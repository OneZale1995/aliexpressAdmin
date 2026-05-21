<?php

namespace App\Services;

use App\Models\AliCategoryProperty;
use App\Models\AliCategoryPropertyValue;
use App\Models\Product;
use App\Models\ProductExportTask;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ProductExportService
{
    private const CHUNK_SIZE = 200;

    // Stream writing keeps memory flat; this cap now mainly controls file size and download usability.
    private const MAX_ROWS_PER_FILE = 20000;

    private const FILE_EXPIRES_DAYS = 7;

    private const EXPORT_PROFILE = 'template-streaming-xlsx-v3';

    private const TEMPLATE_RELATIVE_PATH = 'templates/product_export_template.xlsx';

    private array $categoryPropertyNameCache = [];

    private array $categoryPropertyValueCache = [];

    public function processTask(int $taskId): array
    {
        $task = ProductExportTask::find($taskId);
        if (!$task) {
            return [
                'success' => false,
                'message' => '导出任务不存在',
                'skipped' => true,
            ];
        }

        if ($task->status === 'completed') {
            return [
                'success' => true,
                'message' => '导出任务已完成',
                'skipped' => true,
            ];
        }

        if ($task->status === 'failed') {
            return [
                'success' => false,
                'message' => (string) $task->message,
                'skipped' => true,
            ];
        }

        $user = User::find($task->operator_user_id);
        if (!$user) {
            return $this->markTaskFailed($task, '导出用户不存在');
        }

        $options = is_array($task->options) ? $task->options : [];

        if ($task->status === 'pending') {
            $this->initializeTask($task, $user, $options);
            $task->refresh();
        }

        if ($task->status !== 'running') {
            return [
                'success' => false,
                'message' => '导出任务状态不可执行: ' . $task->status,
                'skipped' => true,
            ];
        }

        try {
            return $this->processNextChunk($task, $user, $options);
        } catch (\Throwable $e) {
            return $this->markTaskFailed($task->fresh() ?: $task, $e->getMessage());
        }
    }

    private function initializeTask(ProductExportTask $task, User $user, array $options): void
    {
        $totalRows = (clone $this->buildExportQuery($user, $options))->count();

        $task->fill([
            'status' => 'running',
            'format' => 'xlsx',
            'total_rows' => $totalRows,
            'processed_rows' => 0,
            'progress' => 0,
            'message' => $totalRows > 0 ? '导出任务开始执行' : '正在生成空导出文件',
            'details' => [
                'last_id' => 0,
                'current_part_index' => 1,
                'rows_in_current_file' => 0,
                'file_paths' => [],
            ],
            'started_at' => $task->started_at ?: now(),
            'finished_at' => null,
            'file_name' => null,
            'file_path' => null,
            'mime_type' => null,
            'file_size' => null,
        ]);
        $task->save();
    }

    private function processNextChunk(ProductExportTask $task, User $user, array $options): array
    {
        $details = is_array($task->details) ? $task->details : [];
        $this->resetInterruptedStreamState($task, $details);

        if ((int) $task->total_rows === 0) {
            $filePath = $this->createEmptyXlsxFile($task->id, 1);

            $this->markTaskCompleted($task->fresh() ?: $task, [$filePath]);

            return [
                'success' => true,
                'message' => '导出任务已完成',
                'skipped' => false,
            ];
        }

        $partIndex = 1;
        $rowsInCurrentFile = 0;
        $processedRows = 0;
        $lastProcessedId = 0;
        $filePaths = [];
        $partContext = $this->startTemplatePartContext($task->id, $partIndex);

        try {
            while (true) {
                $products = $this->buildExportQuery($user, $options)
                    ->select($this->productExportSelectColumns())
                    ->with('shop:id,name,logistics_route,logistics_template_id,logistics_template_name')
                    ->where('id', '>', $lastProcessedId)
                    ->orderBy('id')
                    ->limit(self::CHUNK_SIZE)
                    ->get();

                if ($products->isEmpty()) {
                    unset($products);
                    break;
                }

                foreach ($products as $product) {
                    if ($rowsInCurrentFile >= self::MAX_ROWS_PER_FILE) {
                        $filePaths[] = $this->finalizeTemplatePartContext($partContext);
                        $partIndex++;
                        $rowsInCurrentFile = 0;
                        $partContext = $this->startTemplatePartContext($task->id, $partIndex);
                    }

                    $this->appendProductExportRowXml($partContext, $product);
                    $rowsInCurrentFile++;
                    $processedRows++;
                }

                $lastProcessedId = (int) $products->last()->id;
                $progress = $this->calculateProgress($processedRows, (int) $task->total_rows);

                $task->fill([
                    'processed_rows' => $processedRows,
                    'progress' => $progress,
                    'message' => sprintf('导出中，已处理 %d / %d 条商品', $processedRows, (int) $task->total_rows),
                    'details' => [
                        'last_id' => $lastProcessedId,
                        'current_part_index' => $partIndex,
                        'rows_in_current_file' => $rowsInCurrentFile,
                        'file_paths' => array_values($filePaths),
                    ],
                ]);
                $task->save();

                unset($product, $products);
                gc_collect_cycles();
            }
        } finally {
            if (isset($partContext['rows_handle']) && is_resource($partContext['rows_handle'])) {
                fclose($partContext['rows_handle']);
            }
        }

        if ($rowsInCurrentFile > 0) {
            $filePaths[] = $this->finalizeTemplatePartContext($partContext);
        } else {
            $this->discardTemplatePartContext($partContext);
        }

        $this->markTaskCompleted($task->fresh() ?: $task, $filePaths);

        return [
            'success' => true,
            'message' => '导出任务已完成',
            'skipped' => false,
        ];
    }

    private function buildExportQuery(User $user, array $options): Builder
    {
        $query = Product::query();

        $this->applyPermissionScope($query, $user);

        if (!empty($options['shop_id'])) {
            $query->where('shop_id', $options['shop_id']);
        }
        if (!empty($options['keyword'])) {
            $kw = '%' . $options['keyword'] . '%';
            $query->where(function ($innerQuery) use ($kw) {
                $innerQuery->where('title_en', 'like', $kw)
                    ->orWhere('title_ru', 'like', $kw)
                    ->orWhere('ae_item_id', 'like', $kw);
            });
        }
        if (!empty($options['category_id'])) {
            $query->where('category_id', $options['category_id']);
        }
        if (!empty($options['status_type'])) {
            $query->where('status_type', $options['status_type']);
        }

        return $query;
    }

    private function productExportSelectColumns(): array
    {
        return [
            'id',
            'shop_id',
            'ae_item_id',
            'category_id',
            'bulk_discount',
            'bulk_order',
            'delivery_time',
            'freight_template_id',
            'package_height',
            'package_length',
            'package_width',
            'lot_num',
            'title_en',
            'title_ru',
            'main_image_url',
            'price',
            'gross_weight',
            'descriptions',
            'media',
            'subjects',
            'marketing_images',
            'detail',
            'mobile_detail',
            'properties',
            'skus',
            'raw_data',
        ];
    }

    private function applyPermissionScope(Builder $query, User $user): void
    {
        if ($user->hasRole('super-admin')) {
            return;
        }

        if ($this->isTeamAdmin($user)) {
            $teamIds = Team::where('admin_user_id', $user->id)->pluck('id');
            $shopIds = Shop::whereIn('team_id', $teamIds)->pluck('id');
            $query->whereIn('shop_id', $shopIds);

            return;
        }

        $shopIds = Shop::where('user_id', $user->id)->pluck('id');
        $query->whereIn('shop_id', $shopIds);
    }

    private function isTeamAdmin(User $user): bool
    {
        return Team::where('admin_user_id', $user->id)->exists();
    }

    private function createEmptyXlsxFile(int $taskId, int $partIndex): string
    {
        $relativePath = sprintf('exports/product-exports/task-%d/part_%03d.xlsx', $taskId, $partIndex);
        Storage::disk('local')->makeDirectory(dirname($relativePath));

        $absolutePath = $this->absoluteStoragePath($relativePath);
        @unlink($absolutePath);

        if (!@copy($this->productExportTemplatePath(), $absolutePath)) {
            throw new \RuntimeException('无法复制商品导出模板文件');
        }

        return $relativePath;
    }

    private function markTaskCompleted(ProductExportTask $task, array $filePaths): void
    {
        if (empty($filePaths)) {
            $filePaths[] = $this->createEmptyXlsxFile($task->id, 1);
        }

        $finalRelativePath = count($filePaths) > 1
            ? $this->buildZipArchive($task->id, $filePaths)
            : $filePaths[0];

        $absolutePath = $this->absoluteStoragePath($finalRelativePath);
        $extension = strtolower((string) pathinfo($finalRelativePath, PATHINFO_EXTENSION));
        $downloadExtension = $extension === 'zip' ? 'zip' : 'xlsx';

        $task->fill([
            'status' => 'completed',
            'format' => $downloadExtension,
            'processed_rows' => max((int) $task->processed_rows, (int) $task->total_rows),
            'progress' => 100,
            'file_name' => 'AliExpress_products_' . now()->format('Ymd_His') . '.' . $downloadExtension,
            'file_path' => $finalRelativePath,
            'mime_type' => $downloadExtension === 'zip'
                ? 'application/zip'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size' => is_file($absolutePath) ? filesize($absolutePath) : null,
            'message' => '导出完成',
            'finished_at' => now(),
            'expires_at' => now()->addDays(self::FILE_EXPIRES_DAYS),
            'details' => array_merge(is_array($task->details) ? $task->details : [], [
                'file_paths' => array_values($filePaths),
                'download_path' => $finalRelativePath,
                'export_profile' => self::EXPORT_PROFILE,
            ]),
        ]);
        $task->save();
    }

    private function buildZipArchive(int $taskId, array $filePaths): string
    {
        $relativePath = sprintf('exports/product-exports/task-%d/product_export_task_%d.zip', $taskId, $taskId);
        Storage::disk('local')->makeDirectory(dirname($relativePath));

        $zip = new ZipArchive();
        $openResult = $zip->open($this->absoluteStoragePath($relativePath), ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            throw new \RuntimeException('无法创建导出压缩文件');
        }

        foreach (array_values($filePaths) as $index => $filePath) {
            $absolutePath = $this->absoluteStoragePath($filePath);
            if (!is_file($absolutePath)) {
                continue;
            }

            $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION)) ?: 'xlsx';
            $zip->addFile($absolutePath, sprintf('AliExpress_products_part_%03d.%s', $index + 1, $extension));
        }

        $zip->close();

        foreach ($filePaths as $filePath) {
            @unlink($this->absoluteStoragePath($filePath));
        }

        return $relativePath;
    }

    private function markTaskFailed(ProductExportTask $task, string $message): array
    {
        Storage::disk('local')->deleteDirectory(sprintf('exports/product-exports/task-%d', $task->id));

        $task->fill([
            'status' => 'failed',
            'progress' => 100,
            'message' => $message,
            'finished_at' => now(),
        ]);
        $task->save();

        return [
            'success' => false,
            'message' => $message,
            'skipped' => false,
        ];
    }

    private function calculateProgress(int $processedRows, int $totalRows): int
    {
        if ($totalRows <= 0) {
            return 100;
        }

        return min(99, max(0, (int) floor(($processedRows / $totalRows) * 100)));
    }

    private function absoluteStoragePath(string $relativePath): string
    {
        return Storage::disk('local')->path(ltrim($relativePath, '/'));
    }

    private function resetInterruptedStreamState(ProductExportTask $task, array $details): void
    {
        $hasPartialState = (int) $task->processed_rows > 0
            || (int) ($details['last_id'] ?? 0) > 0
            || !empty((array) ($details['file_paths'] ?? []));

        if (!$hasPartialState) {
            return;
        }

        Storage::disk('local')->deleteDirectory(sprintf('exports/product-exports/task-%d', $task->id));

        $task->fill([
            'processed_rows' => 0,
            'progress' => 0,
            'message' => '检测到中断的导出任务，已从头重新生成',
            'details' => [
                'last_id' => 0,
                'current_part_index' => 1,
                'rows_in_current_file' => 0,
                'file_paths' => [],
            ],
            'file_name' => null,
            'file_path' => null,
            'mime_type' => null,
            'file_size' => null,
            'finished_at' => null,
            'expires_at' => null,
        ]);
        $task->save();
    }

    private function startTemplatePartContext(int $taskId, int $partIndex): array
    {
        $relativePath = sprintf('exports/product-exports/task-%d/part_%03d.xlsx', $taskId, $partIndex);
        $rowsTempRelativePath = sprintf('exports/product-exports/task-%d/part_%03d.rows.xml.tmp', $taskId, $partIndex);
        $directory = dirname($relativePath);

        Storage::disk('local')->makeDirectory($directory);

        $rowsTempAbsolutePath = $this->absoluteStoragePath($rowsTempRelativePath);
        @unlink($rowsTempAbsolutePath);

        $rowsHandle = fopen($rowsTempAbsolutePath, 'wb');
        if ($rowsHandle === false) {
            throw new \RuntimeException('无法创建导出临时数据文件');
        }

        return [
            'task_id' => $taskId,
            'part_index' => $partIndex,
            'relative_path' => $relativePath,
            'rows_temp_absolute_path' => $rowsTempAbsolutePath,
            'rows_handle' => $rowsHandle,
            'data_row_count' => 0,
        ];
    }

    private function appendProductExportRowXml(array &$context, Product $product): void
    {
        $rowNumber = $this->productExportTemplateHeaderRowCount() + 1 + (int) $context['data_row_count'];
        $rowXml = $this->buildProductExportWorksheetRowXml($rowNumber, $this->buildProductExportRow($product));

        if (fwrite($context['rows_handle'], $rowXml) === false) {
            throw new \RuntimeException('写入导出数据行失败');
        }

        $context['data_row_count'] = (int) $context['data_row_count'] + 1;
    }

    private function finalizeTemplatePartContext(array &$context): string
    {
        if (isset($context['rows_handle']) && is_resource($context['rows_handle'])) {
            fclose($context['rows_handle']);
            $context['rows_handle'] = null;
        }

        $sheetXmlAbsolutePath = $this->writeTemplateWorksheetXml($context);
        $targetAbsolutePath = $this->absoluteStoragePath($context['relative_path']);

        @unlink($targetAbsolutePath);
        if (!@copy($this->productExportTemplatePath(), $targetAbsolutePath)) {
            throw new \RuntimeException('无法复制商品导出模板文件');
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($targetAbsolutePath);
        if ($openResult !== true) {
            throw new \RuntimeException('无法打开导出 Excel 文件');
        }

        try {
            if (!$zip->deleteName('xl/worksheets/sheet1.xml')) {
                throw new \RuntimeException('无法替换商品导出模板工作表');
            }

            if (!$zip->addFile($sheetXmlAbsolutePath, 'xl/worksheets/sheet1.xml')) {
                throw new \RuntimeException('无法写入商品导出工作表内容');
            }
        } finally {
            $zip->close();
            @unlink($sheetXmlAbsolutePath);
            @unlink($context['rows_temp_absolute_path']);
        }

        return $context['relative_path'];
    }

    private function discardTemplatePartContext(array $context): void
    {
        if (isset($context['rows_handle']) && is_resource($context['rows_handle'])) {
            fclose($context['rows_handle']);
        }

        if (!empty($context['rows_temp_absolute_path'])) {
            @unlink($context['rows_temp_absolute_path']);
        }
    }

    private function writeTemplateWorksheetXml(array $context): string
    {
        $sheetXmlRelativePath = sprintf(
            'exports/product-exports/task-%d/part_%03d.sheet1.xml.tmp',
            $context['task_id'],
            $context['part_index']
        );
        $sheetXmlAbsolutePath = $this->absoluteStoragePath($sheetXmlRelativePath);
        @unlink($sheetXmlAbsolutePath);

        $sheetHandle = fopen($sheetXmlAbsolutePath, 'wb');
        if ($sheetHandle === false) {
            throw new \RuntimeException('无法创建商品导出工作表临时文件');
        }

        $templateParts = $this->productExportTemplateSheetParts();
        $prefix = preg_replace(
            '/(<dimension[^>]*ref=")[^"]*(")/',
            '$1' . $this->buildTemplateDimensionRef((int) $context['data_row_count']) . '$2',
            $templateParts['sheet_prefix'],
            1
        );

        if (!is_string($prefix)) {
            fclose($sheetHandle);
            throw new \RuntimeException('无法更新商品导出模板维度');
        }

        try {
            fwrite($sheetHandle, $prefix);

            $rowsHandle = fopen($context['rows_temp_absolute_path'], 'rb');
            if ($rowsHandle === false) {
                throw new \RuntimeException('无法读取导出临时数据文件');
            }

            try {
                stream_copy_to_stream($rowsHandle, $sheetHandle);
            } finally {
                fclose($rowsHandle);
            }

            fwrite($sheetHandle, $templateParts['sheet_suffix']);
        } finally {
            fclose($sheetHandle);
        }

        return $sheetXmlAbsolutePath;
    }

    private function productExportTemplateSheetParts(): array
    {
        static $parts = null;

        if ($parts !== null) {
            return $parts;
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($this->productExportTemplatePath());
        if ($openResult !== true) {
            throw new \RuntimeException('无法打开商品导出模板文件');
        }

        try {
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        } finally {
            $zip->close();
        }

        if (!is_string($sheetXml) || $sheetXml === '') {
            throw new \RuntimeException('商品导出模板缺少工作表定义');
        }

        if (!preg_match('/<dimension[^>]*ref="([A-Z]+)(\d+):([A-Z]+)(\d+)"/', $sheetXml, $matches)) {
            throw new \RuntimeException('无法解析商品导出模板维度');
        }

        $sheetDataParts = explode('</sheetData>', $sheetXml, 2);
        if (count($sheetDataParts) !== 2) {
            throw new \RuntimeException('商品导出模板工作表结构不完整');
        }

        $parts = [
            'first_column' => $matches[1],
            'last_column' => $matches[3],
            'header_row_count' => (int) $matches[4],
            'column_count' => $this->columnIndexFromLetters($matches[3]) + 1,
            'sheet_prefix' => $sheetDataParts[0],
            'sheet_suffix' => '</sheetData>' . $sheetDataParts[1],
        ];

        return $parts;
    }

    private function productExportTemplatePath(): string
    {
        $templatePath = resource_path(self::TEMPLATE_RELATIVE_PATH);
        if (!is_file($templatePath)) {
            throw new \RuntimeException('商品导出模板文件不存在');
        }

        return $templatePath;
    }

    private function productExportTemplateHeaderRowCount(): int
    {
        return (int) $this->productExportTemplateSheetParts()['header_row_count'];
    }

    private function productExportTemplateColumnCount(): int
    {
        return (int) $this->productExportTemplateSheetParts()['column_count'];
    }

    private function buildTemplateDimensionRef(int $dataRowCount): string
    {
        $templateParts = $this->productExportTemplateSheetParts();
        $lastRow = $this->productExportTemplateHeaderRowCount() + max(0, $dataRowCount);

        return sprintf('%s1:%s%d', $templateParts['first_column'], $templateParts['last_column'], $lastRow);
    }

    private function buildProductExportWorksheetRowXml(int $rowNumber, array $rowValues): string
    {
        $columnCount = $this->productExportTemplateColumnCount();
        $values = array_slice(array_pad($rowValues, $columnCount, ''), 0, $columnCount);
        $cellsXml = '';

        foreach ($values as $columnIndex => $value) {
            $value = (string) $value;
            if ($value === '') {
                continue;
            }

            $cellsXml .= $this->buildProductExportWorksheetCellXml($columnIndex, $rowNumber, $value);
        }

        return sprintf('<row r="%d" spans="1:%d">%s</row>', $rowNumber, $columnCount, $cellsXml);
    }

    private function buildProductExportWorksheetCellXml(int $columnIndex, int $rowNumber, string $value): string
    {
        $cellReference = $this->columnLettersFromIndex($columnIndex) . $rowNumber;

        if ($this->isProductExportNumericColumn($columnIndex) && is_numeric($value)) {
            return sprintf('<c r="%s"><v>%s</v></c>', $cellReference, trim($value));
        }

        return sprintf(
            '<c r="%s" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
            $cellReference,
            $this->sanitizeWorksheetInlineString($value)
        );
    }

    private function isProductExportNumericColumn(int $columnIndex): bool
    {
        static $numericColumns = null;

        if ($numericColumns === null) {
            $numericColumns = array_fill_keys([7, 14, 15, 16, 46, 47, 48, 49, 50, 53, 54], true);
        }

        return isset($numericColumns[$columnIndex]);
    }

    private function sanitizeWorksheetInlineString(string $value): string
    {
        $sanitized = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value);

        return htmlspecialchars($sanitized ?? $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function columnLettersFromIndex(int $columnIndex): string
    {
        $index = $columnIndex + 1;
        $letters = '';

        while ($index > 0) {
            $index--;
            $letters = chr(($index % 26) + 65) . $letters;
            $index = intdiv($index, 26);
        }

        return $letters;
    }

    private function columnIndexFromLetters(string $columnLetters): int
    {
        $index = 0;
        $length = strlen($columnLetters);

        for ($offset = 0; $offset < $length; $offset++) {
            $index = ($index * 26) + (ord($columnLetters[$offset]) - 64);
        }

        return $index - 1;
    }

    private function buildProductExportRow(Product $product): array
    {
        $row = array_fill(0, 56, '');
        $images = $this->extractProductImages($product);
        $firstSku = $this->extractFirstSku($product);
        $titleRu = $this->firstNonEmpty([$product->title_ru, $this->extractLocalizedSubject($product, ['ru_RU', 'ru'])]);
        $titleEn = $this->firstNonEmpty([$product->title_en, $this->extractLocalizedSubject($product, ['en_US', 'en'])]);

        $row[0] = (string) $product->ae_item_id;
        $row[1] = $this->extractFirstSkuField($firstSku, ['code', 'sku_id', 'id', 'seller_sku_id', 'seller_sku', 'sku_code', 'barcode', 'ean']);
        $row[2] = $titleRu;
        $row[3] = $titleEn;
        $row[4] = $this->extractLocalizedDescription($product, ['ru_RU', 'ru']);
        $row[5] = $this->extractLocalizedDescription($product, ['en_US', 'en']);
        $row[6] = $this->extractRawField($product, ['selling_method', 'sale_type']);
        $row[7] = (string) ($product->lot_num ?? '');
        $row[8] = $images[0] ?? '';
        $row[9] = $images[1] ?? '';
        $row[10] = $images[2] ?? '';
        $row[11] = $images[3] ?? '';
        $row[12] = $images[4] ?? '';
        $row[13] = $images[5] ?? '';
        $row[14] = $this->firstNonEmpty([$this->extractFirstSkuField($firstSku, ['price']), (string) ($product->price ?? '')]);
        $row[15] = $this->extractFirstSkuField($firstSku, ['discount_price', 'sale_price', 'promo_price']);
        $row[16] = $this->extractStocks($product);
        $row[17] = $this->firstNonEmpty([
            $this->extractPropertyValue($product, ['model number', 'model']),
            $this->extractFirstSkuField($firstSku, ['code', 'barcode']),
        ]);
        $row[18] = $this->extractPropertyValue($product, ['origin']);
        $row[19] = $this->extractPropertyValue($product, ['gender']);
        $row[20] = $this->extractPropertyValue($product, ['department name']);
        $row[21] = $this->extractPropertyValue($product, ['closure type']);
        $row[22] = $this->extractPropertyValue($product, ['pattern type']);
        $row[23] = $this->extractPropertyValue($product, ['fit']);
        $row[24] = $this->extractPropertyValue($product, ['season']);
        $row[25] = $this->extractPropertyValue($product, ['fashion element']);
        $row[26] = $this->extractPropertyValue($product, ['upper material']);
        $row[27] = $this->extractPropertyValue($product, ['insole material']);
        $row[28] = $this->extractPropertyValue($product, ['outsole material']);
        $row[29] = $this->extractPropertyValue($product, ['lining material']);
        $row[30] = $this->extractPropertyValue($product, ['heel height']);
        $row[31] = $this->extractPropertyValue($product, ['upper fixing method']);
        $row[32] = $this->extractPropertyValue($product, ['upper coverage']);
        $row[33] = $this->extractPropertyValue($product, ['with metal toe cap']);
        $row[34] = $this->extractPropertyValue($product, ['with or install professional accessories']);
        $row[35] = $this->extractPropertyValue($product, ['whether waterproof']);
        $row[36] = $this->extractPropertyValue($product, ['shoe size (CN)', 'shoe size', 'size (cn)']);
        $row[42] = $this->firstNonEmpty([$this->extractFirstSkuImage($firstSku), $images[0] ?? '']);
        $row[43] = $this->extractSkuPropertyValue($product, $firstSku, ['shoe size', 'size']);
        $row[44] = $this->extractSkuPropertyValue($product, $firstSku, ['color', 'colour']);
        $row[45] = $row[44];
        $row[46] = (string) ($product->delivery_time ?? '');
        $row[47] = $product->gross_weight !== null ? (string) $product->gross_weight : '';
        $row[48] = (string) ($product->package_length ?? '');
        $row[49] = (string) ($product->package_width ?? '');
        $row[50] = (string) ($product->package_height ?? '');
        $row[51] = $this->firstNonEmpty([
            $this->extractRawField($product, ['shipping_template_name', 'freight_template_name']),
            $product->shop ? (string) ($product->shop->logistics_template_name ?? '') : '',
            $product->shop ? (string) ($product->shop->logistics_template_id ?? '') : '',
            (string) ($product->freight_template_id ?? ''),
            (string) ($product->raw_data['freight_template_id'] ?? ''),
        ]);
        $row[52] = $this->firstNonEmpty([$this->extractRawField($product, ['type_of_logistics', 'logistics_type']), $product->shop ? (string) ($product->shop->logistics_route ?? '') : '']);
        $row[53] = $product->bulk_discount !== null ? (string) $product->bulk_discount : '';
        $row[54] = $product->bulk_order !== null ? (string) $product->bulk_order : '';

        return $row;
    }

    private function extractLocalizedDescription(Product $product, array $locales): string
    {
        $descriptions = (array) ($product->descriptions ?? $product->raw_data['descriptions'] ?? []);

        foreach ($descriptions as $description) {
            if (!is_array($description)) {
                continue;
            }

            $locale = (string) ($description['locale'] ?? $description['language'] ?? '');
            if ($locale !== '' && !in_array($locale, $locales, true)) {
                continue;
            }

            foreach (['description', 'content', 'text', 'value', 'html', 'name', 'web_detail', 'mobile_detail'] as $field) {
                $value = trim(strip_tags((string) ($description[$field] ?? '')));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        if (in_array('en_US', $locales, true) || in_array('en', $locales, true)) {
            return trim(strip_tags((string) ($product->detail ?? $product->raw_data['detail'] ?? '')));
        }

        return trim(strip_tags((string) ($product->mobile_detail ?? $product->raw_data['mobile_detail'] ?? '')));
    }

    private function extractLocalizedSubject(Product $product, array $locales): string
    {
        $subjects = (array) ($product->subjects ?? $product->raw_data['subject'] ?? []);

        foreach ($subjects as $subject) {
            if (!is_array($subject)) {
                continue;
            }

            $locale = (string) ($subject['locale'] ?? $subject['language'] ?? '');
            if ($locale !== '' && !in_array($locale, $locales, true)) {
                continue;
            }

            foreach (['name', 'title', 'value', 'text'] as $field) {
                $value = trim((string) ($subject[$field] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    private function extractProductImages(Product $product): array
    {
        $images = [];

        if (!empty($product->main_image_url)) {
            $images[] = (string) $product->main_image_url;
        }

        $this->appendImageUrls($images, $product->media);
        $this->appendImageUrls($images, $product->marketing_images);
        $this->appendImageUrls($images, $product->raw_data['media'] ?? null);
        $this->appendImageUrls($images, $product->raw_data['marketing_images'] ?? null);
        $this->appendImageUrls($images, $product->raw_data['images'] ?? null);
        $this->appendImageUrls($images, $product->raw_data['image_urls'] ?? null);
        $this->appendImageUrls($images, $product->raw_data['gallery'] ?? null);

        return array_values(array_slice(array_unique(array_filter($images)), 0, 6));
    }

    private function appendImageUrls(array &$images, $source): void
    {
        if (is_string($source)) {
            $value = trim($source);
            if ($value !== '' && preg_match('#^https?://#i', $value)) {
                $images[] = $value;
            }

            return;
        }

        if (!is_array($source)) {
            return;
        }

        foreach ($source as $key => $value) {
            if (is_string($value) && preg_match('#^https?://#i', trim($value))) {
                $images[] = trim($value);
                continue;
            }

            if (is_array($value)) {
                $this->appendImageUrls($images, $value);
                continue;
            }

            if (is_string($key) && is_string($value) && in_array($key, ['url', 'src', 'image', 'image_url', 'imageUrl', 'main_image_url'], true)) {
                $images[] = trim($value);
            }
        }
    }

    private function extractFirstSku(Product $product): array
    {
        $skus = is_array($product->skus) && !empty($product->skus)
            ? $product->skus
            : (is_array($product->raw_data['sku'] ?? null) ? $product->raw_data['sku'] : []);

        if (empty($skus)) {
            return [];
        }

        $firstSku = reset($skus);

        return is_array($firstSku) ? $firstSku : [];
    }

    private function extractFirstSkuField(array $sku, array $fields): string
    {
        foreach ($fields as $field) {
            $value = trim((string) ($sku[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function extractFirstSkuImage(array $sku): string
    {
        foreach (['image', 'image_url', 'imageUrl', 'sku_image', 'sku_image_url'] as $field) {
            $value = trim((string) ($sku[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function extractSkuPropertyValue(Product $product, array $sku, array $candidateNames): string
    {
        foreach ($this->resolveProperties($product, (array) ($sku['property'] ?? $sku['properties'] ?? []), true) as $property) {
            if ($this->matchesCandidateName(strtolower($property['name']), $candidateNames)) {
                return $property['value'];
            }
        }

        return '';
    }

    private function extractPropertyValue(Product $product, array $candidateNames): string
    {
        foreach ($this->resolveProperties($product, (array) ($product->properties ?? $product->raw_data['property'] ?? []), false) as $property) {
            if ($this->matchesCandidateName(strtolower($property['name']), $candidateNames)) {
                return $property['value'];
            }
        }

        return '';
    }

    private function extractStocks(Product $product): string
    {
        $total = 0;
        $hasValue = false;

        foreach ((array) ($product->skus ?? []) as $sku) {
            if (!is_array($sku)) {
                continue;
            }

            foreach (['ipm_sku_stock', 'stock', 'stocks', 'quantity', 'available_quantity', 'inventory'] as $field) {
                if (!isset($sku[$field]) || $sku[$field] === '') {
                    continue;
                }

                if (is_bool($sku[$field])) {
                    continue;
                }

                $total += (int) $sku[$field];
                $hasValue = true;
                break;
            }
        }

        return $hasValue ? (string) $total : '';
    }

    private function extractRawField(Product $product, array $fields): string
    {
        foreach ($fields as $field) {
            $value = trim((string) ($product->raw_data[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveProperties(Product $product, array $properties, bool $isSkuProperty): array
    {
        $resolved = [];
        $categoryId = (string) ($product->category_id ?: ($product->raw_data['category_id'] ?? ''));
        $nameMap = $categoryId !== '' ? $this->getCategoryPropertyNameMap($categoryId, $isSkuProperty) : [];

        foreach ($properties as $property) {
            if (!is_array($property)) {
                continue;
            }

            $propertyId = (string) ($property['name_id'] ?? $property['property_id'] ?? $property['id'] ?? '');
            $valueId = (string) ($property['value_id'] ?? '');
            $name = trim((string) ($property['name'] ?? $property['prop_name'] ?? $property['attribute_name'] ?? ($nameMap[$propertyId]['name'] ?? '')));
            $value = trim((string) ($property['value'] ?? $property['value_name'] ?? $property['text'] ?? $property['value_definition'] ?? ''));

            if ($value === '' && $categoryId !== '' && $propertyId !== '' && $valueId !== '') {
                $valueMap = $this->getCategoryPropertyValueMap($categoryId, $propertyId, $isSkuProperty);
                $value = trim((string) ($valueMap[$valueId] ?? ''));
            }

            if ($name === '' || $value === '') {
                continue;
            }

            $resolved[] = [
                'id' => $propertyId,
                'name' => $name,
                'value' => $value,
            ];
        }

        return $resolved;
    }

    private function getCategoryPropertyNameMap(string $categoryId, bool $isSkuProperty): array
    {
        $cacheKey = $categoryId . ':' . ($isSkuProperty ? '1' : '0');
        if (isset($this->categoryPropertyNameCache[$cacheKey])) {
            return $this->categoryPropertyNameCache[$cacheKey];
        }

        $map = [];

        AliCategoryProperty::query()
            ->where('category_id', $categoryId)
            ->where('is_sku_property', $isSkuProperty)
            ->get(['property_id', 'name'])
            ->each(function (AliCategoryProperty $property) use (&$map) {
                $map[$property->property_id] = ['name' => (string) ($property->name ?: $property->property_id)];
            });

        $this->categoryPropertyNameCache[$cacheKey] = $map;

        return $map;
    }

    private function getCategoryPropertyValueMap(string $categoryId, string $propertyId, bool $isSkuProperty): array
    {
        $cacheKey = implode(':', [$categoryId, $propertyId, $isSkuProperty ? '1' : '0']);
        if (isset($this->categoryPropertyValueCache[$cacheKey])) {
            return $this->categoryPropertyValueCache[$cacheKey];
        }

        $map = [];

        AliCategoryPropertyValue::query()
            ->where('category_id', $categoryId)
            ->where('property_id', $propertyId)
            ->where('is_sku_property', $isSkuProperty)
            ->orderByRaw("CASE WHEN shipping_template_id = '' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get(['value_id', 'name'])
            ->each(function (AliCategoryPropertyValue $value) use (&$map) {
                if (!array_key_exists($value->value_id, $map)) {
                    $map[$value->value_id] = (string) $value->name;
                }
            });

        $this->categoryPropertyValueCache[$cacheKey] = $map;

        return $map;
    }

    private function matchesCandidateName(string $name, array $candidateNames): bool
    {
        foreach ($candidateNames as $candidateName) {
            $candidateName = strtolower($candidateName);
            if ($name === $candidateName || str_contains($name, $candidateName)) {
                return true;
            }
        }

        return false;
    }

    private function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
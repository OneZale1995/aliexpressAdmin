<?php

namespace App\Services;

use App\Jobs\RunProductExportTask;
use App\Models\AliCategoryProperty;
use App\Models\AliCategoryPropertyValue;
use App\Models\Product;
use App\Models\ProductExportTask;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

class ProductExportService
{
    private const CHUNK_SIZE = 1000;

    private const MAX_ROWS_PER_FILE = 10000;

    private const FILE_EXPIRES_DAYS = 7;

    private const EXPORT_PROFILE = 'template-xlsx-v1';

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
        $lastId = (int) ($details['last_id'] ?? 0);
        $partIndex = max(1, (int) ($details['current_part_index'] ?? 1));
        $rowsInCurrentFile = max(0, (int) ($details['rows_in_current_file'] ?? 0));
        $filePaths = array_values(array_filter((array) ($details['file_paths'] ?? [])));

        if ((int) $task->total_rows === 0) {
            if (empty($filePaths)) {
                $filePaths[] = $this->createCsvFile($task->id, 1);
            }

            $this->markTaskCompleted($task->fresh() ?: $task, $filePaths);

            return [
                'success' => true,
                'message' => '导出任务已完成',
                'skipped' => false,
            ];
        }

        $products = $this->buildExportQuery($user, $options)
            ->with('shop:id,name,logistics_route,logistics_template_id,logistics_template_name')
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit(self::CHUNK_SIZE)
            ->get();

        if ($products->isEmpty()) {
            $this->markTaskCompleted($task->fresh() ?: $task, $filePaths);

            return [
                'success' => true,
                'message' => '导出任务已完成',
                'skipped' => false,
            ];
        }

        if (!isset($filePaths[$partIndex - 1])) {
            $filePaths[$partIndex - 1] = $this->createCsvFile($task->id, $partIndex);
        }

        $currentFilePath = $filePaths[$partIndex - 1];
        $handle = fopen($this->absoluteStoragePath($currentFilePath), 'ab');

        if ($handle === false) {
            throw new \RuntimeException('无法打开导出文件进行写入');
        }

        try {
            foreach ($products as $product) {
                if ($rowsInCurrentFile >= self::MAX_ROWS_PER_FILE) {
                    fclose($handle);
                    $partIndex++;
                    $rowsInCurrentFile = 0;

                    if (!isset($filePaths[$partIndex - 1])) {
                        $filePaths[$partIndex - 1] = $this->createCsvFile($task->id, $partIndex);
                    }

                    $currentFilePath = $filePaths[$partIndex - 1];
                    $handle = fopen($this->absoluteStoragePath($currentFilePath), 'ab');
                    if ($handle === false) {
                        throw new \RuntimeException('无法打开导出分片文件进行写入');
                    }
                }

                fputcsv($handle, $this->buildProductExportRow($product));
                $rowsInCurrentFile++;
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $processedRows = (int) $task->processed_rows + $products->count();
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

        if ($processedRows >= (int) $task->total_rows) {
            $this->markTaskCompleted($task->fresh() ?: $task, $filePaths);

            return [
                'success' => true,
                'message' => '导出任务已完成',
                'skipped' => false,
            ];
        }

        RunProductExportTask::dispatch($task->id)->onQueue(RunProductExportTask::QUEUE_NAME);

        return [
            'success' => true,
            'message' => '导出任务分片已写入，等待下一批继续处理',
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

    private function createCsvFile(int $taskId, int $partIndex): string
    {
        $relativePath = sprintf('exports/product-exports/task-%d/part_%03d.csv', $taskId, $partIndex);
        Storage::disk('local')->makeDirectory(dirname($relativePath));

        $handle = fopen($this->absoluteStoragePath($relativePath), 'wb');
        if ($handle === false) {
            throw new \RuntimeException('无法创建导出文件');
        }

        try {
            fwrite($handle, "\xEF\xBB\xBF");
            foreach ($this->productExportHeaderRows() as $headerRow) {
                fputcsv($handle, $headerRow);
            }
        } finally {
            fclose($handle);
        }

        return $relativePath;
    }

    private function markTaskCompleted(ProductExportTask $task, array $filePaths): void
    {
        if (empty($filePaths)) {
            $filePaths[] = $this->createCsvFile($task->id, 1);
        }

        $xlsxFilePaths = $this->convertCsvPartsToStyledXlsx($task->id, $filePaths);

        $finalRelativePath = count($xlsxFilePaths) > 1
            ? $this->buildZipArchive($task->id, $xlsxFilePaths)
            : $xlsxFilePaths[0];

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
                'file_paths' => array_values($xlsxFilePaths),
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

    private function convertCsvPartsToStyledXlsx(int $taskId, array $csvFilePaths): array
    {
        $xlsxFilePaths = [];

        foreach (array_values($csvFilePaths) as $index => $csvFilePath) {
            $xlsxFilePaths[] = $this->convertCsvPartToStyledXlsx($taskId, $csvFilePath, $index + 1);
        }

        return $xlsxFilePaths;
    }

    private function convertCsvPartToStyledXlsx(int $taskId, string $csvFilePath, int $partIndex): string
    {
        $csvAbsolutePath = $this->absoluteStoragePath($csvFilePath);
        if (!is_file($csvAbsolutePath)) {
            throw new \RuntimeException('导出分片文件不存在，无法生成 Excel');
        }

        $relativePath = sprintf('exports/product-exports/task-%d/part_%03d.xlsx', $taskId, $partIndex);
        Storage::disk('local')->makeDirectory(dirname($relativePath));

        $spreadsheet = $this->loadExportTemplateSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $rowNumber = 4;
        $handle = fopen($csvAbsolutePath, 'rb');

        if ($handle === false) {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            throw new \RuntimeException('无法读取导出分片文件');
        }

        try {
            $lineNumber = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                if ($lineNumber <= 3) {
                    continue;
                }

                $sheet->fromArray([
                    array_slice(array_pad($row, 56, ''), 0, 56),
                ], null, 'A' . $rowNumber, true);
                $rowNumber++;
            }
        } finally {
            fclose($handle);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($this->absoluteStoragePath($relativePath));

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        @unlink($csvAbsolutePath);

        return $relativePath;
    }

    private function loadExportTemplateSpreadsheet(): Spreadsheet
    {
        $templatePath = $this->productExportTemplatePath();

        if (is_file($templatePath)) {
            $spreadsheet = IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = max(
                (int) $sheet->getHighestDataRow(),
                (int) $sheet->getHighestRow()
            );

            if ($highestRow > 3) {
                $sheet->removeRow(4, $highestRow - 3);
            }

            return $spreadsheet;
        }

        return $this->buildStyledSpreadsheet();
    }

    private function productExportTemplatePath(): string
    {
        return resource_path(self::TEMPLATE_RELATIVE_PATH);
    }

    private function buildStyledSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($this->productExportHeaderRows(), null, 'A1', true);

        foreach ($this->productExportHeaderMerges() as $mergeRange) {
            $sheet->mergeCells($mergeRange);
        }

        $this->applyProductExportHeaderStyles($sheet);
        $this->applyProductExportColumnWidths($sheet);

        return $spreadsheet;
    }

    private function productExportHeaderMerges(): array
    {
        return [
            'A1:B1',
            'C1:H1',
            'I1:N1',
            'O1:Q1',
            'R1:AK1',
            'AL1:AP1',
            'AQ1:AT1',
            'AU1:BA1',
            'BB1:BC1',
            'C2:D2',
            'E2:F2',
        ];
    }

    private function applyProductExportHeaderStyles($sheet): void
    {
        $sheet->getStyle('A1:BD3')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:BD3')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A1:BD3')->getBorders()->getAllBorders()->setBorderStyle('thin');

        $sheet->getStyle('A1:BD1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F7E7B6']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('A2:BD2')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '666666']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFF9E8']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('A3:BD3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E6F0FF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(2)->setRowHeight(66);
        $sheet->getRowDimension(3)->setRowHeight(34);
    }

    private function applyProductExportColumnWidths($sheet): void
    {
        $widths = [
            'A' => 24, 'B' => 22, 'C' => 30, 'D' => 30, 'E' => 38, 'F' => 38, 'G' => 16, 'H' => 14,
            'I' => 20, 'J' => 20, 'K' => 20, 'L' => 20, 'M' => 20, 'N' => 20,
            'O' => 14, 'P' => 18, 'Q' => 12,
            'R' => 18, 'S' => 16, 'T' => 14, 'U' => 18, 'V' => 16, 'W' => 16, 'X' => 16, 'Y' => 16,
            'Z' => 18, 'AA' => 18, 'AB' => 18, 'AC' => 18, 'AD' => 18, 'AE' => 16, 'AF' => 18,
            'AG' => 18, 'AH' => 18, 'AI' => 22, 'AJ' => 18, 'AK' => 16,
            'AL' => 18, 'AM' => 18, 'AN' => 18, 'AO' => 18, 'AP' => 18,
            'AQ' => 20, 'AR' => 14, 'AS' => 14, 'AT' => 18,
            'AU' => 18, 'AV' => 16, 'AW' => 16, 'AX' => 16, 'AY' => 16, 'AZ' => 18,
            'BA' => 16, 'BB' => 16, 'BC' => 18, 'BD' => 12,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle('A4:BD1048576')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('C4:F1048576')->getAlignment()->setWrapText(true);
    }

    private function productExportHeaderRows(): array
    {
        return [
            [
                '', '', '', '', '', '', '', '',
                'Product image', '', '', '', '', '',
                'Prices and stocks', '', '',
                'Detailed description', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                'Any seller\'s attributes', '', '', '', '',
                "Product variation info (SKU).\nProduct variations sharing the same SPU ID are grouped on one product page", '', '', '',
                'Packaging and shipment', '', '', '', '', '', '',
                'Bulk price', '', '',
            ],
            [
                'Do not change it',
                'Unique SKU ID, barcode or EAN of the product in your system. No more than 50 characters',
                'At least you should fill in the column in the product language. Unfilled columns will be translated automatically',
                '',
                'At least you should fill in the column in the product language. Unfilled columns will be translated automatically',
                '',
                'In the drop-down list, select the value',
                'If product sells in a set, specify the number of products in one set from 1 to 100000',
                'Link to an image in external data source',
                'Link to an image in external data source',
                'Link to an image in external data source',
                'Link to an image in external data source',
                'Link to an image in external data source',
                'Link to an image in external data source',
                '',
                '',
                'Number of products for sale. Only Integer values',
                '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                'Add any attribute. In the row below, specify attribute name (less than 128 symbols), then in the product row, specify the attribute value (less than 128 symbols). Use Cyrillic and Latin characters, punctuation marks and numerals.',
                'Add any attribute. In the row below, specify attribute name (less than 128 symbols), then in the product row, specify the attribute value (less than 128 symbols). Use Cyrillic and Latin characters, punctuation marks and numerals.',
                'Add any attribute. In the row below, specify attribute name (less than 128 symbols), then in the product row, specify the attribute value (less than 128 symbols). Use Cyrillic and Latin characters, punctuation marks and numerals.',
                'Add any attribute. In the row below, specify attribute name (less than 128 symbols), then in the product row, specify the attribute value (less than 128 symbols). Use Cyrillic and Latin characters, punctuation marks and numerals.',
                'Add any attribute. In the row below, specify attribute name (less than 128 symbols), then in the product row, specify the attribute value (less than 128 symbols). Use Cyrillic and Latin characters, punctuation marks and numerals.',
                'Link to an image in external data source',
                '', '', '', '',
                'from 1 to 30 days. We recommend to use less than 5 days. You should not specify it for products with FBA logistics',
                'from 0.001 to 500',
                'from 1 to 700',
                'from 1 to 700',
                'from 1 to 700',
                'In the drop-down list, select the value. View available values or add a new one',
                '', '', '',
            ],
            [
                'AliExpress product ID (seller SPU ID)*',
                'SKU ID/Barcode (seller SKU ID)*',
                'Product name (Russian)',
                'Product name (English)',
                'Description (Russian)',
                'Description (English)',
                'Selling method*',
                'Quantity in packaging',
                'Main image*',
                'Image #2',
                'Image #3',
                'Image #4',
                'Image #5',
                'Image #6',
                'Price, CNY*',
                'Discounted price, CNY',
                'Stocks',
                'Model Number',
                'Origin',
                'Gender',
                'Department Name',
                'Closure Type',
                'Pattern Type',
                'Fit',
                'Season',
                'Fashion Element',
                'Upper Material',
                'Insole Material',
                'Outsole Material',
                'Lining Material',
                'Heel Height',
                'Upper fixing method',
                'Upper coverage',
                'With metal toe cap',
                'With or install Professional accessories',
                'Whether waterproof',
                'Shoe size (CN)',
                '',
                '',
                '',
                '',
                '',
                'Variation Image',
                'Shoe Size',
                'Color',
                'Сolor name in your system',
                'Order processing time (days)*',
                'Weight in packaging (kg)*',
                'Length in packaging (cm)*',
                'Width in packaging (cm)*',
                'Height in packaging (cm)*',
                'Shipping template*',
                'Type of logistics',
                'Bulk discount, %',
                'Bulk order from, pcs.',
                '',
            ],
        ];
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
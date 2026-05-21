<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunProductExportTask;
use App\Models\AliCategoryProperty;
use App\Models\AliCategoryPropertyValue;
use App\Models\Product;
use App\Models\ProductExportTask;
use App\Models\Shop;
use App\Models\Team;
use App\Services\ProductSyncStateService;
use App\Services\ProductExportService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProductController extends Controller
{
    use ApiResponse;

    private const EXPORT_PROFILE = 'template-streaming-xlsx-v3';

    private array $categoryPropertyNameCache = [];

    private array $categoryPropertyValueCache = [];

    public function index(Request $request, ProductSyncStateService $productSyncStateService)
    {
        $user = $request->user();

        $query = Product::with('shop:id,name,logistics_route,logistics_template_id,logistics_template_name')
            ->orderBy('category_id')
            ->orderBy('ae_item_id');

        $this->applyPermissionScope($query, $user);

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }
        if ($request->filled('keyword')) {
            $kw = '%' . $request->keyword . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('title_en', 'like', $kw)
                  ->orWhere('title_ru', 'like', $kw)
                  ->orWhere('ae_item_id', 'like', $kw);
            });
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('status_type')) {
            $query->where('status_type', $request->status_type);
        }

        $page  = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 20);

        $total = $query->count();
        $items = (clone $query)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $syncStates = $productSyncStateService->getStates($items->pluck('shop_id')->all());
        $selectedShopSyncState = $request->filled('shop_id')
            ? ($syncStates[(int) $request->shop_id] ?? $productSyncStateService->getState((int) $request->shop_id))
            : null;

        $items = $items->map(function ($p) use ($syncStates) {
                $data = $p->toArray();
                if (empty($data['main_image_url'])) {
                    $data['main_image_url'] = $this->extractProductImages($p)[0] ?? '';
                }
                $data['sku_count'] = is_array($p->skus) ? count($p->skus) : 0;
                $data['sync_queue'] = $syncStates[(int) $p->shop_id] ?? null;
                unset($data['raw_data'], $data['skus'], $data['properties']);
                return $data;
            });

        return $this->success([
            'total' => $total,
            'items' => $items,
            'sync_queue' => $selectedShopSyncState,
        ]);
    }

    public function export(Request $request)
    {
        $user = $request->user();
        $options = $this->extractExportOptions($request);

        $existingTask = ProductExportTask::query()
            ->where('operator_user_id', $user->id)
            ->whereIn('status', ['pending', 'running'])
            ->orderByDesc('id')
            ->first();

        if ($existingTask) {
            return $this->success($this->transformExportTask($existingTask, $options), '已有导出任务正在执行，已返回当前任务');
        }

        $currentTotalRows = (clone $this->buildExportQuery($user, $options))->count();
        $reusableTask = $this->findReusableCompletedTask($user, $options, $currentTotalRows);
        if ($reusableTask) {
            return $this->success(
                $this->transformExportTask($reusableTask, $options, $currentTotalRows),
                '当前商品数据未变化，已返回已有导出文件'
            );
        }

        $task = ProductExportTask::create([
            'operator_user_id' => $user->id,
            'trigger_type' => 'manual',
            'status' => 'pending',
            'format' => 'xlsx',
            'options' => $options,
            'message' => '导出任务已创建，等待执行',
        ]);

        try {
            RunProductExportTask::dispatch($task->id)->onQueue(RunProductExportTask::QUEUE_NAME);
        } catch (\Throwable $e) {
            $task->update([
                'status' => 'failed',
                'progress' => 100,
                'message' => '导出任务启动失败: ' . $e->getMessage(),
                'finished_at' => now(),
            ]);

            return $this->error('导出任务启动失败: ' . $e->getMessage());
        }

        return $this->success($this->transformExportTask($task, $options, $currentTotalRows), '导出任务已启动');
    }

    public function exportHistory(Request $request)
    {
        $user = $request->user();
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(50, max(1, (int) $request->input('limit', 10)));
        $options = $this->extractExportOptions($request);
        $currentTotalRows = (clone $this->buildExportQuery($user, $options))->count();

        $historyQuery = ProductExportTask::query()
            ->where('operator_user_id', $user->id)
            ->orderByDesc('id');

        $total = (clone $historyQuery)->count();
        $items = $historyQuery
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function (ProductExportTask $task) use ($options, $currentTotalRows) {
                return $this->transformExportTask($task, $options, $currentTotalRows);
            })
            ->values();

        return $this->success([
            'total' => $total,
            'items' => $items,
            'current_total_rows' => $currentTotalRows,
        ]);
    }

    public function exportProgress(Request $request)
    {
        $request->validate([
            'task_id' => 'nullable|integer|exists:product_export_tasks,id',
        ]);

        $task = $this->findAccessibleExportTask($request, $request->input('task_id'));
        if (!$task) {
            return $this->error('未找到导出任务');
        }

        return $this->success($task);
    }

    public function downloadExport(Request $request)
    {
        $request->validate([
            'task_id' => 'required|integer|exists:product_export_tasks,id',
        ]);

        $task = $this->findAccessibleExportTask($request, (int) $request->input('task_id'));
        if (!$task) {
            return $this->error('未找到导出任务');
        }

        if ($task->status !== 'completed' || !$task->file_path) {
            return $this->error('导出任务尚未完成');
        }

        $absolutePath = Storage::disk('local')->path(ltrim((string) $task->file_path, '/'));
        if (!is_file($absolutePath)) {
            return $this->error('导出文件不存在或已失效');
        }

        return response()->download(
            $absolutePath,
            $task->file_name ?: basename($absolutePath),
            [
                'Content-Type' => $task->mime_type ?: 'application/octet-stream',
            ]
        );
    }

    public function deleteExport(Request $request)
    {
        $request->validate([
            'task_id' => 'required|integer|exists:product_export_tasks,id',
        ]);

        $task = $this->findAccessibleExportTask($request, (int) $request->input('task_id'));
        if (!$task) {
            return $this->error('未找到导出任务');
        }

        if (in_array($task->status, ['pending', 'running'], true)) {
            return $this->error('执行中的导出任务暂不支持删除');
        }

        Storage::disk('local')->deleteDirectory(sprintf('exports/product-exports/task-%d', $task->id));
        $task->delete();

        return $this->success(null, '导出记录及文件已删除');
    }

    private function productExportHeaderRows(): array
    {
        return [
            [
                '', '', '', '', '', '', '', '',
                'Product image', '', '', '', '', '',
                'Prices and stocks', '', '',
                'Detailed description', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                'Any seller’s attributes', '', '', '', '',
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
        $row[36] = $this->extractPropertyValue($product, ['shoe size (cn)', 'shoe size', 'size (cn)']);
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

    public function syncShop(Request $request, ProductSyncStateService $productSyncStateService)
    {
        $request->validate(['shop_id' => 'required|exists:shops,id']);

        $shop = Shop::findOrFail($request->shop_id);
        if (!$shop->access_token) {
            return $this->error('该店铺未配置 access_token，无法同步商品');
        }

        $syncState = $productSyncStateService->dispatchShopSync($shop);

        $message = $productSyncStateService->isActive($syncState)
            ? ((string) ($syncState['message'] ?? '商品同步任务已在队列中'))
            : '商品同步任务已提交，同步完成后刷新列表即可';

        return $this->success(['sync_queue' => $syncState], $message);
    }

    private function extractExportOptions(Request $request): array
    {
        return $this->normalizeExportOptions([
            'shop_id' => $request->input('shop_id'),
            'keyword' => $request->input('keyword', ''),
            'category_id' => $request->input('category_id'),
            'status_type' => $request->input('status_type'),
        ]);
    }

    private function findAccessibleExportTask(Request $request, ?int $taskId = null): ?ProductExportTask
    {
        $user = $request->user();

        $query = ProductExportTask::query()->orderByDesc('id');
        if (!$user->hasRole('super-admin')) {
            $query->where('operator_user_id', $user->id);
        }
        if ($taskId) {
            $query->whereKey($taskId);
        }

        return $query->first();
    }

    private function findReusableCompletedTask($user, array $options, int $currentTotalRows): ?ProductExportTask
    {
        $normalizedOptions = $this->normalizeExportOptions($options);

        return ProductExportTask::query()
            ->where('operator_user_id', $user->id)
            ->where('status', 'completed')
            ->where('total_rows', $currentTotalRows)
            ->orderByDesc('id')
            ->get()
            ->first(function (ProductExportTask $task) use ($normalizedOptions) {
                return $this->normalizeExportOptions((array) ($task->options ?? [])) === $normalizedOptions
                    && $this->usesCurrentExportProfile($task)
                    && $this->exportFileExists($task);
            });
    }

    private function transformExportTask(ProductExportTask $task, array $currentOptions = [], ?int $currentTotalRows = null): array
    {
        $normalizedCurrentOptions = $this->normalizeExportOptions($currentOptions);
        $taskOptions = $this->normalizeExportOptions((array) ($task->options ?? []));
        $fileExists = $this->exportFileExists($task);
        $matchesCurrentScope = $taskOptions === $normalizedCurrentOptions;

        return array_merge($task->toArray(), [
            'options' => $taskOptions,
            'file_exists' => $fileExists,
            'exported_rows' => $task->total_rows !== null ? (int) $task->total_rows : (int) $task->processed_rows,
            'exported_at' => optional($task->finished_at ?: $task->created_at)->format('Y-m-d H:i:s'),
            'matches_current_scope' => $matchesCurrentScope,
            'can_reuse' => $currentTotalRows !== null
                && $matchesCurrentScope
                && $this->usesCurrentExportProfile($task)
                && $task->status === 'completed'
                && $fileExists
                && (int) $task->total_rows === $currentTotalRows,
        ]);
    }

    private function usesCurrentExportProfile(ProductExportTask $task): bool
    {
        return (string) ((is_array($task->details) ? $task->details : [])['export_profile'] ?? '') === self::EXPORT_PROFILE;
    }

    private function buildExportQuery($user, array $options)
    {
        $normalizedOptions = $this->normalizeExportOptions($options);
        $query = Product::query();

        $this->applyPermissionScope($query, $user);

        if ($normalizedOptions['shop_id'] !== null) {
            $query->where('shop_id', $normalizedOptions['shop_id']);
        }
        if ($normalizedOptions['keyword'] !== '') {
            $kw = '%' . $normalizedOptions['keyword'] . '%';
            $query->where(function ($innerQuery) use ($kw) {
                $innerQuery->where('title_en', 'like', $kw)
                    ->orWhere('title_ru', 'like', $kw)
                    ->orWhere('ae_item_id', 'like', $kw);
            });
        }
        if ($normalizedOptions['category_id'] !== null) {
            $query->where('category_id', $normalizedOptions['category_id']);
        }
        if ($normalizedOptions['status_type'] !== '') {
            $query->where('status_type', $normalizedOptions['status_type']);
        }

        return $query;
    }

    private function normalizeExportOptions(array $options): array
    {
        $shopId = $options['shop_id'] ?? null;
        $categoryId = $options['category_id'] ?? null;
        $statusType = trim((string) ($options['status_type'] ?? ''));

        return [
            'shop_id' => $shopId === '' || $shopId === null ? null : (int) $shopId,
            'keyword' => trim((string) ($options['keyword'] ?? '')),
            'category_id' => $categoryId === '' || $categoryId === null ? null : (string) $categoryId,
            'status_type' => $statusType,
        ];
    }

    private function exportFileExists(ProductExportTask $task): bool
    {
        if (!$task->file_path) {
            return false;
        }

        return Storage::disk('local')->exists(ltrim((string) $task->file_path, '/'));
    }

    private function applyPermissionScope($query, $user): void
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

    private function isTeamAdmin($user): bool
    {
        return Team::where('admin_user_id', $user->id)->exists();
    }
}

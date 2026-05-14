<?php

namespace App\Console\Commands;

use App\Jobs\RunShopProductsSyncJob;
use App\Models\Order;
use App\Models\Product;
use App\Services\AliExpressService;
use Illuminate\Console\Command;

class EnrichSkuAttributes extends Command
{
    protected $signature = 'orders:enrich-sku {--limit=100} {--force}';
    protected $description = '补充订单商品的SKU变体属性（尺码、颜色等）';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');

        $query = Order::with(['items', 'shop'])
            ->whereHas('shop', fn($q) => $q->whereNotNull('access_token'));

        if (!$force) {
            $query->whereHas('items', fn($q) => $q->whereNull('sku_attributes'));
        }

        $orders = $query->limit($limit)->get();
        $total = $orders->count();
        $this->info("Found {$total} orders to enrich.");

        $service = new AliExpressService();

        // 按店铺分组收集产品
        $shopProducts = []; // [shop_id => ['shop' => Shop, 'product_ids' => [...]]]
        foreach ($orders as $order) {
            if (!$order->shop) continue;
            $sid = $order->shop->id;
            if (!isset($shopProducts[$sid])) {
                $shopProducts[$sid] = ['shop' => $order->shop, 'product_ids' => []];
            }
            foreach ($order->items as $item) {
                if ($item->ae_item_id) {
                    $shopProducts[$sid]['product_ids'][] = (string) $item->ae_item_id;
                }
            }
        }

        $totalProducts = 0;
        $dispatched = 0;
        $readyProducts = [];

        foreach ($shopProducts as $sid => $data) {
            $unique = array_unique($data['product_ids']);
            $data['product_ids'] = array_values($unique);
            $totalProducts += count($unique);

            // 拆分为已有数据和缺失数据
            $existing = Product::where('shop_id', $sid)
                ->whereIn('ae_item_id', $unique)
                ->whereNotNull('skus')
                ->pluck('ae_item_id')
                ->toArray();

            $existingMap = array_flip($existing);
            $missing = array_values(array_filter($unique, fn($id) => !isset($existingMap[$id])));

            if (!empty($missing)) {
                RunShopProductsSyncJob::dispatch($sid, $missing)->onQueue('products');
                $dispatched++;
            }

            if (!empty($existing)) {
                $readyProducts[] = ['shop' => $data['shop'], 'product_ids' => $existing];
            }
        }

        $this->info("Unique products: {$totalProducts}");

        if ($dispatched > 0) {
            $this->info("Dispatched {$dispatched} shop-level sync jobs to queue.");
        }

        // 批量同步已有产品的分类
        if (!empty($readyProducts)) {
            $allCategoryIds = [];
            foreach ($readyProducts as $group) {
                $cids = Product::where('shop_id', $group['shop']->id)
                    ->whereIn('ae_item_id', $group['product_ids'])
                    ->whereNotNull('category_id')
                    ->where('category_id', '!=', '')
                    ->pluck('category_id')
                    ->all();
                $allCategoryIds = array_merge($allCategoryIds, $cids);
            }
            $allCategoryIds = array_unique($allCategoryIds);

            if (!empty($allCategoryIds)) {
                $firstShop = $orders->first()->shop ?? null;
                if ($firstShop && $firstShop->access_token) {
                    $this->info('Batch-syncing ' . count($allCategoryIds) . ' categories...');
                    $synced = $service->batchSyncCategories($firstShop->access_token, $allCategoryIds);
                    $this->info("Categories synced: {$synced}");
                }
            }
        }

        // 补充已有产品的订单（job会处理缺失产品的订单）
        $this->info('Enriching orders with existing product data...');
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%");

        $totalEnriched = 0;
        $startTime = microtime(true);

        foreach ($orders as $order) {
            if (!$order->shop) {
                $bar->advance();
                continue;
            }

            $enriched = $service->enrichOrderItemSkuAttributes($order->shop, $order, $force);
            $totalEnriched += $enriched;
            $bar->advance();
        }

        $bar->finish();
        $this->line('');

        $elapsed = round(microtime(true) - $startTime, 1);
        $this->info("Done. {$total} orders processed, {$totalEnriched} items enriched in {$elapsed}s.");

        if ($dispatched > 0) {
            $this->info("{$dispatched} shop jobs syncing products — run this command again after workers finish.");
        }

        return 0;
    }
}

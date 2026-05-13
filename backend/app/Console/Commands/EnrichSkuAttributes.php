<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
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

        // 收集所有商品ID，预同步缺失的产品数据
        $service = new AliExpressService();
        $productIds = collect();
        foreach ($orders as $order) {
            if (!$order->shop) continue;
            foreach ($order->items as $item) {
                if ($item->ae_item_id) {
                    $productIds->push([
                        'shop_id' => $order->shop->id,
                        'shop' => $order->shop,
                        'product_id' => (string) $item->ae_item_id,
                    ]);
                }
            }
        }

        $uniqueProductIds = $productIds->unique('product_id')->values();
        $this->info("Unique products across orders: {$uniqueProductIds->count()}");

        $missingIds = [];
        foreach ($uniqueProductIds as $p) {
            $exists = Product::where('shop_id', $p['shop_id'])
                ->where('ae_item_id', $p['product_id'])
                ->whereNotNull('skus')
                ->exists();
            if (!$exists) {
                $missingIds[] = $p;
            }
        }

        if (count($missingIds) > 0) {
            // 按店铺分组，每批10个并发请求
            $byShop = [];
            foreach ($missingIds as $p) {
                $byShop[$p['shop_id']][] = $p;
            }

            $this->info('Pre-fetching ' . count($missingIds) . ' missing products (10 concurrent)...');
            $syncBar = $this->output->createProgressBar(count($missingIds));
            $syncBar->setFormat(" %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%  %message%");

            foreach ($byShop as $shopId => $products) {
                $shop = $products[0]['shop'];
                $pids = array_column($products, 'product_id');
                $synced = 0;

                foreach (array_chunk($pids, 10) as $chunk) {
                    $syncBar->setMessage("Shop #{$shopId} (" . count($chunk) . ' concurrent)');
                    $service->batchGetProductDetails($shop, $chunk, 10);
                    $syncBar->advance(count($chunk));
                }
            }
            $syncBar->finish();
            $this->line('');
        }

        // 批量预同步类目数据（每批20个，大幅减少API调用）
        $allProductIds = $uniqueProductIds->pluck('product_id')->unique()->values()->toArray();
        if (!empty($allProductIds)) {
            $firstShop = $orders->first()->shop ?? null;
            if ($firstShop && $firstShop->access_token) {
                $categoryIds = Product::whereIn('ae_item_id', $allProductIds)
                    ->whereNotNull('category_id')
                    ->where('category_id', '!=', '')
                    ->pluck('category_id')
                    ->unique()
                    ->values()
                    ->toArray();

                if (!empty($categoryIds)) {
                    $this->info('Batch-syncing ' . count($categoryIds) . ' categories...');
                    $synced = $service->batchSyncCategories($firstShop->access_token, $categoryIds);
                    $this->info("Categories synced: {$synced}");
                }
            }
        }

        $this->info('Enriching SKU attributes...');
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%  %message%");

        $totalEnriched = 0;
        $startTime = microtime(true);

        foreach ($orders as $idx => $order) {
            $bar->setMessage("Order #{$order->ae_order_id}");

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

        return 0;
    }
}

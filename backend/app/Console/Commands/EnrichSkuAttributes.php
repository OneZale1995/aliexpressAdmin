<?php

namespace App\Console\Commands;

use App\Jobs\RunShopProductDetailSyncJob;
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

        // 收集所有商品ID → 缺失的dispatch到队列并行同步
        $productMap = []; // [product_id => ['shop' => Shop, 'shop_id' => int]]
        foreach ($orders as $order) {
            if (!$order->shop) continue;
            foreach ($order->items as $item) {
                if ($item->ae_item_id && !isset($productMap[$item->ae_item_id])) {
                    $productMap[$item->ae_item_id] = ['shop' => $order->shop, 'shop_id' => $order->shop->id];
                }
            }
        }

        $this->info('Unique products: ' . count($productMap));

        $dispatched = 0;
        $existingIds = [];
        foreach ($productMap as $productId => $info) {
            $exists = Product::where('shop_id', $info['shop_id'])
                ->where('ae_item_id', $productId)
                ->whereNotNull('skus')
                ->exists();
            if ($exists) {
                $existingIds[] = $productId;
            } else {
                RunShopProductDetailSyncJob::dispatch($info['shop_id'], $productId)->onQueue('products');
                $dispatched++;
            }
        }

        if ($dispatched > 0) {
            $this->info("Dispatched {$dispatched} product sync jobs to queue. Workers will enrich related orders on completion.");
        }

        // 批量预同步类目（每批20个，已有的跳过）
        if (!empty($existingIds)) {
            $firstShop = $orders->first()->shop ?? null;
            if ($firstShop && $firstShop->access_token) {
                $categoryIds = Product::whereIn('ae_item_id', $existingIds)
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

        $this->info('Enriching SKU attributes (items with local product data)...');
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
        $this->info("Done. {$total} orders processed, {$totalEnriched} items enriched now in {$elapsed}s.");

        if ($dispatched > 0) {
            $this->info("{$dispatched} products syncing via queue — run this command again after workers finish for full coverage.");
        }

        return 0;
    }
}

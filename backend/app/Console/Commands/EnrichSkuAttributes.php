<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AliExpressService;
use Illuminate\Console\Command;

class EnrichSkuAttributes extends Command
{
    protected $signature = 'orders:enrich-sku {--limit=100}';
    protected $description = '补充订单商品的SKU变体属性（尺码、颜色等）';

    public function handle()
    {
        $limit = (int) $this->option('limit');

        $orders = Order::with(['items', 'shop'])
            ->whereHas('items', fn($q) => $q->whereNull('sku_attributes'))
            ->whereHas('shop', fn($q) => $q->whereNotNull('access_token'))
            ->limit($limit)
            ->get();

        $this->info("Found {$orders->count()} orders to enrich.");

        $service = new AliExpressService();
        $totalEnriched = 0;

        foreach ($orders as $order) {
            if (!$order->shop) continue;

            $enriched = $service->enrichOrderItemSkuAttributes($order->shop, $order);
            $totalEnriched += $enriched;

            if ($enriched > 0) {
                $this->line("  Order {$order->ae_order_id}: enriched {$enriched} items");
            }
        }

        $this->info("Done. Enriched {$totalEnriched} items total.");
        return 0;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
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

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%  %message%");

        $service = new AliExpressService();
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

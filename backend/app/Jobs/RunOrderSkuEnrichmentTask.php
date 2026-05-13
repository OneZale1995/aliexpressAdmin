<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Shop;
use App\Services\AliExpressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class RunOrderSkuEnrichmentTask implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $shopId, public int $orderId)
    {
    }

    public function handle(AliExpressService $service): void
    {
        try {
            $order = Order::with('items')->find($this->orderId);
            if (!$order) {
                return;
            }

            $shop = Shop::find($this->shopId);
            if (!$shop) {
                return;
            }

            $shop->makeVisible('access_token');
            $service->enrichOrderItemSkuAttributes($shop, $order, false, false);
        } finally {
            Cache::forget($this->lockKey());
        }
    }

    public function failed(\Throwable $exception): void
    {
        Cache::forget($this->lockKey());
    }

    protected function lockKey(): string
    {
        return sprintf('ali:order-sku-enrich:lock:%d', $this->orderId);
    }
}

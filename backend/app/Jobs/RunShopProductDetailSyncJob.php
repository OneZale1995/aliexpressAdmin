<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Services\AliExpressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunShopProductDetailSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 15;

    public function __construct(public int $shopId, public string $productId)
    {
    }

    public function handle(AliExpressService $service): void
    {
        $shop = Shop::find($this->shopId);
        if (!$shop || !$shop->access_token) {
            return;
        }

        $service->getProductDetail($shop, $this->productId);

        $this->enrichRelatedOrders($shop, $service);
    }

    protected function enrichRelatedOrders(Shop $shop, AliExpressService $service): void
    {
        $orderIds = OrderItem::where('ae_item_id', $this->productId)
            ->whereNull('sku_attributes')
            ->pluck('order_id')
            ->unique()
            ->toArray();

        if (empty($orderIds)) {
            return;
        }

        $orders = Order::with('items')->whereIn('id', $orderIds)->get();
        foreach ($orders as $order) {
            $service->enrichOrderItemSkuAttributes($shop, $order);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('third_party')->error('Shop product detail sync failed', [
            'shop_id' => $this->shopId,
            'product_id' => $this->productId,
            'message' => $e->getMessage(),
        ]);
    }
}
<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Services\AliExpressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunShopProductsSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;
    public int $backoff = 30;

    public function __construct(public int $shopId, public array $productIds)
    {
    }

    public function handle(AliExpressService $service): void
    {
        $shop = Shop::find($this->shopId);
        if (!$shop || !$shop->access_token) {
            return;
        }

        // 1. 逐个拉取产品详情（跳过分类同步，快）
        foreach ($this->productIds as $productId) {
            $service->getProductDetail($shop, $productId, true);
        }

        // 2. 批量同步分类
        $categoryIds = Product::where('shop_id', $shop->id)
            ->whereIn('ae_item_id', $this->productIds)
            ->whereNotNull('category_id')
            ->where('category_id', '!=', '')
            ->pluck('category_id')
            ->unique()
            ->values()
            ->toArray();

        if (!empty($categoryIds)) {
            $service->batchSyncCategories($shop->access_token, $categoryIds);
        }

        // 3. 找到关联订单，每个订单只补充一次
        $orderIds = OrderItem::whereIn('ae_item_id', $this->productIds)
            ->whereNull('sku_attributes')
            ->pluck('order_id')
            ->unique()
            ->values()
            ->toArray();

        if (empty($orderIds)) {
            return;
        }

        $orders = Order::with('items')->whereIn('id', $orderIds)->get();
        foreach ($orders as $order) {
            $service->enrichOrderItemSkuAttributes($shop, $order, false, false);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('third_party')->error('Shop products sync failed', [
            'shop_id' => $this->shopId,
            'product_count' => count($this->productIds),
            'message' => $e->getMessage(),
        ]);
    }
}

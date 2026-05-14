<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\AliExpressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunShopProductSyncPageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 75;
    public int $backoff = 30;

    public function __construct(
        public int $shopId,
        public ?string $lastProductId = null,
        public int $limit = 50
    ) {
    }

    public function handle(AliExpressService $service): void
    {
        $shop = Shop::find($this->shopId);
        if (!$shop || !$shop->access_token) {
            Cache::forget(RunShopProductSyncJob::lockKey($this->shopId));

            return;
        }

        $result = $service->syncShopProductPage($shop, $this->lastProductId, $this->limit);
        if (!($result['success'] ?? false)) {
            throw new \RuntimeException((string) ($result['error'] ?? '商品分页同步失败'));
        }

        if (($result['has_more'] ?? false) && !empty($result['next_last_id'])) {
            self::dispatch($this->shopId, (string) $result['next_last_id'], $this->limit)
                ->onQueue($this->queue ?? 'default');
        }

        if (!empty($result['detail_product_ids'])) {
            RunShopProductsSyncJob::dispatch($this->shopId, array_map('strval', $result['detail_product_ids']))
                ->onQueue($this->queue ?? 'default');
        }

        if (!($result['has_more'] ?? false)) {
            Cache::forget(RunShopProductSyncJob::lockKey($this->shopId));

            Log::channel('third_party')->info('Shop product short sync completed', [
                'shop_id' => $this->shopId,
                'processed' => $result['processed'] ?? 0,
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Cache::forget(RunShopProductSyncJob::lockKey($this->shopId));

        Log::channel('third_party')->error('Shop product page sync failed', [
            'shop_id' => $this->shopId,
            'last_product_id' => $this->lastProductId,
            'message' => $e->getMessage(),
        ]);
    }
}
<?php

namespace App\Jobs;

use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunShopProductSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const LOCK_TTL_HOURS = 6;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(public int $shopId)
    {
    }

    public function handle(): void
    {
        $shop = Shop::find($this->shopId);
        if (!$shop || !$shop->access_token) {
            Cache::forget(self::lockKey($this->shopId));
            return;
        }

        if (!Cache::add(self::lockKey($this->shopId), 1, now()->addHours(self::LOCK_TTL_HOURS))) {
            Log::channel('third_party')->info('Shop product sync skipped because another sync is running', [
                'shop_id' => $this->shopId,
            ]);

            return;
        }

        try {
            RunShopProductSyncPageJob::dispatch($this->shopId)->onQueue($this->queue ?? 'default');

            Log::channel('third_party')->info('Shop product sync started', [
                'shop_id' => $this->shopId,
            ]);
        } catch (\Throwable $e) {
            Cache::forget(self::lockKey($this->shopId));

            Log::channel('third_party')->error('Shop product sync failed', [
                'shop_id' => $this->shopId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public static function lockKey(int $shopId): string
    {
        return sprintf('shop-product-sync:%d', $shopId);
    }
}

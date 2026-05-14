<?php

namespace App\Services;

use App\Jobs\RunShopProductSyncJob;
use App\Models\Shop;
use Illuminate\Support\Facades\Cache;

class ProductSyncStateService
{
    public const SYNC_QUEUE = 'products';

    private const ACTIVE_TTL_SECONDS = 21600;

    private const FINAL_TTL_SECONDS = 900;

    public function dispatchShopSync(Shop $shop): array
    {
        $state = $this->getState((int) $shop->id);
        if ($this->isActive($state)) {
            return $state;
        }

        if (!Cache::add($this->dispatchLockKey((int) $shop->id), 1, now()->addSeconds(self::ACTIVE_TTL_SECONDS))) {
            return $this->getState((int) $shop->id) ?? [
                'status' => 'pending',
                'shop_id' => (int) $shop->id,
                'shop_name' => (string) $shop->name,
                'queue' => self::SYNC_QUEUE,
                'message' => '商品同步任务已进入队列，等待执行',
            ];
        }

        $state = $this->putState((int) $shop->id, [
            'status' => 'pending',
            'shop_id' => (int) $shop->id,
            'shop_name' => (string) $shop->name,
            'queue' => self::SYNC_QUEUE,
            'message' => '商品同步任务已进入队列，等待执行',
            'queued_at' => now()->toDateTimeString(),
            'started_at' => null,
            'finished_at' => null,
        ], self::ACTIVE_TTL_SECONDS);

        try {
            RunShopProductSyncJob::dispatch((int) $shop->id)->onQueue(self::SYNC_QUEUE);

            return $state;
        } catch (\Throwable $e) {
            Cache::forget($this->dispatchLockKey((int) $shop->id));
            $this->markFailed((int) $shop->id, '商品同步任务提交失败: ' . $e->getMessage(), (string) $shop->name);

            throw $e;
        }
    }

    public function getState(int $shopId): ?array
    {
        $state = Cache::get($this->stateKey($shopId));
        if (!is_array($state)) {
            return null;
        }

        return $state;
    }

    public function getStates(array $shopIds): array
    {
        $states = [];
        foreach (array_unique(array_map('intval', $shopIds)) as $shopId) {
            if ($shopId <= 0) {
                continue;
            }

            $state = $this->getState($shopId);
            if ($state) {
                $states[$shopId] = $state;
            }
        }

        return $states;
    }

    public function isActive(?array $state): bool
    {
        if (!$state) {
            return false;
        }

        return in_array((string) ($state['status'] ?? ''), ['pending', 'running'], true);
    }

    public function markRunning(int $shopId, ?string $shopName = null, ?string $message = null): array
    {
        $existing = $this->getState($shopId) ?? [];

        return $this->putState($shopId, array_merge($existing, [
            'status' => 'running',
            'shop_id' => $shopId,
            'shop_name' => $shopName ?? (string) ($existing['shop_name'] ?? ''),
            'queue' => self::SYNC_QUEUE,
            'message' => $message ?: '商品同步中',
            'queued_at' => $existing['queued_at'] ?? now()->toDateTimeString(),
            'started_at' => now()->toDateTimeString(),
            'finished_at' => null,
        ]), self::ACTIVE_TTL_SECONDS);
    }

    public function markCompleted(int $shopId, ?string $shopName = null, ?string $message = null): array
    {
        $existing = $this->getState($shopId) ?? [];
        Cache::forget($this->dispatchLockKey($shopId));

        return $this->putState($shopId, array_merge($existing, [
            'status' => 'completed',
            'shop_id' => $shopId,
            'shop_name' => $shopName ?? (string) ($existing['shop_name'] ?? ''),
            'queue' => self::SYNC_QUEUE,
            'message' => $message ?: '商品同步已完成',
            'finished_at' => now()->toDateTimeString(),
        ]), self::FINAL_TTL_SECONDS);
    }

    public function markFailed(int $shopId, string $message, ?string $shopName = null): array
    {
        $existing = $this->getState($shopId) ?? [];
        Cache::forget($this->dispatchLockKey($shopId));

        return $this->putState($shopId, array_merge($existing, [
            'status' => 'failed',
            'shop_id' => $shopId,
            'shop_name' => $shopName ?? (string) ($existing['shop_name'] ?? ''),
            'queue' => self::SYNC_QUEUE,
            'message' => $message,
            'finished_at' => now()->toDateTimeString(),
        ]), self::FINAL_TTL_SECONDS);
    }

    private function putState(int $shopId, array $state, int $ttlSeconds): array
    {
        Cache::put($this->stateKey($shopId), $state, now()->addSeconds($ttlSeconds));

        return $state;
    }

    private function stateKey(int $shopId): string
    {
        return sprintf('shop-product-sync:state:%d', $shopId);
    }

    private function dispatchLockKey(int $shopId): string
    {
        return sprintf('shop-product-sync:dispatch:%d', $shopId);
    }
}
<?php

namespace App\Services;

use App\Jobs\RunOrderSyncShopTask;
use App\Models\OrderSyncTask;
use App\Models\Shop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderSyncTaskService
{
    private const SHOP_RUNNING_TTL_SECONDS = 1800;

    private const SHOP_RECENT_TTL_SECONDS = 1800;

    private const TERMINAL_DETAIL_STATUSES = ['completed', 'failed', 'skipped'];

    public function execute(OrderSyncTask $task): array
    {
        if ($task->status === 'running') {
            return [
                'success' => true,
                'message' => '同步任务已在执行',
                'skipped' => true,
            ];
        }

        if ($task->status !== 'pending') {
            return [
                'success' => $task->status === 'completed',
                'message' => '任务状态非可执行: ' . $task->status,
                'skipped' => true,
            ];
        }

        try {
            return $this->dispatchShopTasks($task);
        } catch (\Throwable $e) {
            $this->markTaskFailed($task->id, $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'skipped' => false,
            ];
        }
    }

    public function executeShopTask(int $taskId, int $shopId): array
    {
        $task = OrderSyncTask::find($taskId);
        if (!$task) {
            return [
                'success' => false,
                'message' => '同步任务不存在',
                'skipped' => true,
            ];
        }

        if (!in_array($task->status, ['pending', 'running'], true)) {
            return [
                'success' => $task->status === 'completed',
                'message' => '任务状态非可执行: ' . $task->status,
                'skipped' => true,
            ];
        }

        $shop = Shop::find($shopId);
        $shopName = $shop?->name ?: ('店铺#' . $shopId);

        if (!$shop) {
            return $this->completeShopTask($taskId, $shopId, $shopName, 0, '店铺不存在', 'failed');
        }

        $options = is_array($task->options) ? $task->options : [];
        $signature = $this->buildShopSyncSignature($shopId, $options);
        $runningKey = $this->shopRunningKey($signature);

        if (!Cache::add($runningKey, now()->toDateTimeString(), now()->addSeconds(self::SHOP_RUNNING_TTL_SECONDS))) {
            return $this->completeShopTask($taskId, $shopId, $shopName, 0, '店铺正在同步，已跳过', 'skipped');
        }

        try {
            if ($this->wasShopSyncedRecently($signature)) {
                return $this->completeShopTask($taskId, $shopId, $shopName, 0, '最近30分钟内已同步，已跳过', 'skipped');
            }

            $this->markShopRunning($taskId, $shopName);

            $shop->makeVisible('access_token');
            $result = (new AliExpressService())->syncOrders($shop, $this->buildSyncParams($options));
            $status = !empty($result['error']) ? 'failed' : 'completed';

            if ($status === 'completed') {
                $this->markShopSyncedRecently($signature);
            }

            return $this->completeShopTask(
                $taskId,
                $shopId,
                $shopName,
                (int) ($result['synced'] ?? 0),
                $result['error'] ?? null,
                $status
            );
        } catch (\Throwable $e) {
            return $this->completeShopTask($taskId, $shopId, $shopName, 0, $e->getMessage(), 'failed');
        } finally {
            Cache::forget($runningKey);
        }
    }

    private function dispatchShopTasks(OrderSyncTask $task): array
    {
        $options = is_array($task->options) ? $task->options : [];
        $shopIds = $options['shop_ids'] ?? [];
        $shops = Shop::whereIn('id', $shopIds)
            ->get(['id', 'name']);

        if ($shops->isEmpty()) {
            $this->markTaskFailed($task->id, '没有可同步店铺');

            return [
                'success' => false,
                'message' => '没有可同步店铺',
                'skipped' => false,
            ];
        }

        $result = DB::transaction(function () use ($task, $shops) {
            $lockedTask = OrderSyncTask::query()->lockForUpdate()->find($task->id);

            if (!$lockedTask) {
                return [
                    'success' => false,
                    'message' => '同步任务不存在',
                    'skipped' => true,
                ];
            }

            if ($lockedTask->status === 'running') {
                return [
                    'success' => true,
                    'message' => '同步任务已在执行',
                    'skipped' => true,
                ];
            }

            if ($lockedTask->status !== 'pending') {
                return [
                    'success' => $lockedTask->status === 'completed',
                    'message' => '任务状态非可执行: ' . $lockedTask->status,
                    'skipped' => true,
                ];
            }

            $lockedTask->fill([
                'status' => 'running',
                'started_at' => $lockedTask->started_at ?: now(),
                'total_shops' => $shops->count(),
                'processed_shops' => 0,
                'failed_shops' => 0,
                'synced_orders' => 0,
                'progress' => 0,
                'current_shop_name' => '等待店铺子任务执行',
                'message' => '同步中',
                'details' => [],
                'finished_at' => null,
            ]);
            $lockedTask->save();

            return [
                'success' => true,
                'message' => '同步任务已拆分为店铺子任务并加入队列',
                'skipped' => false,
            ];
        });

        if (!empty($result['skipped']) || empty($result['success'])) {
            return $result;
        }

        foreach ($shops as $shop) {
            RunOrderSyncShopTask::dispatch($task->id, (int) $shop->id);
        }

        return [
            'success' => true,
            'message' => '同步任务已拆分为店铺子任务并加入队列',
            'skipped' => false,
        ];
    }

    private function markTaskFailed(int $taskId, string $message): void
    {
        OrderSyncTask::whereKey($taskId)->update([
            'status' => 'failed',
            'progress' => 100,
            'current_shop_name' => null,
            'message' => $message,
            'finished_at' => now(),
        ]);
    }

    private function markShopRunning(int $taskId, string $shopName): void
    {
        DB::transaction(function () use ($taskId, $shopName) {
            $task = OrderSyncTask::query()->lockForUpdate()->find($taskId);
            if (!$task || in_array($task->status, ['completed', 'failed'], true)) {
                return;
            }

            $task->fill([
                'status' => 'running',
                'started_at' => $task->started_at ?: now(),
                'current_shop_name' => $shopName,
                'message' => '同步中',
            ]);
            $task->save();
        });
    }

    private function completeShopTask(
        int $taskId,
        int $shopId,
        string $shopName,
        int $synced,
        ?string $error,
        string $status
    ): array {
        return DB::transaction(function () use ($taskId, $shopId, $shopName, $synced, $error, $status) {
            $task = OrderSyncTask::query()->lockForUpdate()->find($taskId);

            if (!$task) {
                return [
                    'success' => false,
                    'message' => '同步任务不存在',
                    'skipped' => true,
                ];
            }

            $details = is_array($task->details) ? $task->details : [];
            $detailIndex = $this->findDetailIndex($details, $shopId);

            if ($detailIndex !== null) {
                $existingStatus = (string) ($details[$detailIndex]['status'] ?? '');
                if ($this->isTerminalDetailStatus($existingStatus)) {
                    return [
                        'success' => $existingStatus !== 'failed',
                        'message' => '店铺子任务已处理',
                        'skipped' => true,
                    ];
                }
            }

            $now = now()->toDateTimeString();
            $details[$detailIndex ?? count($details)] = [
                'shop_id' => $shopId,
                'shop_name' => $shopName,
                'synced' => $synced,
                'error' => $error,
                'status' => $status,
                'started_at' => $detailIndex !== null ? ($details[$detailIndex]['started_at'] ?? $now) : $now,
                'finished_at' => $now,
            ];

            $processed = count(array_filter($details, fn(array $detail) => $this->isTerminalDetailStatus((string) ($detail['status'] ?? ''))));
            $failed = count(array_filter($details, fn(array $detail) => (string) ($detail['status'] ?? '') === 'failed'));
            $skipped = count(array_filter($details, fn(array $detail) => (string) ($detail['status'] ?? '') === 'skipped'));
            $syncedOrders = array_sum(array_map(fn(array $detail) => (int) ($detail['synced'] ?? 0), $details));
            $totalShops = max(1, (int) $task->total_shops);
            $allProcessed = $processed >= (int) $task->total_shops && (int) $task->total_shops > 0;

            $taskStatus = $allProcessed && $failed === (int) $task->total_shops ? 'failed' : ($allProcessed ? 'completed' : 'running');
            $message = $allProcessed
                ? $this->resolveTaskCompletionMessage((int) $task->total_shops, $failed, $skipped)
                : '同步中';

            $task->fill([
                'status' => $taskStatus,
                'processed_shops' => $processed,
                'failed_shops' => $failed,
                'synced_orders' => $syncedOrders,
                'progress' => (int) floor(($processed / $totalShops) * 100),
                'current_shop_name' => $allProcessed ? null : '并发同步中',
                'message' => $message,
                'details' => array_values($details),
                'finished_at' => $allProcessed ? now() : null,
            ]);
            $task->save();

            return [
                'success' => $status !== 'failed',
                'message' => $error ?: 'ok',
                'skipped' => false,
            ];
        });
    }

    private function buildSyncParams(array $options): array
    {
        $params = [];

        if (!empty($options['date_start'])) {
            $params['date_start'] = $options['date_start'] . 'T00:00:00Z';
        }

        if (!empty($options['date_end'])) {
            $params['date_end'] = $options['date_end'] . 'T23:59:59Z';
        }

        return $params;
    }

    private function buildShopSyncSignature(int $shopId, array $options): string
    {
        return md5(json_encode([
            'shop_id' => $shopId,
            'date_start' => $options['date_start'] ?? null,
            'date_end' => $options['date_end'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
    }

    private function shopRunningKey(string $signature): string
    {
        return 'order_sync_shop:running:' . $signature;
    }

    private function shopRecentKey(string $signature): string
    {
        return 'order_sync_shop:recent:' . $signature;
    }

    private function wasShopSyncedRecently(string $signature): bool
    {
        return Cache::has($this->shopRecentKey($signature));
    }

    private function markShopSyncedRecently(string $signature): void
    {
        Cache::put(
            $this->shopRecentKey($signature),
            now()->toDateTimeString(),
            now()->addSeconds(self::SHOP_RECENT_TTL_SECONDS)
        );
    }

    private function findDetailIndex(array $details, int $shopId): ?int
    {
        foreach ($details as $index => $detail) {
            if ((int) ($detail['shop_id'] ?? 0) === $shopId) {
                return $index;
            }
        }

        return null;
    }

    private function isTerminalDetailStatus(string $status): bool
    {
        return in_array($status, self::TERMINAL_DETAIL_STATUSES, true);
    }

    private function resolveTaskCompletionMessage(int $totalShops, int $failed, int $skipped): string
    {
        if ($failed >= $totalShops && $totalShops > 0) {
            return '同步失败';
        }

        if ($failed > 0 && $skipped > 0) {
            return '同步完成，部分店铺失败，部分店铺已跳过';
        }

        if ($failed > 0) {
            return '同步完成，部分店铺失败';
        }

        if ($skipped >= $totalShops && $totalShops > 0) {
            return '同步完成，全部店铺已跳过';
        }

        if ($skipped > 0) {
            return '同步完成，部分店铺已跳过';
        }

        return '同步完成';
    }
}
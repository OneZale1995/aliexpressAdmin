<?php

namespace App\Services;

use App\Models\OrderSyncTask;
use App\Models\Shop;

class OrderSyncTaskService
{
    public function execute(OrderSyncTask $task): array
    {
        if (!in_array($task->status, ['pending', 'running'], true)) {
            return [
                'success' => $task->status === 'completed',
                'message' => '任务状态非可执行: ' . $task->status,
                'skipped' => true,
            ];
        }

        try {
            return $this->runTask($task);
        } catch (\Throwable $e) {
            $task->update([
                'status' => 'failed',
                'progress' => 100,
                'current_shop_name' => null,
                'message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'skipped' => false,
            ];
        }
    }

    private function runTask(OrderSyncTask $task): array
    {
        $options = $task->options ?? [];
        $shopIds = $options['shop_ids'] ?? [];
        $dateStart = $options['date_start'] ?? null;
        $dateEnd = $options['date_end'] ?? null;

        $shops = Shop::whereIn('id', $shopIds)->get();
        if ($shops->isEmpty()) {
            $task->update([
                'status' => 'failed',
                'message' => '没有可同步店铺',
                'finished_at' => now(),
                'progress' => 100,
            ]);

            return [
                'success' => false,
                'message' => '没有可同步店铺',
                'skipped' => false,
            ];
        }

        $task->update([
            'status' => 'running',
            'started_at' => $task->started_at ?: now(),
            'total_shops' => $shops->count(),
            'processed_shops' => 0,
            'failed_shops' => 0,
            'synced_orders' => 0,
            'progress' => 0,
            'message' => '同步中',
            'details' => [],
        ]);

        $service = new AliExpressService();
        $processed = 0;
        $failed = 0;
        $synced = 0;
        $details = [];

        foreach ($shops as $shop) {
            $task->update(['current_shop_name' => $shop->name]);

            $shop->makeVisible('access_token');
            $params = [];
            if ($dateStart) {
                $params['date_start'] = $dateStart . 'T00:00:00Z';
            }
            if ($dateEnd) {
                $params['date_end'] = $dateEnd . 'T23:59:59Z';
            }

            $result = $service->syncOrders($shop, $params);
            $processed++;
            $synced += (int) ($result['synced'] ?? 0);
            if (!empty($result['error'])) {
                $failed++;
            }

            $details[] = [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'synced' => (int) ($result['synced'] ?? 0),
                'error' => $result['error'] ?? null,
            ];

            $task->update([
                'processed_shops' => $processed,
                'failed_shops' => $failed,
                'synced_orders' => $synced,
                'progress' => (int) floor(($processed / max(1, $shops->count())) * 100),
                'details' => $details,
            ]);
        }

        $allFailed = $failed === $shops->count();
        $message = $allFailed
            ? '同步失败'
            : ($failed > 0 ? '同步完成，部分店铺失败' : '同步完成');

        $task->update([
            'status' => $allFailed ? 'failed' : 'completed',
            'progress' => 100,
            'current_shop_name' => null,
            'message' => $message,
            'finished_at' => now(),
        ]);

        return [
            'success' => !$allFailed,
            'message' => $message,
            'skipped' => false,
        ];
    }
}
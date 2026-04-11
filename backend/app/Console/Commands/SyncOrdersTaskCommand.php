<?php

namespace App\Console\Commands;

use App\Models\OrderSyncTask;
use App\Models\Shop;
use App\Services\AliExpressService;
use Illuminate\Console\Command;

class SyncOrdersTaskCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:sync-task {taskId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '执行订单同步任务并更新进度';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $taskId = (int) $this->argument('taskId');
        $task = OrderSyncTask::find($taskId);

        if (!$task) {
            $this->error("任务不存在: {$taskId}");
            return self::FAILURE;
        }

        if (!in_array($task->status, ['pending', 'running'], true)) {
            $this->warn("任务状态非可执行: {$task->status}");
            return self::SUCCESS;
        }

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
            return self::FAILURE;
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

        $allFailed = ($failed === $shops->count());
        $task->update([
            'status' => $allFailed ? 'failed' : 'completed',
            'progress' => 100,
            'current_shop_name' => null,
            'message' => $allFailed ? '同步失败' : '同步完成',
            'finished_at' => now(),
        ]);

        return $allFailed ? self::FAILURE : self::SUCCESS;
    }
}

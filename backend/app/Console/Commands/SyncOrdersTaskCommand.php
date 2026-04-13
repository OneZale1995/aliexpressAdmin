<?php

namespace App\Console\Commands;

use App\Models\OrderSyncTask;
use App\Services\OrderSyncTaskService;
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

        $result = (new OrderSyncTaskService())->execute($task);

        if (!empty($result['skipped'])) {
            $this->warn($result['message']);
            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        if ($result['success']) {
            $this->info($result['message']);
            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}

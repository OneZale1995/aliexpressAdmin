<?php

namespace App\Jobs;

use App\Models\OrderSyncTask;
use App\Services\OrderSyncTaskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunOrderSyncTask implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $taskId)
    {
    }

    public function handle(OrderSyncTaskService $service): void
    {
        $task = OrderSyncTask::find($this->taskId);
        if (!$task) {
            return;
        }

        $service->execute($task);
    }
}
<?php

namespace App\Jobs;

use App\Models\ProductExportTask;
use App\Services\ProductExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunProductExportTask implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const QUEUE_NAME = 'product-exports';

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $taskId)
    {
    }

    public function handle(ProductExportService $service): void
    {
        ini_set('memory_limit', '512M');

        $service->processTask($this->taskId);
    }

    public function failed(\Throwable $e): void
    {
        ProductExportTask::whereKey($this->taskId)->update([
            'status' => 'failed',
            'progress' => 100,
            'message' => '导出任务失败: ' . $e->getMessage(),
            'finished_at' => now(),
        ]);

        Log::error('Product export task failed', [
            'task_id' => $this->taskId,
            'message' => $e->getMessage(),
        ]);
    }
}
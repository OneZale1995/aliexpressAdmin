<?php

namespace App\Jobs;

use App\Services\OrderSyncTaskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunOrderSyncShopTask implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public int $taskId, public int $shopId)
    {
    }

    public function handle(OrderSyncTaskService $service): void
    {
        $service->executeShopTask($this->taskId, $this->shopId);
    }

    public function failed(\Throwable $exception): void
    {
        app(OrderSyncTaskService::class)->markShopTaskFailedFromQueue(
            $this->taskId,
            $this->shopId,
            $exception->getMessage()
        );
    }
}
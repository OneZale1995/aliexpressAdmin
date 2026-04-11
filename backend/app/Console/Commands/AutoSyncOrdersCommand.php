<?php

namespace App\Console\Commands;

use App\Models\OrderSyncTask;
use App\Models\Shop;
use Illuminate\Console\Command;

class AutoSyncOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:auto-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动同步全部店铺订单';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shops = Shop::whereNotNull('access_token')
            ->where('access_token', '!=', '')
            ->pluck('id')
            ->toArray();

        if (empty($shops)) {
            $this->warn('未找到可自动同步店铺');
            return self::SUCCESS;
        }

        $task = OrderSyncTask::create([
            'operator_user_id' => null,
            'trigger_type' => 'auto',
            'status' => 'pending',
            'total_shops' => count($shops),
            'options' => [
                'shop_ids' => $shops,
                'date_start' => null,
                'date_end' => null,
            ],
            'message' => '自动同步任务已创建',
        ]);

        $this->call('order:sync-task', ['taskId' => $task->id]);

        return self::SUCCESS;
    }
}

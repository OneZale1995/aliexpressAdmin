<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_logistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->boolean('is_primary')->default(true)->comment('是否主物流记录');
            $table->string('logistics_mode', 20)->nullable()->comment('FBS/DBS');
            $table->string('provider_code', 50)->nullable()->comment('物流渠道编码');
            $table->string('provider_name', 100)->nullable()->comment('物流渠道名称');
            $table->string('template_code', 50)->nullable()->comment('物流模板/方案编码');
            $table->string('external_order_id', 120)->nullable()->comment('外部物流系统订单号');
            $table->unsignedBigInteger('platform_logistic_order_id')->nullable()->comment('平台物流单ID');
            $table->unsignedBigInteger('handover_list_id')->nullable()->comment('平台交接单ID');
            $table->string('handover_list_status', 40)->nullable()->comment('交接单状态');
            $table->string('tracking_number', 120)->nullable()->comment('运单号');
            $table->string('tracking_url', 500)->nullable()->comment('物流追踪URL');
            $table->string('logistic_status', 50)->nullable()->comment('物流状态');
            $table->json('payload')->nullable()->comment('物流扩展信息');
            $table->timestamps();

            $table->index(['order_id', 'is_primary']);
            $table->index('provider_code');
            $table->index('platform_logistic_order_id');
            $table->index('handover_list_id');
            $table->index('tracking_number');
        });

        DB::table('orders')
            ->select([
                'id',
                'logistics_type',
                'logistics_template',
                'tracking_number',
                'logistic_order_id',
                'handover_list_id',
                'handover_list_status',
                'sz56t_order_id',
                'created_at',
                'updated_at',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($orders) {
                $rows = [];

                foreach ($orders as $order) {
                    $hasLogistics = !empty($order->logistics_type)
                        || !empty($order->logistics_template)
                        || !empty($order->tracking_number)
                        || !empty($order->logistic_order_id)
                        || !empty($order->handover_list_id)
                        || !empty($order->sz56t_order_id);

                    if (!$hasLogistics) {
                        continue;
                    }

                    $providerCode = match (true) {
                        !empty($order->sz56t_order_id) => 'sz56t',
                        $order->logistics_template === 'offline_epacket' => 'chinapost',
                        $order->logistics_template === 'offline_leiyi' => 'sz56t',
                        !empty($order->logistic_order_id) || strtoupper((string) $order->logistics_type) === 'FBS' => 'aliexpress',
                        default => null,
                    };

                    $providerName = match ($providerCode) {
                        'sz56t' => 'SZ56T',
                        'chinapost' => 'China Post',
                        'aliexpress' => 'AliExpress',
                        default => null,
                    };

                    $rows[] = [
                        'order_id' => $order->id,
                        'is_primary' => true,
                        'logistics_mode' => $order->logistics_type ?: null,
                        'provider_code' => $providerCode,
                        'provider_name' => $providerName,
                        'template_code' => $order->logistics_template ?: null,
                        'external_order_id' => $order->sz56t_order_id ?: null,
                        'platform_logistic_order_id' => $order->logistic_order_id ?: null,
                        'handover_list_id' => $order->handover_list_id ?: null,
                        'handover_list_status' => $order->handover_list_status ?: null,
                        'tracking_number' => $order->tracking_number ?: null,
                        'tracking_url' => null,
                        'logistic_status' => null,
                        'payload' => null,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('order_logistics')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_logistics');
    }
};
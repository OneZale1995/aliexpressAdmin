<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('logistic_order_id')->nullable()->after('tracking_number')->comment('货件ID(logistic_orders.id)');
            $table->index('logistic_order_id');
        });

        // 回填历史数据：从 raw_data.logistic_orders[0].id 提取
        DB::statement("\n            UPDATE orders\n            SET logistic_order_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.logistic_orders[0].id')) AS UNSIGNED)\n            WHERE raw_data IS NOT NULL\n              AND JSON_EXTRACT(raw_data, '$.logistic_orders[0].id') IS NOT NULL\n        ");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['logistic_order_id']);
            $table->dropColumn('logistic_order_id');
        });
    }
};

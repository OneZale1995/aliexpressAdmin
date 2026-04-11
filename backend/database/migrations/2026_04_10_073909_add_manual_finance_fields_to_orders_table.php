<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('lianlian_fee', 12, 2)->default(0)->after('affiliate_fee')->comment('连连费用');
            $table->decimal('purchase_amount', 12, 2)->default(0)->after('estimate_revenue')->comment('采购额');
            $table->decimal('express_fee', 12, 2)->default(0)->after('purchase_amount')->comment('快递费(手动)');
            $table->timestamp('apply_qianze_at')->nullable()->after('shipping_date')->comment('申请千泽时间');
            $table->timestamp('ship_qianze_at')->nullable()->after('apply_qianze_at')->comment('发货千泽时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'lianlian_fee',
                'purchase_amount',
                'express_fee',
                'apply_qianze_at',
                'ship_qianze_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('logistics_template', 30)->default('online')->after('logistics_type')->comment('物流模板: online/offline_leiyi/offline_epacket');
            $table->decimal('eub_amazon_ratio', 8, 2)->default(0)->after('logistics_fee')->comment('E邮宝亚马逊比例(%)');
            $table->decimal('eub_base_fee', 12, 2)->default(0)->after('eub_amazon_ratio')->comment('E邮宝固定附加费');
            $table->decimal('calculated_logistics_fee', 12, 2)->default(0)->after('eub_base_fee')->comment('系统计算物流费');
            $table->boolean('logistics_fee_override')->default(false)->after('calculated_logistics_fee')->comment('物流费是否人工覆盖');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'logistics_template',
                'eub_amazon_ratio',
                'eub_base_fee',
                'calculated_logistics_fee',
                'logistics_fee_override',
            ]);
        });
    }
};

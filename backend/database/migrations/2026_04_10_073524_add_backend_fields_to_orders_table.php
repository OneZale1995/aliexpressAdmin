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
            $table->text('admin_remark')->nullable()->after('seller_comment')->comment('后台备注');
            $table->string('purchase_image', 500)->nullable()->after('admin_remark')->comment('采购图片');
            $table->string('shipping_image', 500)->nullable()->after('purchase_image')->comment('发货图片');
            $table->date('purchase_date')->nullable()->after('shipping_image')->comment('采购日期');
            $table->date('shipping_date')->nullable()->after('purchase_date')->comment('发货日期');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'admin_remark',
                'purchase_image',
                'shipping_image',
                'purchase_date',
                'shipping_date',
            ]);
        });
    }
};

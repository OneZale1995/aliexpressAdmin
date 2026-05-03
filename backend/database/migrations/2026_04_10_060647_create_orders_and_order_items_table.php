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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ae_order_id')->unique()->comment('速卖通订单ID');
            $table->unsignedBigInteger('shop_id')->comment('所属店铺');
            $table->string('status', 50)->default('')->comment('订单状态');
            $table->string('payment_status', 50)->default('')->comment('支付状态');
            $table->string('delivery_status', 50)->default('')->comment('发货状态');
            $table->string('order_display_status', 50)->default('')->comment('展示状态');
            $table->string('antifraud_status', 50)->default('')->comment('反欺诈状态');
            $table->decimal('total_amount', 12, 2)->default(0)->comment('订单总金额');
            $table->string('currency', 10)->default('RUB');
            $table->decimal('platform_fee', 12, 2)->default(0)->comment('平台费用');
            $table->decimal('affiliate_fee', 12, 2)->default(0)->comment('联盟费用');
            $table->decimal('estimate_revenue', 12, 2)->default(0)->comment('预估收入');
            $table->string('buyer_name', 200)->default('');
            $table->string('buyer_phone', 50)->default('');
            $table->string('buyer_country_code', 10)->default('');
            $table->string('receiver_name', 200)->default('');
            $table->string('receiver_phone', 50)->default('');
            $table->text('delivery_address')->nullable();
            $table->string('receiver_country', 100)->default('');
            $table->string('receiver_region', 200)->default('');
            $table->string('receiver_city', 200)->default('');
            $table->string('receiver_street', 500)->default('');
            $table->string('receiver_zip', 20)->default('');
            $table->string('logistics_type', 20)->default('')->comment('DBS/FBS');
            $table->string('tracking_number', 100)->default('');
            $table->string('finish_reason', 100)->default('');
            $table->text('seller_comment')->nullable();
            $table->boolean('fully_prepared')->default(false);
            $table->decimal('shipping_fee', 12, 2)->default(0)->comment('快递费');
            $table->decimal('logistics_fee', 12, 2)->default(0)->comment('物流费');
            $table->timestamp('ae_created_at')->nullable()->comment('速卖通下单时间');
            $table->timestamp('ae_paid_at')->nullable()->comment('支付时间');
            $table->timestamp('ae_updated_at')->nullable()->comment('速卖通更新时间');
            $table->timestamp('cut_off_date')->nullable()->comment('建议发货日期');
            $table->timestamp('shipping_deadline')->nullable()->comment('最晚发货日期');
            $table->timestamp('marked_ship_at')->nullable()->comment('标记发货时间');
            $table->timestamp('actual_ship_at')->nullable()->comment('实际发货时间');
            $table->json('disputes')->nullable()->comment('争议信息');
            $table->json('raw_data')->nullable()->comment('原始API数据');
            $table->timestamps();

            $table->index('shop_id');
            $table->index('status');
            $table->index('order_display_status');
            $table->index('ae_created_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('ae_order_line_id')->default(0)->comment('速卖通行项ID');
            $table->string('ae_item_id', 50)->default('')->comment('产品ID');
            $table->string('ae_sku_id', 50)->default('')->comment('SKU ID');
            $table->string('sku_code', 100)->default('');
            $table->string('name', 500)->default('');
            $table->string('img_url', 500)->default('');
            $table->decimal('item_price', 12, 2)->default(0)->comment('单价');
            $table->string('currency', 10)->default('RUB');
            $table->integer('quantity')->default(1);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->json('properties')->nullable();
            $table->decimal('line_fee', 12, 2)->default(0)->comment('平台费');
            $table->decimal('item_affiliate_fee', 12, 2)->default(0)->comment('联盟费');
            $table->decimal('item_estimate_revenue', 12, 2)->default(0)->comment('预估收入');
            $table->string('issue_status', 50)->default('NoDispute');
            $table->string('logistic_name', 200)->default('');
            $table->string('logistic_storage_type', 20)->default('');
            $table->timestamps();

            $table->index('order_id');
            $table->index('ae_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};

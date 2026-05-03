<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('店铺名称');
            $table->string('email', 200)->nullable()->comment('邮箱地址');
            $table->tinyInteger('status')->default(1)->comment('状态 1=正常 0=禁用');
            $table->text('access_token')->nullable()->comment('访问令牌');
            $table->decimal('default_shipping_fee', 10, 2)->default(0)->comment('默认运费');
            $table->string('logistics_template_id', 100)->nullable()->comment('物流模板ID');
            $table->string('logistics_template_name', 200)->nullable()->comment('物流模板名称');
            $table->string('logistics_route', 200)->nullable()->comment('物流线路');
            $table->timestamp('order_updated_at')->nullable()->comment('订单更新时间');
            $table->unsignedBigInteger('user_id')->comment('所属采购用户ID');
            $table->unsignedBigInteger('team_id')->comment('所属团队ID');
            $table->timestamps();

            $table->index('user_id');
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};

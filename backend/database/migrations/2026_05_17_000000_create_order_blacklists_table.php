<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_blacklists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->comment('所属团队ID');
            $table->string('name', 200)->nullable()->comment('黑名单客户姓名');
            $table->string('phone', 50)->nullable()->comment('黑名单客户电话');
            $table->text('remark')->nullable()->comment('备注');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人ID');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('修改人ID');
            $table->timestamps();

            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_blacklists');
    }
};

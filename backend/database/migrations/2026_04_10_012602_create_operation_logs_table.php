<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->default(0)->comment('操作用户ID');
            $table->string('user_name', 100)->default('')->comment('操作用户名');
            $table->string('method', 10)->comment('请求方法');
            $table->string('path', 191)->comment('请求路径');
            $table->string('ip', 45)->nullable()->comment('IP地址');
            $table->text('input')->nullable()->comment('请求参数');
            $table->integer('status_code')->default(200)->comment('响应状态码');
            $table->text('response')->nullable()->comment('响应内容');
            $table->integer('duration')->default(0)->comment('耗时(ms)');
            $table->timestamps();
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
};

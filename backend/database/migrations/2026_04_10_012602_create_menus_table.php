<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父级ID');
            $table->string('title', 50)->comment('菜单标题');
            $table->string('icon', 50)->nullable()->comment('图标');
            $table->string('path', 191)->nullable()->comment('路由路径');
            $table->string('component', 191)->nullable()->comment('前端组件路径');
            $table->string('permission', 100)->nullable()->comment('权限标识');
            $table->tinyInteger('type')->default(1)->comment('类型 1目录 2菜单 3按钮');
            $table->tinyInteger('hidden')->default(0)->comment('是否隐藏 0否 1是');
            $table->integer('sort')->default(0)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态 1启用 0禁用');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};

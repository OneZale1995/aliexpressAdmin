<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('权限标识');
            $table->string('display_name', 100)->comment('显示名称');
            $table->string('guard_name', 50)->default('api')->comment('守卫');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父级ID');
            $table->string('description', 191)->nullable()->comment('描述');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};

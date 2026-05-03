<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('团队名称');
            $table->unsignedBigInteger('admin_user_id')->comment('团队管理员用户ID');
            $table->tinyInteger('status')->default(1)->comment('状态 1=启用 0=禁用');
            $table->text('description')->nullable()->comment('团队描述');
            $table->timestamps();

            $table->index('admin_user_id');
        });

        // 团队成员表（采购人员）
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->index('team_id');
            $table->index('user_id');
            $table->unique(['team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
    }
};

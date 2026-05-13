<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_logistics_configs', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 20)->comment('作用域: team/user');
            $table->unsignedBigInteger('scope_id')->comment('作用域ID');
            $table->string('provider', 30)->comment('物流渠道: chinapost/sz56t');
            $table->boolean('enabled')->default(false)->comment('是否启用该作用域物流配置');
            $table->json('config')->nullable()->comment('配置JSON');
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id', 'provider'], 'uniq_scope_provider');
            $table->index(['provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_logistics_configs');
    }
};

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
        Schema::create('order_sync_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operator_user_id')->nullable()->comment('触发人');
            $table->string('trigger_type', 20)->default('manual')->comment('manual/auto');
            $table->string('status', 20)->default('pending')->comment('pending/running/completed/failed');
            $table->integer('total_shops')->default(0);
            $table->integer('processed_shops')->default(0);
            $table->integer('failed_shops')->default(0);
            $table->integer('synced_orders')->default(0);
            $table->integer('progress')->default(0);
            $table->string('current_shop_name', 200)->nullable();
            $table->text('message')->nullable();
            $table->json('options')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'trigger_type']);
            $table->index('operator_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_sync_tasks');
    }
};

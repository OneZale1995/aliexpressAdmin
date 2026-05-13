<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_export_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operator_user_id')->nullable()->comment('触发人');
            $table->string('trigger_type', 20)->default('manual')->comment('manual/auto');
            $table->string('status', 20)->default('pending')->comment('pending/running/completed/failed');
            $table->string('format', 20)->default('csv')->comment('csv/zip');
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->integer('progress')->default(0);
            $table->string('file_name', 255)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('message')->nullable();
            $table->json('options')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'trigger_type']);
            $table->index('operator_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_export_tasks');
    }
};
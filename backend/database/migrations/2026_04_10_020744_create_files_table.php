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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->default(0)->index();
            $table->string('original_name')->comment('原始文件名');
            $table->string('filename')->comment('存储文件名');
            $table->string('path')->comment('存储路径');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0)->comment('文件大小(字节)');
            $table->string('disk', 50)->default('public')->comment('存储磁盘');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};

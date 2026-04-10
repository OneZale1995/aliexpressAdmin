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
        Schema::create('dict_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dict_type_id')->index();
            $table->string('label')->comment('显示标签');
            $table->string('value', 100)->comment('字典值');
            $table->tinyInteger('status')->default(1);
            $table->unsignedInteger('sort')->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dict_data');
    }
};

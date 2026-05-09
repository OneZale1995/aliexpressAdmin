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
        Schema::create('customs_products', function (Blueprint $table) {
            $table->id();
            $table->string('name_cn', 100)->comment('中文报关名');
            $table->string('name_en', 200)->comment('英文报关名');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customs_products');
    }
};

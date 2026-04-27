<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_order_address_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name', 100);
            $table->string('company', 100)->nullable();
            $table->string('post_code', 32)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('id_type', 32)->nullable();
            $table->string('id_no', 64)->nullable();
            $table->string('nation', 20)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('county', 100)->nullable();
            $table->string('address', 500);
            $table->string('gis', 255)->nullable();
            $table->string('linker', 100)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'type', 'nation'], 'admin_order_address_books_region_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_order_address_books');
    }
};
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
        Schema::create('products', function (Blueprint $table) {
            $table->id(); // Or $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['gel', 'bar', 'drink_mix', 'chews', 'real_food', 'other']);
            $table->decimal('carbs_g', 8, 2)->default(0);
            $table->decimal('sodium_mg', 8, 2)->default(0);
            $table->decimal('caffeine_mg', 8, 2)->default(0);
            $table->string('serving_size_description');
            $table->decimal('serving_volume_ml', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

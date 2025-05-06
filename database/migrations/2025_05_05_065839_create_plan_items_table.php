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
        Schema::create('plan_items', function (Blueprint $table) {
            $table->id(); // Or $table->uuid('id')->primary();
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->integer('time_offset_seconds'); // Time into activity
            $table->enum('instruction_type', ['consume', 'drink', 'reminder']);
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null'); // Product might be deleted
            $table->string('quantity_description'); // e.g., "1", "0.5", "150ml", "Sip"
            $table->decimal('calculated_carbs_g', 8, 2)->default(0);
            $table->decimal('calculated_fluid_ml', 8, 2)->default(0);
            $table->decimal('calculated_sodium_mg', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps(); // Optional for plan items, but can be useful
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_items');
    }
};

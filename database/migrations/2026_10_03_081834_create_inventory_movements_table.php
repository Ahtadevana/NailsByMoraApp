<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')
                ->constrained('inventory')
                ->restrictOnDelete();
            $table->enum('type', ['IN', 'OUT']);
            $table->decimal('quantity', 10, 2)->unsigned();
            $table->string('reason');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_quantity_positive CHECK (quantity > 0)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};

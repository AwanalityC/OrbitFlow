<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('borrowing_item_equipment_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrowing_item_id')
                ->constrained('borrowing_items')
                ->cascadeOnDelete();
            $table->foreignId('equipment_unit_id')
                ->constrained('equipment_units')
                ->restrictOnDelete();
            $table->timestamp('borrowed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['borrowing_item_id', 'equipment_unit_id'],
                'borrow_item_unit_unique'
            );
            $table->index(
                ['equipment_unit_id', 'returned_at'],
                'bi_unit_returned_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowing_item_equipment_unit');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('borrowing_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('borrowing_id')
                ->constrained('borrowings')
                ->cascadeOnDelete();

            $table->foreignId('equipment_id')
                ->constrained('equipment')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowing_items');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('losses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('borrowing_id')
                ->constrained('borrowings')
                ->cascadeOnDelete();

            $table->foreignId('equipment_unit_id')
                ->constrained('equipment_units')
                ->cascadeOnDelete();

            $table->text('description')->nullable();
            $table->string('status')->default('Dilaporkan');
            $table->timestamp('reported_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('losses');
    }
};
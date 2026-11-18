<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('compensations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('return_record_id')
                ->nullable()
                ->constrained('return_records')
                ->nullOnDelete();

            $table->foreignId('loss_id')
                ->nullable()
                ->constrained('losses')
                ->nullOnDelete();

            $table->string('type');
            $table->text('description')->nullable();
            $table->string('status')->default('Pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensations');
    }
};
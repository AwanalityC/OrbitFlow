<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('compensations', function (Blueprint $table) {
            $table->string('resolution_type', 50)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->string('recovered_condition', 30)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->foreignId('replacement_equipment_unit_id')
                ->nullable()
                ->constrained('equipment_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('compensations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropConstrainedForeignId('replacement_equipment_unit_id');
            $table->dropColumn([
                'resolution_type',
                'amount',
                'resolution_notes',
                'recovered_condition',
                'decided_at',
                'resolved_at',
                'completion_notes',
            ]);
        });
    }
};

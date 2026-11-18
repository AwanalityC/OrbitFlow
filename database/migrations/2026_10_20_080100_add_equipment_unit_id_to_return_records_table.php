<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_records', function (Blueprint $table) {
            $table->foreignId('equipment_unit_id')
                ->nullable()
                ->after('borrowing_id')
                ->constrained('equipment_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('return_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_unit_id');
        });
    }
};

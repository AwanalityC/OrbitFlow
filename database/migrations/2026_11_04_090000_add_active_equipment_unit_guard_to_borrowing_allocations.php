<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Refuse to guess if historical data already assigns a physical unit to
        // more than one unreturned borrowing. Resolve those records first.
        $duplicates = DB::table('borrowing_item_equipment_unit')
            ->select('equipment_unit_id')
            ->whereNull('returned_at')
            ->groupBy('equipment_unit_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('equipment_unit_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($duplicates !== []) {
            throw new \RuntimeException(
                'Cannot enable active-unit protection. These equipment unit IDs have multiple active allocations: ' .
                implode(', ', $duplicates) .
                '. Review the borrowing_item_equipment_unit table and close or correct duplicate allocations before retrying this migration.'
            );
        }

        Schema::table('borrowing_item_equipment_unit', function (Blueprint $table) {
            $table->unsignedBigInteger('active_equipment_unit_id')->nullable()->after('equipment_unit_id');
        });

        // Backfill all existing unreturned allocations so the new unique index
        // protects current loans as well as future approvals.
        DB::table('borrowing_item_equipment_unit')
            ->whereNull('returned_at')
            ->update(['active_equipment_unit_id' => DB::raw('equipment_unit_id')]);

        Schema::table('borrowing_item_equipment_unit', function (Blueprint $table) {
            $table->unique('active_equipment_unit_id', 'bi_active_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::table('borrowing_item_equipment_unit', function (Blueprint $table) {
            $table->dropUnique('bi_active_unit_unique');
            $table->dropColumn('active_equipment_unit_id');
        });
    }
};

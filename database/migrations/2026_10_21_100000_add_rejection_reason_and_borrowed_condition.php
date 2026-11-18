<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable();
        });

        Schema::table('borrowing_item_equipment_unit', function (Blueprint $table) {
            $table->string('condition_at_borrowing')->nullable()->after('borrowed_at');
        });

        // Earlier versions treated every lightly damaged unit as unusable. Restore
        // known functional units to the available pool while retaining their condition.
        DB::table('equipment_units')
            ->where('condition', 'Rusak Ringan')
            ->where('status', 'Rusak')
            ->update(['status' => 'Tersedia']);
    }

    public function down(): void
    {
        Schema::table('borrowing_item_equipment_unit', function (Blueprint $table) {
            $table->dropColumn('condition_at_borrowing');
        });

        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};

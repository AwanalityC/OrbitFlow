<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('image_path')->nullable();
        });

        Schema::table('equipment_units', function (Blueprint $table) {
            $table->string('current_photo_path')->nullable();
        });

        Schema::table('borrowing_item_equipment_unit', function (Blueprint $table) {
            $table->string('handover_photo_path')->nullable();
            $table->unsignedBigInteger('handover_photo_taken_by')->nullable();
            $table->foreign('handover_photo_taken_by', 'bi_handover_photo_actor_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('return_records', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
            $table->unsignedBigInteger('photo_taken_by')->nullable();
            $table->foreign('photo_taken_by', 'return_photo_actor_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('return_records', function (Blueprint $table) {
            $table->dropForeign('return_photo_actor_fk');
            $table->dropColumn(['photo_path', 'photo_taken_by']);
        });

        Schema::table('borrowing_item_equipment_unit', function (Blueprint $table) {
            $table->dropForeign('bi_handover_photo_actor_fk');
            $table->dropColumn(['handover_photo_path', 'handover_photo_taken_by']);
        });

        Schema::table('equipment_units', function (Blueprint $table) {
            $table->dropColumn('current_photo_path');
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};

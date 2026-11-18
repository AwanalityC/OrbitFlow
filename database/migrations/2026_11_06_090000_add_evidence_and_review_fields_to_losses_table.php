<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('losses', function (Blueprint $table) {
            $table->string('evidence_photo_path')->nullable()->after('description');
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reported_at');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->index('reported_at', 'loss_reported_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('losses', function (Blueprint $table) {
            $table->dropIndex('loss_reported_at_idx');
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['evidence_photo_path', 'reviewed_by', 'reviewed_at', 'review_notes']);
        });
    }
};

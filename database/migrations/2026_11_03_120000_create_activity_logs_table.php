<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Snapshots preserve attribution if an account is ever removed later.
            $table->string('actor_name', 255);
            $table->string('actor_email', 255)->nullable();
            $table->string('actor_role', 40)->nullable();
            $table->string('category', 40)->index();
            $table->string('event_key', 100)->index();
            $table->string('summary', 255);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 255)->nullable();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at', 6)->useCurrent()->index();

            $table->index(['actor_user_id', 'created_at'], 'activity_actor_created_idx');
            $table->index(['category', 'created_at'], 'activity_category_created_idx');
            $table->index(['subject_type', 'subject_id'], 'activity_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};

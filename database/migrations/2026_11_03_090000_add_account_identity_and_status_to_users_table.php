<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nis')->nullable()->unique('users_nis_unique');
            $table->string('class_name', 50)->nullable();
            $table->boolean('is_active')->default(true);
        });

        // Existing administrator accounts become Super Admins without changing
        // their login credentials or any borrowing history.
        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'super_admin']);
    }

    public function down(): void
    {
        DB::table('users')
            ->whereIn('role', ['super_admin', 'petugas'])
            ->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_nis_unique');
            $table->dropColumn(['nis', 'class_name', 'is_active']);
        });
    }
};

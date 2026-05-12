<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel stores MySQL ENUM as VARCHAR + CHECK on SQLite. The MySQL-only migration
 * that adds super_admin skips SQLite, so tests could not insert role=super_admin.
 * This migration replaces the role column with a plain string on SQLite only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role_string', 32)->default('user');
        });

        DB::statement('UPDATE users SET role_string = role');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('role_string', 'role');
        });
    }

    public function down(): void
    {
        // Non-destructive: re-tightening SQLite CHECK enums without doctrine/dbal is fragile.
    }
};

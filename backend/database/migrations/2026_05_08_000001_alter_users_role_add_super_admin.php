<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            throw new \RuntimeException('Role enum migration currently supports only MySQL. Use MySQL for this project.');
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','user','super_admin') NOT NULL DEFAULT 'user'");
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            throw new \RuntimeException('Role enum rollback currently supports only MySQL. Use MySQL for this project.');
        }

        DB::statement("UPDATE users SET role = 'admin' WHERE role = 'super_admin'");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user'");
    }
};

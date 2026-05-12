<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // 🏢 Link user to company (multi-tenant SaaS)
            $table->foreignId('company_id')
                  ->nullable()
                  ->constrained()
                  ->cascadeOnDelete();

            // 🔐 Role-based system (FIXED ✅)
            $table->enum('role', ['admin', 'user'])->default('user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // Remove foreign key safely
            $table->dropForeign(['company_id']);

            // Remove columns
            $table->dropColumn(['company_id', 'role']);
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $toDrop = array_values(array_filter(
            ['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_hashes'],
            fn (string $col) => Schema::hasColumn('users', $col)
        ));

        if ($toDrop === []) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('remember_token');
            }
            if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            }
            if (! Schema::hasColumn('users', 'two_factor_recovery_hashes')) {
                $table->json('two_factor_recovery_hashes')->nullable()->after('two_factor_confirmed_at');
            }
        });
    }
};

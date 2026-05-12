<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Align existing DB rows with PlatformOwnersSeeder canonical platform tenant email.
 */
return new class extends Migration
{
    public function up(): void
    {
        $new = 'owner@aicomplaintdoctor.platform';
        $old = 'owners@ai-complaint-doctor.platform';

        if (DB::table('companies')->where('email', $new)->exists()) {
            return;
        }

        DB::table('companies')->where('email', $old)->update(['email' => $new]);
    }

    public function down(): void
    {
        DB::table('companies')
            ->where('email', 'owner@aicomplaintdoctor.platform')
            ->update(['email' => 'owners@ai-complaint-doctor.platform']);
    }
};

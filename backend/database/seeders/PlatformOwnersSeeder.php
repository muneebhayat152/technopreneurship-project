<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Ensures the four project owners exist as platform super administrators,
 * sharing one organization ("AI Complaint Doctor").
 *
 * Runs on deploy (see Dockerfile / railway.json). Safe to run repeatedly:
 * uses updateOrCreate by email.
 */
class PlatformOwnersSeeder extends Seeder
{
    /** Distinct from tenant sign-ups — reserved platform tenant email. */
    private const PLATFORM_COMPANY_EMAIL = 'owners@ai-complaint-doctor.platform';

    public function run(): void
    {
        $company = Company::updateOrCreate(
            ['email' => self::PLATFORM_COMPANY_EMAIL],
            [
                'name' => 'AI Complaint Doctor',
                'subscription' => 'premium',
                'is_active' => true,
                'industry' => 'Customer Experience & AI Software',
                'country' => 'Pakistan',
            ]
        );

        $owners = [
            ['name' => 'Wania Babar', 'email' => 'waniababar@gmail.com'],
            ['name' => 'Abdul Rafay', 'email' => 'abdulrafay@gmail.com'],
            ['name' => 'Muhammad Ramish', 'email' => 'mramish152@gmail.com'],
            ['name' => 'Muneeb Ur Rehman', 'email' => 'muneebhayat152@gmail.com'],
        ];

        foreach ($owners as $owner) {
            User::updateOrCreate(
                ['email' => $owner['email']],
                [
                    'name' => $owner['name'],
                    'password' => '123456',
                    'role' => 'super_admin',
                    'company_id' => $company->id,
                ]
            );
        }
    }
}

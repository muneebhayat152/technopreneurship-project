<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Complaint;
use App\Models\User;
use App\Services\ClusteringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserProvidedResetSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('UserProvidedResetSeeder is disabled outside local/testing.');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Clear business data
        DB::table('alerts')->truncate();
        DB::table('issue_timeseries')->truncate();
        DB::table('complaints')->truncate();
        DB::table('issue_clusters')->truncate();
        DB::table('personal_access_tokens')->truncate();
        DB::table('users')->truncate();
        DB::table('companies')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $companies = [
            [
                'name' => 'GlobalTelco Group',
                'email' => 'hq@globaltelco.com',
                'subscription' => 'premium',
                'industry' => 'Telecommunications',
                'country' => 'United Kingdom',
            ],
            [
                'name' => 'FreshMart E‑commerce',
                'email' => 'ops@freshmart.com',
                'subscription' => 'free',
                'industry' => 'E‑commerce',
                'country' => 'United Arab Emirates',
            ],
            [
                'name' => 'MetroBank Partners',
                'email' => 'service@metrobankpartners.com',
                'subscription' => 'premium',
                'industry' => 'Banking / Fintech',
                'country' => 'United States',
            ],
            [
                'name' => 'Nordic Logistics AB',
                'email' => 'hello@nordiclogistics.com',
                'subscription' => 'free',
                'industry' => 'Logistics',
                'country' => 'Sweden',
            ],
            [
                'name' => 'Singapore Healthdesk',
                'email' => 'care@singaporehealthdesk.com',
                'subscription' => 'premium',
                'industry' => 'Healthcare',
                'country' => 'Singapore',
            ],
        ];

        $companyModels = [];
        foreach ($companies as $c) {
            $companyModels[] = Company::create([
                'name' => $c['name'],
                'email' => $c['email'],
                'subscription' => $c['subscription'],
                'is_active' => true,
                'industry' => $c['industry'],
                'country' => $c['country'],
            ]);
        }

        // Emails: add .com where user omitted it.
        $users = [
            [
                'name' => 'Muneeb Ur Rehman',
                'email' => 'muneebhayat152@gmail.com',
                'password' => '123456',
                'role' => 'super_admin',
                'company' => $companyModels[0],
                'complaint' => 'Repeated network drops and slow data in evening hours. Please fix urgently.',
            ],
            [
                'name' => 'Abdul Rafay',
                'email' => 'abdulrafay@gmail.com',
                'password' => '123456',
                'role' => 'user',
                'company' => $companyModels[1],
                'complaint' => 'Delivery arrived late and items were damaged. This is poor service.',
            ],
            [
                'name' => 'Muhammad Ramish',
                'email' => 'mramish@gmail.com',
                'password' => '123456',
                'role' => 'user',
                'company' => $companyModels[2],
                'complaint' => 'Payment failed during transfer and I got an error. Please check the system.',
            ],
            [
                'name' => 'Wania Babar',
                'email' => 'waniababar@gmail.com',
                'password' => '123456',
                'role' => 'user',
                'company' => $companyModels[3],
                'complaint' => 'Shipment tracking has not updated and delivery is delayed again.',
            ],
            [
                'name' => 'Shoaib Hassan',
                'email' => 'shoaibkharal@gmail.com',
                'password' => '123456',
                'role' => 'user',
                'company' => $companyModels[4],
                'complaint' => 'Support response is slow and appointment portal shows error after OTP.',
            ],
        ];

        foreach ($users as $u) {
            $user = User::create([
                'name' => $u['name'],
                'email' => $u['email'],
                'password' => Hash::make($u['password']),
                'company_id' => $u['company']->id,
                'role' => $u['role'],
            ]);

            $text = $u['complaint'];
            $t = strtolower($text);
            $sentiment = 'neutral';
            if (preg_match('/\b(bad|slow|late|poor|worst|error|failed|delay|damaged|urgent)\b/', $t)) {
                $sentiment = 'negative';
            } elseif (preg_match('/\b(good|excellent|fast|great)\b/', $t)) {
                $sentiment = 'positive';
            }

            $category = 'general';
            if (str_contains($t, 'delivery') || str_contains($t, 'shipment') || str_contains($t, 'tracking')) {
                $category = 'delivery';
            } elseif (str_contains($t, 'payment') || str_contains($t, 'transfer') || str_contains($t, 'billing')) {
                $category = 'payment';
            } elseif (str_contains($t, 'support') || str_contains($t, 'service') || str_contains($t, 'appointment')) {
                $category = 'service';
            }

            Complaint::create([
                'company_id' => $u['company']->id,
                'user_id' => $user->id,
                'complaint_text' => $text,
                'sentiment' => $sentiment,
                'category' => $category,
                'status' => 'open',
                'priority' => $sentiment === 'negative' ? 'high' : 'medium',
            ]);
        }

        // Build issue clusters + alerts for the new data.
        /** @var ClusteringService $clustering */
        $clustering = app(ClusteringService::class);
        foreach ($companyModels as $c) {
            $clustering->reclusterCompany((int) $c->id);
        }
    }
}


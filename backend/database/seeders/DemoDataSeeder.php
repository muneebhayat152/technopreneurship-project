<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Models\Complaint;
use App\Services\ClusteringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) && ! env('ALLOW_DEMO_SEEDER', false)) {
            throw new \RuntimeException('DemoDataSeeder is blocked outside local/testing unless ALLOW_DEMO_SEEDER=true.');
        }

        $clustering = app(ClusteringService::class);

        User::updateOrCreate(
            ['email' => 'superadmin@ai-complaint-doctor.test'],
            [
                'name' => 'Platform Super Admin',
                'password' => Hash::make('password'),
                'company_id' => null,
                'role' => 'super_admin',
            ]
        );

        $companies = [
            [
                'name' => 'GlobalTelco Group',
                'email' => 'hq@globaltelco.test',
                'subscription' => 'premium',
                'industry' => 'Telecommunications',
                'country' => 'United Kingdom',
                'admin_email' => 'admin@globaltelco.test',
                'templates' => [
                    'billing cycle is wrong and I was overcharged twice this month',
                    'network drops every evening in central London area',
                    'customer support chat waited 45 minutes with no resolution',
                    '5G speed is slower than advertised on my business plan',
                    'payment failed when renewing my family bundle online',
                ],
            ],
            [
                'name' => 'FreshMart E‑commerce',
                'email' => 'ops@freshmart.test',
                'subscription' => 'free',
                'industry' => 'E‑commerce',
                'country' => 'United Arab Emirates',
                'admin_email' => 'admin@freshmart.test',
                'templates' => [
                    'delivery arrived late and vegetables were damaged',
                    'refund for missing item still not processed after 10 days',
                    'checkout payment error on mobile app during sale',
                    'support agent was rude when I asked for exchange',
                ],
            ],
            [
                'name' => 'Metro Bank Partners',
                'email' => 'service@metrobankpartners.test',
                'subscription' => 'premium',
                'industry' => 'Banking / Fintech',
                'country' => 'United States',
                'admin_email' => 'admin@metrobankpartners.test',
                'templates' => [
                    'card declined at merchant but balance shows available funds',
                    'wire transfer delay of 4 business days without notification',
                    'mobile banking app crashes on statement export',
                    'incorrect interest charge on my savings product',
                ],
            ],
            [
                'name' => 'Nordic Logistics AB',
                'email' => 'hello@nordiclogistics.test',
                'subscription' => 'free',
                'industry' => 'Logistics',
                'country' => 'Sweden',
                'admin_email' => 'admin@nordiclogistics.test',
                'templates' => [
                    'shipment stuck at customs with no tracking updates',
                    'late delivery caused production line stoppage at our plant',
                    'damaged pallet received at warehouse dock 3',
                ],
            ],
            [
                'name' => 'Singapore Healthdesk',
                'email' => 'care@singaporehealthdesk.test',
                'subscription' => 'premium',
                'industry' => 'Healthcare',
                'country' => 'Singapore',
                'admin_email' => 'admin@singaporehealthdesk.test',
                'templates' => [
                    'appointment booking portal shows error after OTP',
                    'long waiting time at clinic despite scheduled slot',
                    'billing statement does not match insurance claim',
                    'poor communication about lab results turnaround',
                ],
            ],
        ];

        foreach ($companies as $row) {
            $company = Company::firstOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'subscription' => $row['subscription'],
                    'is_active' => true,
                    'registration_status' => Company::REGISTRATION_ACTIVE,
                    'industry' => $row['industry'],
                    'country' => $row['country'],
                ]
            );

            $admin = User::firstOrCreate(
                ['email' => $row['admin_email']],
                [
                    'name' => $row['name'].' Admin',
                    'password' => Hash::make('password'),
                    'company_id' => $company->id,
                    'role' => 'admin',
                ]
            );

            $agent = User::firstOrCreate(
                ['email' => 'agent.'.$company->id.'@demo.test'],
                [
                    'name' => 'Support Agent',
                    'password' => Hash::make('password'),
                    'company_id' => $company->id,
                    'role' => 'user',
                ]
            );

            $templates = $row['templates'];
            $n = 120;
            for ($i = 0; $i < $n; $i++) {
                $base = $templates[$i % count($templates)];
                $text = ucfirst($base).'. Case reference #'.(1000 + $i).' — please escalate if unresolved.';
                $daysAgo = random_int(0, 35);
                $created = Carbon::now()->subDays($daysAgo)->subHours(random_int(0, 20));

                $t = strtolower($text);
                $sentiment = 'neutral';
                if (preg_match('/\b(bad|slow|late|poor|worst|rude|error|failed|delay|damaged|stuck)\b/', $t)) {
                    $sentiment = 'negative';
                }
                $category = 'general';
                if (str_contains($t, 'delivery') || str_contains($t, 'shipment') || str_contains($t, 'logistics') || str_contains($t, 'warehouse')) {
                    $category = 'delivery';
                } elseif (str_contains($t, 'payment') || str_contains($t, 'billing') || str_contains($t, 'refund') || str_contains($t, 'card') || str_contains($t, 'charge')) {
                    $category = 'payment';
                } elseif (str_contains($t, 'support') || str_contains($t, 'service') || str_contains($t, 'agent') || str_contains($t, 'waiting')) {
                    $category = 'service';
                }

                Complaint::create([
                    'company_id' => $company->id,
                    'user_id' => $i % 3 === 0 ? $admin->id : $agent->id,
                    'complaint_text' => $text,
                    'sentiment' => $sentiment,
                    'category' => $category,
                    'status' => $i % 7 === 0 ? 'resolved' : 'open',
                    'priority' => $sentiment === 'negative' ? 'high' : 'medium',
                    'created_at' => $created,
                    'updated_at' => $created,
                ]);
            }

            $clustering->reclusterCompany((int) $company->id);
        }
    }
}

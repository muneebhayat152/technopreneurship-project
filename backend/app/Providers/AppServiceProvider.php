<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id
                ? 'user:'.$request->user()->id
                : 'ip:'.$request->ip();

            return Limit::perMinute(120)->by($key);
        });

        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(10)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('approval-requests', function (Request $request) {
            $uid = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(30)->by('approval-req:'.$uid);
        });

        RateLimiter::for('approval-review', function (Request $request) {
            $uid = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(90)->by('approval-rev:'.$uid);
        });

        RateLimiter::for('complaint-status', function (Request $request) {
            $uid = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(45)->by('complaint-st:'.$uid);
        });

        RateLimiter::for('user-notifications', function (Request $request) {
            $uid = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(120)->by('user-notif:'.$uid);
        });
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePremiumSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $user->loadMissing('company');

        if ($user->role !== 'super_admin') {
            $company = $user->company;
            if (! $company || ! $company->is_active) {
                return response()->json(['success' => false, 'message' => 'Company unavailable'], 403);
            }
        }

        if ($user->hasPremiumFeatures()) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Premium access required (assign Premium on the user or upgrade the organization).',
            'upgrade_required' => true,
        ], 402);
    }
}

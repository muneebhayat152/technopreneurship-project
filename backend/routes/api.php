<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\IssuesController;
use App\Http\Controllers\PlatformAnalyticsController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AdminApprovalRequestController;
use App\Http\Controllers\UserNotificationController;

/*
|--------------------------------------------------------------------------
| API Routes - AI Complaint Doctor SaaS (FINAL VERSION)
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Root (GET /api) — smoke test for Railway / VITE_API_URL base
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => response()->json([
    'status' => 'ok',
    'app' => config('app.name'),
]));

/*
|--------------------------------------------------------------------------
| 🔐 AUTH ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
});


/*
|--------------------------------------------------------------------------
| 🔒 PROTECTED ROUTES (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | 👤 CURRENT USER
    |--------------------------------------------------------------------------
    */
    Route::get('/user', function (Request $request) {
        $user = $request->user()->loadMissing('company');

        return response()->json([
            'success' => true,
            'user' => array_merge($user->toArray(), [
                'effective_access_tier' => $user->computeEffectiveAccessTier(),
            ]),
        ]);
    });
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/user/notifications', [UserNotificationController::class, 'index'])
        ->middleware('throttle:user-notifications');
    Route::post('/user/notifications/read-all', [UserNotificationController::class, 'markAllRead'])
        ->middleware('throttle:user-notifications');
    Route::post('/user/notifications/{id}/read', [UserNotificationController::class, 'markRead'])
        ->middleware('throttle:user-notifications');

    /*
    |--------------------------------------------------------------------------
    | 🧾 COMPLAINT MODULE
    |--------------------------------------------------------------------------
    */
    Route::prefix('complaints')->group(function () {

        Route::post('/', [ComplaintController::class, 'store']);          // Create
        Route::get('/', [ComplaintController::class, 'index']);           // Read
        Route::put('/{id}/status', [ComplaintController::class, 'updateStatus'])
            ->middleware('throttle:complaint-status'); // Status (admin / super admin)
        Route::put('/{id}', [ComplaintController::class, 'update']);       // Update text
        Route::delete('/{id}', [ComplaintController::class, 'destroy']);  // Delete
    });


    /*
    |--------------------------------------------------------------------------
    | 👨‍💼 ADMIN MODULE
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->group(function () {

        Route::get('/analytics', [PlatformAnalyticsController::class, 'index']);
        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        Route::post('/approval-requests', [AdminApprovalRequestController::class, 'store'])
            ->middleware('throttle:approval-requests');
        Route::get('/approval-requests', [AdminApprovalRequestController::class, 'index']);
        Route::post('/approval-requests/{id}/approve', [AdminApprovalRequestController::class, 'approve'])
            ->middleware('throttle:approval-review');
        Route::post('/approval-requests/{id}/reject', [AdminApprovalRequestController::class, 'reject'])
            ->middleware('throttle:approval-review');

        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::put('/users/{id}/restore', [AdminController::class, 'restoreUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
    });


    /*
    |--------------------------------------------------------------------------
    | 🏢 COMPANY MODULE (🔥 NEW SaaS FEATURE)
    |--------------------------------------------------------------------------
    */
    Route::prefix('companies')->group(function () {

        Route::get('/', [CompanyController::class, 'index']);              // Get all companies (super admin)
        Route::post('/{id}/toggle', [CompanyController::class, 'toggleStatus']); // Activate/Deactivate
        Route::put('/{id}/subscription', [CompanyController::class, 'updateSubscription']);
        Route::delete('/{id}', [CompanyController::class, 'destroy']);    // Remove tenant (super admin)
    });

    /*
    |--------------------------------------------------------------------------
    | 📊 DASHBOARD MODULE
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | 🔬 ISSUES / ALERTS (Premium + platform super admin)
    |--------------------------------------------------------------------------
    */
    Route::middleware('premium')->group(function () {
        Route::get('/issues', [IssuesController::class, 'index']);
        Route::get('/issues/{id}/diagnosis', [IssuesController::class, 'diagnosis']);
        Route::get('/issues/{id}/timeline', [IssuesController::class, 'timeline']);
        Route::get('/alerts', [IssuesController::class, 'alerts']);
        Route::post('/alerts/{id}/read', [IssuesController::class, 'markAlertRead']);
    });
});
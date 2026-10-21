<?php

use App\Http\Controllers\AdminOperationsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PartnerWorkspaceController;
use App\Http\Controllers\StaticDesignController;
use App\Http\Controllers\SupporterWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/ads/{ad}/click', [AdminOperationsController::class, 'trackClick'])->whereNumber('ad')->name('ads.click');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'postLogin'])->middleware('throttle:web-login')->name('login.store');
});

Route::middleware(['auth', 'account.active'])->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/supporter', [DashboardController::class, 'supporter'])->middleware(['role:supporter', 'access:dashboard.supporter'])->name('supporter.dashboard');
    Route::middleware(['role:supporter', 'access:dashboard.supporter'])->prefix('supporter')->name('supporter.')->group(function (): void {
        Route::get('/orders', [SupporterWorkspaceController::class, 'orders'])->middleware('access:orders.view')->name('orders');
        Route::get('/maids', [SupporterWorkspaceController::class, 'maids'])->middleware('access:maids.view')->name('maids');
        Route::get('/profile', [SupporterWorkspaceController::class, 'profile'])->name('profile');
        Route::put('/profile', [SupporterWorkspaceController::class, 'updateProfile'])->name('profile.update');
        Route::get('/security', [SupporterWorkspaceController::class, 'security'])->name('security');
        Route::put('/security', [SupporterWorkspaceController::class, 'updateSecurity'])->name('security.update');
    });
    Route::get('/partner', [DashboardController::class, 'partner'])->middleware(['role:partner', 'access:dashboard.partner'])->name('partner.dashboard');
    Route::middleware(['role:partner', 'access:dashboard.partner'])->prefix('partner')->name('partner.')->group(function (): void {
        Route::get('/orders', [PartnerWorkspaceController::class, 'orders'])->middleware('access:orders.view')->name('orders');
        Route::get('/maids', [PartnerWorkspaceController::class, 'maids'])->middleware('access:maids.view')->name('maids');
        Route::get('/messages', [PartnerWorkspaceController::class, 'messages'])->middleware('access:messages.view')->name('messages');
        Route::post('/messages/{conversation}/reply', [PartnerWorkspaceController::class, 'reply'])->middleware('access:messages.view')->name('messages.reply');
        Route::get('/profile', [PartnerWorkspaceController::class, 'profile'])->name('profile');
        Route::put('/profile', [PartnerWorkspaceController::class, 'updateProfile'])->name('profile.update');
    });

    include 'super_admin.php';

    Route::get('/', [DashboardController::class, 'redirect'])->name('dashboard');
    Route::post('/profile/avatar', [MediaController::class, 'avatar'])->middleware('access:profile.media.manage')->name('profile.avatar');

    foreach (config('design.workspaces') as $workspace => $settings) {
        Route::redirect("/design/{$workspace}", "/design/{$workspace}/{$settings['home']}")
            ->middleware(["role:{$settings['role']}", "access:{$settings['permission']}"])
            ->name("design.{$workspace}.home");

        Route::get("/design/{$workspace}/{file}", StaticDesignController::class)
            ->defaults('workspace', $workspace)
            ->where('file', '[A-Za-z0-9][A-Za-z0-9._-]*')
            ->middleware(["role:{$settings['role']}", "access:{$settings['permission']}"])
            ->name("design.{$workspace}.file");
    }
});

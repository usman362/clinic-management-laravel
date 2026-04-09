<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// Upgrade endpoints — gated behind auth + clinic_admin role.
// These routes run destructive migrations / seeders and must never be exposed publicly.
Route::middleware(['auth', 'xss', 'checkUserStatus', 'role:clinic_admin'])->group(function () {
    // upgrade to v3.0.0
    Route::post('/upgrade-to-v3-0-0', function () {
        Artisan::call('db:seed', ['--class' => 'DefaultPaymentGatewaySeeder']);

        return back()->with('success', 'v3.0.0 payment gateway seeder executed.');
    })->name('upgrade.v3');

    Route::post('upgrade/database', function () {
        Artisan::call('migrate', ['--force' => true]);

        return back()->with('success', 'Database migrated.');
    })->name('upgrade.database');

    Route::post('lang-js', function () {
        Artisan::call('lang:js');

        return back()->with('success', 'Language JS regenerated.');
    })->name('upgrade.lang-js');
});

<?php

use App\Http\Controllers\AppointmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| JotForm Consent Webhook
|--------------------------------------------------------------------------
|
| Called by booking.js after the patient signs a consent form in the embedded
| JotForm iframe. Authenticated session required so we can verify the patient
| owns the appointment they're signing for. The JotForm iframe runs inside an
| authenticated patient/doctor page, so the auth cookie is available.
|
| Required POST fields:
|   - appointment_id: the appointment's id
|   - doctor_id: the doctor's ID
|
| Optional: attach a PDF file as 'file' in the multipart request.
|
*/
Route::middleware(['web', 'auth'])
    ->match(['get', 'post'], '/consent-webhook', [AppointmentController::class, 'consentWebhook'])
    ->name('api.consent.webhook');

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
// CP-12 fix: Removed 'auth' middleware. JotForm redirects from an iframe (cross-origin)
// which has NO session cookie, so 'auth' blocks all JotForm POSTs with 401.
// The consentWebhook() method has its own authorization logic inside (checks
// appointment ownership when user IS authenticated, allows recording when not).
Route::middleware(['web'])
    ->match(['get', 'post'], '/consent-webhook', [AppointmentController::class, 'consentWebhook'])
    ->name('api.consent.webhook');

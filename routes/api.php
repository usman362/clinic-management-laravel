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
// CP-12: Jotform's servers post here directly from jotform.com (not through
// the browser), so there is NO session, NO cookies, NO CSRF token. Running
// this route under the `web` middleware group was causing every external
// webhook to be rejected with 419 "Page Expired" before the controller ran.
//
// We deliberately register it WITHOUT the `web` group. CSRF is further
// exempted in VerifyCsrfToken::$except. Authorization is handled inside
// AppointmentController::consentWebhook():
//   * when a session IS present (in-page AJAX path from booking.js) it
//     verifies the patient owns the appointment
//   * when no session (Jotform server call) it falls back to submission_id
//     + appointment_id/doctor_id resolved from POST/query/referer
Route::match(['get', 'post'], '/consent-webhook', [AppointmentController::class, 'consentWebhook'])
    ->name('api.consent.webhook');

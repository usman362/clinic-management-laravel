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
| Public endpoint for JotForm to call after a consent form is signed.
| Configure JotForm "Thank You" page or webhook to POST to:
|   POST {APP_URL}/api/consent-webhook
|
| Required query params or hidden fields:
|   - appointment_token: the appointment's unique_id
|   - doctor_id: the doctor's ID
|
| Optional: attach a PDF file as 'file' in the multipart request.
|
*/
Route::match(['get', 'post'], '/consent-webhook', [AppointmentController::class, 'consentWebhook'])->name('api.consent.webhook');

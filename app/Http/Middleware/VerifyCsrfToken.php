<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'razorpay-payment-success',
        'razorpay-payment-failed',
        'paytm-callback',
        // CP-12: Jotform posts from its own servers and can't send a CSRF
        // token. The webhook endpoint verifies the submission via its own
        // authorization logic (appointment ownership / optional shared
        // secret) inside AppointmentController::consentWebhook().
        'api/consent-webhook',
        'consent-webhook',
    ];
}

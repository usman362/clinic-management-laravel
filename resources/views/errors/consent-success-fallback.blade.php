<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consent Form Submitted</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f5f7fa; color: #333; }
        .container { max-width: 500px; width: 90%; text-align: center; padding: 40px 30px; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .icon { width: 80px; height: 80px; margin: 0 auto 24px; background: #d4edda; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .icon svg { width: 40px; height: 40px; color: #28a745; }
        h1 { font-size: 22px; font-weight: 700; margin-bottom: 12px; color: #155724; }
        p { font-size: 15px; color: #6c757d; line-height: 1.6; margin-bottom: 20px; }
        .info { background: #e8f5e9; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #2e7d32; margin-bottom: 24px; }
        .btn { display: inline-block; padding: 12px 28px; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: #6366f1; color: #fff; }
        .btn-primary:hover { background: #4f46e5; }
        .note { font-size: 12px; color: #9ca3af; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1>Thank You!</h1>
        <p>{{ $message ?? 'Your consent form has been submitted successfully.' }}</p>
        <div class="info">
            Your consent form has been recorded. You can now return to the booking page to continue.
        </div>
        @if(isset($appointment) && $appointment)
            <a href="{{ route('patients.appointments.book-by-token', $appointment->appointment_unique_id) }}" class="btn btn-primary">
                Continue Booking
            </a>
        @else
            <button onclick="window.history.back()" class="btn btn-primary">
                Go Back
            </button>
        @endif
        <p class="note">This page will close automatically if opened in an iframe.</p>
    </div>
    <script>
        // If this page is loaded inside an iframe (Jotform redirect), notify the parent
        if (window.parent !== window) {
            try {
                window.parent.postMessage({
                    action: 'consent-form-completed',
                    source: 'clinic-consent-webhook',
                    success: true
                }, '*');
            } catch (e) {}
        }
    </script>
</body>
</html>

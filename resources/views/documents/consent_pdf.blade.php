<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Consent Form</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #222; margin: 40px; }
        h1 { color: #2c3e50; border-bottom: 2px solid #2c3e50; padding-bottom: 10px; }
        .box { background: #f8f9fa; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .label { font-weight: bold; color: #555; display: inline-block; width: 160px; }
        .value { color: #000; }
        .row { margin: 10px 0; }
        .footer { margin-top: 40px; font-size: 11px; color: #777; border-top: 1px solid #ddd; padding-top: 15px; }
        .stamp { color: #28a745; font-weight: bold; font-size: 14px; }
    </style>
</head>
<body>
    <h1>Informed Consent Form — Signed</h1>

    <div class="box">
        <p class="stamp">✓ This form has been signed and submitted successfully.</p>

        <div class="row"><span class="label">Patient:</span><span class="value">{{ $patientName }}</span></div>
        <div class="row"><span class="label">Patient Email:</span><span class="value">{{ $patientEmail }}</span></div>
        <div class="row"><span class="label">Doctor:</span><span class="value">{{ $doctorName }}</span></div>
        <div class="row"><span class="label">Appointment ID:</span><span class="value">{{ $appointmentUid }}</span></div>
        <div class="row"><span class="label">Signed On:</span><span class="value">{{ $signedAt }}</span></div>
        @if(!empty($submissionId))
            <div class="row"><span class="label">JotForm Submission:</span><span class="value">#{{ $submissionId }}</span></div>
        @endif
    </div>

    @if(!empty($submittedAnswers))
        <h3>Submitted Responses</h3>
        <div class="box">
            @foreach($submittedAnswers as $key => $value)
                <div class="row">
                    <span class="label">{{ $key }}:</span>
                    <span class="value">{{ is_array($value) ? json_encode($value) : $value }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="footer">
        This document is automatically generated as proof that the patient has completed and submitted the informed consent form
        via the clinic's booking system on {{ $signedAt }}.
        @if(!empty($submissionId))
            Full original submission is available on JotForm with submission ID #{{ $submissionId }}.
        @endif
    </div>
</body>
</html>

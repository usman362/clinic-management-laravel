<!DOCTYPE html>
<html lang="en">
{{--
    CP-44: Real-data consent PDF, rendered from a Jotform submission's
    `answers` array. Used as a fallback when Jotform's hosted PDF
    endpoints (generatePDF / pdf-converter / metadata pdf_url) cannot
    return a filled, signed copy — most commonly on EU/HIPAA accounts
    where /pdf-converter returns the blank form template.

    The view mirrors the layout of the official Jotform PDF:
    - Light header band with form title + submission date
    - Two-column rows of label / value
    - Signature rendered as an inline base64 PNG when present
    - Small Jotform footer line for parity with the official export
--}}
<head>
    <meta charset="UTF-8">
    <title>{{ $formTitle ?? 'Consent Form' }}</title>
    <style>
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2a44; margin: 0; padding: 0; }
        .header {
            background: #f4f5f9;
            padding: 36px 56px 30px;
            border-bottom: 1px solid #e3e6f0;
        }
        .header .date { float: right; font-size: 11px; color: #1f2a44; }
        .header h1 { font-size: 26px; margin: 0 0 6px 0; color: #1f2a44; }
        .header p  { font-size: 12px; margin: 0; color: #4a5276; }
        .body { padding: 36px 56px; }
        .row { margin-bottom: 22px; }
        .row .label {
            font-weight: bold; color: #1f2a44; font-size: 13px;
            display: inline-block; width: 170px; vertical-align: top;
        }
        .row .value { color: #1f2a44; font-size: 13px; display: inline-block; }
        .sig-img { max-width: 220px; max-height: 70px; }
        .footer {
            margin-top: 40px; padding: 0 56px 30px; font-size: 10px; color: #888;
        }
        .footer a { color: #888; text-decoration: none; }
    </style>
</head>
<body>
    <div class="header">
        <span class="date">{{ $submittedAt ?? '' }}</span>
        <h1>{{ $formTitle ?? 'Consent Form' }}</h1>
        @if(!empty($formDescription))
            <p>{{ $formDescription }}</p>
        @endif
    </div>

    <div class="body">
        @forelse($fields as $field)
            <div class="row">
                <span class="label">{{ $field['label'] }}</span>
                <span class="value">
                    @if(! empty($field['signature_data_uri']))
                        <img class="sig-img" src="{{ $field['signature_data_uri'] }}" alt="signature">
                    @else
                        {{ $field['value'] }}
                    @endif
                </span>
            </div>
        @empty
            <p style="color:#888;">No submission fields available.</p>
        @endforelse
    </div>

    <div class="footer">
        Generated from Jotform submission #{{ $submissionId ?? '' }}.
    </div>
</body>
</html>

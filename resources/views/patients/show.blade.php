@extends('layouts.app')
@section('title')
    {{ __('messages.patients') }}
@endsection
@section('header_toolbar')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-7">
            <h1 class="mb-0 me-1">{{ __('messages.patient.details') }}</h1>
            <div class="text-end mt-4 mt-md-0">
                @if (!getLogInUser()->hasRole('doctor'))
                    <a href="{{ route('patients.edit', $patient->id) }}">
                        <button type="button" class="btn btn-primary me-4">{{ __('messages.common.edit') }}</button>
                    </a>
                @endif
                <a href="{{ url()->previous() }}">
                    <button type="button" class="btn btn-outline-primary float-end">{{ __('messages.common.back') }}</button>
                </a>
            </div>
        </div>
    </div>
    <style>
        .be-comment-block {
            margin-bottom: 50px !important;
            border: 1px solid #edeff2;
            border-radius: 2px;
            padding: 50px 70px;
            border: 1px solid #ffffff;
        }

        .comments-title {
            font-size: 16px;
            color: #262626;
            margin-bottom: 15px;
            font-family: 'Conv_helveticaneuecyr-bold';
        }

        .be-img-comment {
            width: 60px;
            height: 60px;
            float: left;
            margin-bottom: 15px;
        }

        .be-ava-comment {
            width: 60px;
            height: 60px;
            border-radius: 50%;
        }

        .be-comment-content {
            margin-left: 80px;
        }

        .be-comment-content span {
            display: inline-block;
            width: 49%;
            margin-bottom: 15px;
        }

        .be-comment-name {
            font-size: 13px;
            font-family: 'Conv_helveticaneuecyr-bold';
        }

        .be-comment-content a {
            color: #383b43;
        }

        .be-comment-content span {
            display: inline-block;
            width: 49%;
            margin-bottom: 15px;
        }

        .be-comment-time {
            text-align: right;
        }

        .be-comment-time {
            font-size: 11px;
            color: #b4b7c1;
        }

        .be-comment-text {
            font-size: 13px;
            line-height: 18px;
            color: #7a8192;
            display: block;
            background: #f6f6f7;
            border: 1px solid #edeff2;
            padding: 15px 20px 20px 20px;
        }

        .form-group.fl_icon .icon {
            position: absolute;
            top: 1px;
            left: 16px;
            width: 48px;
            height: 48px;
            background: #f6f6f7;
            color: #b5b8c2;
            text-align: center;
            line-height: 50px;
            -webkit-border-top-left-radius: 2px;
            -webkit-border-bottom-left-radius: 2px;
            -moz-border-radius-topleft: 2px;
            -moz-border-radius-bottomleft: 2px;
            border-top-left-radius: 2px;
            border-bottom-left-radius: 2px;
        }

        .form-group .form-input {
            font-size: 13px;
            line-height: 50px;
            font-weight: 400;
            color: #b4b7c1;
            width: 100%;
            height: 50px;
            padding-left: 20px;
            padding-right: 20px;
            border: 1px solid #edeff2;
            border-radius: 3px;
        }

        .form-group.fl_icon .form-input {
            padding-left: 70px;
        }

        .form-group textarea.form-input {
            height: 150px;
        }
    </style>
@endsection
@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-column">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-xxl-5 col-12">
                            <div class="d-sm-flex align-items-center mb-5 mb-xxl-0 text-center text-sm-start">
                                <div class="image image-circle image-lg-small">
                                    <img src="{{ $patient->profile }}" alt="user">
                                </div>
                                <div class="ms-0 ms-md-10 mt-5 mt-sm-0  ">
                                    <span class="text-success mb-2 d-block">{{ $patient->user->role_name }}</span>
                                    <h2>{{ $patient->user->full_name }}</h2>
                                    <a href="mailto:{{ $patient->user->email }}"
                                        class="text-gray-600 text-decoration-none fs-4">
                                        {{ $patient->user->email }}
                                    </a><br>
                                    @if ($patient->user->contact != null)
                                        <a href="tel:{{ $patient->user->region_code }} {{ $patient->user->contact }}"
                                            class="text-gray-600 text-decoration-none fs-4">
                                            {{ !empty($patient->user->contact) ? '+' . $patient->user->region_code . ' ' . $patient->user->contact : __('messages.common.n/a') }}
                                        </a>
                                    @else
                                        <a class="text-gray-600 text-decoration-none fs-4">
                                            {{ __('messages.common.n/a') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-7 col-12">
                            <div class="row justify-content-center">
                                <div class="col-md-4 col-sm-6 col-12 mb-6 mb-md-0">
                                    <div class="border rounded-10 p-5 h-100">
                                        <h2 class="text-primary mb-3">{{ $data['todayAppointmentCount'] }}</h2>
                                        <h3 class="fs-5 fw-light text-gray-600 mb-0">
                                            {{ __('messages.patient_dashboard.today_appointments') }}</h3>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6 col-12 mb-6 mb-md-0">
                                    <div class="border rounded-10 p-5 h-100">
                                        <h2 class="text-primary mb-3">{{ $data['upcomingAppointmentCount'] }}</h2>
                                        <h3 class="fs-5 fw-light text-gray-600 mb-0">
                                            {{ __('messages.patient_dashboard.upcoming_appointments') }}</h3>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6 col-12">
                                    <div class="border rounded-10 p-5 h-100">
                                        <h2 class="text-primary mb-3">{{ $data['completedAppointmentCount'] }}</h2>
                                        <h3 class="fs-5 fw-light text-gray-600 mb-0">
                                            {{ __('messages.patient_dashboard.completed_appointments') }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @php
                $comments = \App\Models\PatientComment::with(['patient', 'owner'])
                    ->where('patient_id', $patient->id)
                    ->get();
            
                // AP-02/CP-12 fix: Documents are saved with user_id = Patient's User ID,
                // not Patient ID. Query with $patient->id previously missed all records.
                $documents = \App\Models\Document::with(['user', 'owner'])
                    ->where('user_id', $patient->user_id)
                    ->orderByDesc('created_at')
                    ->get();
            
                $activeTab = request('tab', 'overview');
            @endphp
            <div class="mt-7">
                <ul class="nav nav-tabs mb-sm-7 mb-5 pb-1 overflow-auto flex-nowrap text-nowrap" id="myTab"
                    role="tablist">
                    <li class="nav-item position-relative me-7 mb-3" role="presentation">
                        <button class="nav-link p-0 {{ $activeTab === 'overview' ? 'active' : '' }}" id="overview-tab" data-bs-toggle="tab"
                            data-bs-target="#overview" type="button" role="tab" aria-controls="overview"
                            aria-selected="true">
                            {{ __('messages.common.overview') }}
                        </button>
                    </li>
                    <li class="nav-item position-relative me-7 mb-3" role="presentation">
                        <button class="nav-link p-0 {{ $activeTab === 'appointments' ? 'active' : '' }}" id="appointments-tab" data-bs-toggle="tab"
                            data-bs-target="#appointments" type="button" role="tab" aria-controls="appointments"
                            aria-selected="false">
                            {{ __('messages.appointments') }}
                        </button>
                    </li>
                    <li class="nav-item position-relative me-7 mb-3" role="presentation">
                        <button class="nav-link p-0 {{ $activeTab === 'comments' ? 'active' : '' }}" id="comments-tab" data-bs-toggle="tab" data-bs-target="#comments"
                            type="button" role="tab" aria-controls="comments" aria-selected="false">
                            {{ __('Comments') }}({{ $comments->count() }})
                        </button>
                    </li>
                    <li class="nav-item position-relative me-7 mb-3" role="presentation">
                        <button class="nav-link p-0 {{ $activeTab === 'documents' ? 'active' : '' }}" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents"
                            type="button" role="tab" aria-controls="documents" aria-selected="false">
                            {{ __('Documents') }}
                        </button>
                    </li>
                </ul>
                <div class="tab-content" id="myTabContent">
                    <div class="tab-pane fade {{ $activeTab === 'overview' ? 'show active' : '' }}" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    {{ Form::hidden('patient_role', getLogInUser()->hasRole('patient'), ['id' => 'patientRolePatientDetail']) }}
                                    @include('patients.show_fields')
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade {{ $activeTab === 'appointments' ? 'show active' : '' }}" id="appointments" role="tabpanel" aria-labelledby="appointments-tab">
                        <livewire:patient-show-page-appointment-table :patientId="$patient->id" />
                    </div>
                    <div class="tab-pane fade {{ $activeTab === 'comments' ? 'show active' : '' }}" id="comments" role="tabpanel" aria-labelledby="comments-tab">
                        <div class="be-comment-block">
                            @forelse ($comments as $comment)
                                <div class="be-comment">
                                    <div class="be-img-comment">
                                        <a href="blog-detail-2.html">
                                            <img src="https://cdn-icons-png.flaticon.com/512/6596/6596121.png"
                                                alt="" class="be-ava-comment">
                                        </a>
                                    </div>
                                    <div class="be-comment-content">
                                        <span class="be-comment-name">
                                            <a
                                                href="blog-detail-2.html">{{ $comment->owner->first_name . ' ' . $comment->owner->last_name }}</a>
                                        </span>
                                        <span class="be-comment-time">
                                            <i class="fa fa-clock-o"></i>
                                            {{ \Carbon\Carbon::parse($comment->created_at)->format('D m Y H:i:s') }}
                                        </span>

                                        <p class="be-comment-text">
                                            {{ $comment->comments }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                <p class="be-comment-text">
                                    Not Comments Yet
                                </p>
                            @endforelse
                            @if (!getLogInUser()->hasRole('patient'))
                                <form action="{{ route('store.appointment_comments', $patient->id) }}" class="form-block"
                                    method="POST">
                                    @csrf
                                    <div class="row">
                                        <div class="col-xs-12">
                                            <div class="form-group">
                                                <textarea class="form-input" name="comments" required="" placeholder="Your Comment"></textarea>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary pull-right">Submit</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>
                    <div class="tab-pane fade {{ $activeTab === 'documents' ? 'show active' : '' }}" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Documents</h5>
                            </div>

                            <div class="card-body">

                                <!-- Upload Form -->
                                <form action="{{route('documents.store',$patient->id)}}" method="POST"
                                    enctype="multipart/form-data" class="mb-4">
                                    @csrf

                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label">Document Title</label>
                                            <input type="text" name="title" class="form-control"
                                                placeholder="e.g. Passport">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Document File</label>
                                            <input type="file" name="file" class="form-control"
                                                accept=".pdf,.jpg,.jpeg,.png" required>
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">Type</label>
                                            <select name="type" class="form-select">
                                                <option value="identity">Identity</option>
                                                <option value="medical">Medical</option>
                                                <option value="consent">Consent</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>

                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-primary w-100">
                                                Upload
                                            </button>
                                        </div>
                                    </div>
                                </form>

                                <!-- Documents Table -->
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Title</th>
                                                <th>Type</th>
                                                <th>Uploaded At</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($documents as $doc)
                                                <tr>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>{{ $doc->title ?? 'Test Document' }}</td>
                                                    <td>{{ ucfirst($doc->type ?? 'Other') }}</td>
                                                    <td>{{ $doc->created_at->format('d M Y') }}</td>
                                                    <td class="text-center d-flex">
                                                        @php
                                                            // CP-12 V8: Build correct download URL for public disk files.
                                                            // Documents saved via Storage::disk('public') live at storage/app/public/{path}
                                                            // and are served via the `storage/...` symlink.
                                                            $docPath = $doc->path ?? '';
                                                            if ($docPath && \Illuminate\Support\Str::startsWith($docPath, 'jotform:')) {
                                                                // Legacy records that never got a real file
                                                                $downloadUrl = null;
                                                                $externalLabel = 'JotForm #' . substr($docPath, 8);
                                                            } elseif ($doc->type === 'consent') {
                                                                // CP-33: Route consent downloads through the smart endpoint
                                                                // so each click can lazy-refresh the official Jotform PDF.
                                                                $downloadUrl = route('documents.download', $doc->id);
                                                            } elseif ($docPath) {
                                                                $downloadUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($docPath);
                                                            } else {
                                                                $downloadUrl = null;
                                                                $externalLabel = null;
                                                            }
                                                        @endphp
                                                        @if($downloadUrl)
                                                            @if($doc->type === 'consent')
                                                                {{-- CP-38: Consent download hits Jotform API and may take
                                                                    several seconds. Show a spinner so the user doesn't
                                                                    spam-click the button. --}}
                                                                <a href="{{ $downloadUrl }}"
                                                                    class="btn btn-sm btn-outline-primary mx-2 js-consent-download"
                                                                    data-url="{{ $downloadUrl }}"
                                                                    data-filename="consent_{{ $doc->id }}.pdf">
                                                                    <span class="btn-label">Download</span>
                                                                </a>
                                                            @else
                                                                <a href="{{ $downloadUrl }}" target="_blank"
                                                                    class="btn btn-sm btn-outline-primary mx-2" download>
                                                                    Download
                                                                </a>
                                                            @endif
                                                        @elseif(!empty($externalLabel))
                                                            <span class="btn btn-sm btn-outline-secondary mx-2 disabled"
                                                                  title="Original submission stored on JotForm — reference ID shown">
                                                                {{ $externalLabel }}
                                                            </span>
                                                        @else
                                                            <span class="btn btn-sm btn-outline-secondary mx-2 disabled">No file</span>
                                                        @endif
                                                        <form method="POST"
                                                            action="{{ route('documents.destroy', $doc->id) }}"
                                                            >
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="btn btn-sm btn-danger">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">
                                                        No documents uploaded yet
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- CP-38: Consent download spinner. Fetches the PDF as a blob so the
         button stays in "downloading" state until the file actually arrives,
         then triggers a save. Prevents spam-clicking while Jotform's signed
         PDF takes a few seconds to fetch on the backend. --}}
    <script>
        (function () {
            document.querySelectorAll('.js-consent-download').forEach(function (btn) {
                btn.addEventListener('click', async function (e) {
                    e.preventDefault();
                    if (btn.dataset.busy === '1') return;
                    btn.dataset.busy = '1';

                    var label  = btn.querySelector('.btn-label');
                    var orig   = label ? label.textContent : 'Download';
                    var origHt = btn.innerHTML;

                    btn.classList.add('disabled');
                    btn.style.pointerEvents = 'none';
                    btn.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
                        '<span>Preparing…</span>';

                    try {
                        var url = btn.dataset.url;
                        var resp = await fetch(url, {
                            credentials: 'same-origin',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!resp.ok) throw new Error('HTTP ' + resp.status);

                        var blob = await resp.blob();
                        var disp = resp.headers.get('Content-Disposition') || '';
                        var m    = /filename\*?=(?:UTF-8'')?["']?([^"';]+)/i.exec(disp);
                        var name = (m && m[1]) ? decodeURIComponent(m[1]) : (btn.dataset.filename || 'document.pdf');

                        var blobUrl = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = blobUrl;
                        a.download = name;
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        setTimeout(function () { URL.revokeObjectURL(blobUrl); }, 5000);
                    } catch (err) {
                        console.error('Consent download failed', err);
                        alert('Download failed. Please try again.');
                    } finally {
                        btn.innerHTML = origHt;
                        if (btn.querySelector('.btn-label')) {
                            btn.querySelector('.btn-label').textContent = orig;
                        }
                        btn.classList.remove('disabled');
                        btn.style.pointerEvents = '';
                        btn.dataset.busy = '0';
                    }
                });
            });
        })();
    </script>
@endsection

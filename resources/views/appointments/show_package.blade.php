@extends('layouts.app')
@section('title')
    {{__('Package Details > Appointments')}}
@endsection
@section('content')
    <div class="container-fluid">
        @include('flash::message')
        @php
            $relationId = $appointment['data']['relation_id'] ?? null;
            $package = $relationId
                ? \App\Models\Package::where('relation_id', $relationId)
                    ->where('appointment_type', 'assessment')
                    ->first()
                : null;

            // Multi-send feedback support
            $canSendFeedback   = false;
            $doctorStatusMap   = [];
            $assessmentDoctors = collect();
            $remainingDoctorIds = [];
            $preSelectedDoctorIds = [];
            $needsConfirm      = false;

            if ($package) {
                $doctorStatusMap      = $package->getDoctorFeedbackStatusMap();
                $remainingDoctorIds   = $package->getDoctorIdsWithoutFeedback();
                $canSendFeedback      = ! empty($remainingDoctorIds);
                $assessmentDoctors    = \App\Models\Doctor::with('user')
                                            ->whereIn('id', $package->getAssessmentDoctorIds())
                                            ->get();
                $preSelectedDoctorIds = array_map('intval', (array) session('feedback_selected_docs', []));
                $needsConfirm         = (int) session('feedback_needs_confirm') === (int) $package->id;
            }

            $isPatient = getLogInUser()->hasRole('patient');
        @endphp

        <div class="d-flex justify-content-between align-items-end mb-5">
            <h1>@yield('title')</h1>
            <div class="d-flex gap-2">
                @if($package && !$isPatient)
                    @if($canSendFeedback)
                        <button type="button" class="btn btn-warning"
                                data-bs-toggle="modal" data-bs-target="#sendFeedbackModal">
                            <i class="fas fa-paper-plane me-1"></i> Send Feedback Package
                        </button>
                    @else
                        <span class="btn btn-success btn-sm disabled" title="Feedback sent to every doctor in this package">
                            <i class="fas fa-check me-1"></i> Feedback Sent (All Doctors)
                        </span>
                    @endif
                @endif
                <a class="btn btn-outline-primary"
                   href="{{ url()->previous() }}">{{ __('messages.common.back') }}</a>
            </div>
        </div>

        <livewire:package-appointments-table :relationId="$appointment['data']['relation_id']"/>
    </div>

    {{-- Send Feedback Package Modal --}}
    @if($package && $canSendFeedback && !$isPatient)
        <div class="modal fade" id="sendFeedbackModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('feedback.send-from-package', $package->id) }}">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-paper-plane me-1 text-warning"></i>
                                Send Feedback Package
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small mb-4">
                                Select the doctor(s) who should receive a feedback schedule for this patient.
                                Each selection creates a new feedback package containing only the chosen doctors.
                                You can send feedback to remaining doctors later in separate batches.
                            </p>

                            @if($assessmentDoctors->whereIn('id', $remainingDoctorIds)->count() > 1)
                                <div class="form-check mb-3 border-bottom pb-3">
                                    <input class="form-check-input" type="checkbox" id="selectAllFeedbackDocs">
                                    <label class="form-check-label fw-bold" for="selectAllFeedbackDocs">
                                        Select all remaining doctors
                                    </label>
                                </div>
                            @endif

                            @foreach($assessmentDoctors as $doctor)
                                @php
                                    $docStatus = $doctorStatusMap[$doctor->id] ?? null;
                                    $alreadySent = $docStatus !== null;
                                    $preChecked  = in_array((int) $doctor->id, $preSelectedDoctorIds, true);
                                @endphp
                                <div class="form-check mb-2 py-1">
                                    <input class="form-check-input feedback-doc-checkbox"
                                           type="checkbox"
                                           name="doctor_ids[]"
                                           value="{{ $doctor->id }}"
                                           id="fbDoc_{{ $doctor->id }}"
                                           {{ $alreadySent ? 'disabled' : '' }}
                                           {{ $preChecked && ! $alreadySent ? 'checked' : '' }}>
                                    <label class="form-check-label d-flex justify-content-between align-items-center w-100"
                                           for="fbDoc_{{ $doctor->id }}">
                                        <span>
                                            <i class="fas fa-user-md me-1 text-muted"></i>
                                            {{ $doctor->user->full_name ?? 'Doctor #' . $doctor->id }}
                                        </span>
                                        @if($docStatus === 'completed')
                                            <span class="badge bg-success">Completed</span>
                                        @elseif($docStatus === 'booked')
                                            <span class="badge bg-info text-dark">Booked</span>
                                        @elseif($docStatus === 'sent')
                                            <span class="badge bg-warning text-dark">Feedback Sent</span>
                                        @else
                                            <span class="badge bg-light text-dark">Pending</span>
                                        @endif
                                    </label>
                                </div>
                            @endforeach

                            @if($needsConfirm)
                                <div class="alert alert-warning mt-3 mb-0 small">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Some assessment appointments for the selected doctor(s) are not yet completed.
                                    Tick the option below to proceed anyway.
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirmSend">
                                    <label class="form-check-label" for="confirmSend">
                                        Send anyway even though assessments aren't complete
                                    </label>
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-warning" id="submitFeedbackBtn" disabled>
                                <i class="fas fa-paper-plane me-1"></i> Send Feedback
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
            (function () {
                function bindFeedbackModal() {
                    var modalEl = document.getElementById('sendFeedbackModal');
                    if (!modalEl || modalEl.dataset.bound === '1') return;
                    modalEl.dataset.bound = '1';

                    var selectAll  = modalEl.querySelector('#selectAllFeedbackDocs');
                    var checkboxes = modalEl.querySelectorAll('.feedback-doc-checkbox:not([disabled])');
                    var submitBtn  = modalEl.querySelector('#submitFeedbackBtn');

                    function updateSubmitState() {
                        var any = Array.prototype.some.call(checkboxes, function (cb) { return cb.checked; });
                        submitBtn.disabled = !any;
                        if (selectAll) {
                            var all = checkboxes.length > 0 && Array.prototype.every.call(checkboxes, function (cb) { return cb.checked; });
                            selectAll.checked = all;
                        }
                    }

                    if (selectAll) {
                        selectAll.addEventListener('change', function () {
                            Array.prototype.forEach.call(checkboxes, function (cb) { cb.checked = selectAll.checked; });
                            updateSubmitState();
                        });
                    }
                    Array.prototype.forEach.call(checkboxes, function (cb) {
                        cb.addEventListener('change', updateSubmitState);
                    });

                    updateSubmitState();

                    @if($needsConfirm)
                        // Auto-reopen modal after admin was bounced with "needs confirm"
                        try {
                            new bootstrap.Modal(modalEl).show();
                        } catch (e) { /* bootstrap not ready */ }
                    @endif
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bindFeedbackModal);
                } else {
                    bindFeedbackModal();
                }
                // Turbo-friendly
                document.addEventListener('turbo:load', bindFeedbackModal);
            })();
        </script>
    @endif
@endsection

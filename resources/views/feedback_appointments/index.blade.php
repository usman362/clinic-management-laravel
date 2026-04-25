@extends('layouts.app')
@section('title')
    {{ __('Feedback Packages') }}
@endsection
@section('content')
    <div class="container-fluid">
        @include('flash::message')

        {{-- AP-05 V8: Instructions panel (admin/staff only).
             Uses localStorage to remember dismissal — independent of Turbo cache,
             Livewire morph, or page navigation. Loads BEFORE the Livewire table
             so it can't be touched by component re-renders.

             Wrapped in @once so any duplicate rendering wouldn't cause flicker. --}}
        @if(!getLogInUser()->hasRole('patient'))
            {{-- AP-18: Suppress the flash for users who previously dismissed
                 the banner. Without this guard, the HTML rendered with
                 `display:block` for ~1 second before applyState() ran and
                 hid it again — visible flicker. By injecting a CSS rule
                 synchronously *before* the banner element parses (via a
                 plain inline script + document.write of a <style> tag),
                 the browser never paints the banner for dismissed users. --}}
            <script>
                (function () {
                    try {
                        if (localStorage.getItem('feedbackInstructionsDismissed') === '1') {
                            document.write('<style>#feedbackInstructionsBox{display:none !important;}</style>');
                        }
                    } catch (e) { /* localStorage disabled — fall through to default visible */ }
                })();
            </script>
            {{-- Banner is marked `alert-important` so the global
                 `alertInitialize()` (which auto-slides any `.alert` after
                 a few seconds) never touches it, and inline opacity:1
                 overrides any framework CSS that tries to fade sibling
                 elements during Livewire morphs. The JS below only wires
                 the manual close button + handles state changes. --}}
            <div id="feedbackInstructionsBox"
                 class="alert alert-important bg-info bg-opacity-10 border border-info rounded p-4 position-relative mb-4"
                 role="region" aria-label="Feedback Package Instructions"
                 style="opacity:1 !important;">
                <h5 class="fw-bold mb-2"><i class="fas fa-info-circle me-2"></i>What is a Feedback Package?</h5>
                <p class="mb-2">
                    A <strong>feedback package</strong> is a follow-up appointment set created after an assessment package has been completed.
                    It allows the patient to book a feedback meeting with their doctor(s) to review results and next steps.
                </p>
                <hr>
                <p class="mb-2"><strong>How to create a feedback package:</strong></p>
                <ol class="mb-2">
                    <li>Go to <a href="{{ route('appointments.index') }}"><strong>Packages</strong></a> and open the assessment package you want to follow up on.</li>
                    <li>Click the <strong>"Send Feedback Package"</strong> button on the package details page.</li>
                    <li>The system will create a feedback package linked to that assessment, pre-filled with the same patient and doctor(s).</li>
                    <li>The patient receives a booking link to select their preferred feedback appointment slot.</li>
                </ol>
                <p class="mb-0"><small>Alternatively, click <strong>"Create Feedback Package"</strong> below to create one manually.</small></p>
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3"
                        aria-label="Close"
                        id="feedbackInstructionsCloseBtn"></button>
            </div>

            <script>
                // AP-05: Self-contained banner handler.
                // - Does NOT auto-dismiss.
                // - Honours the user's manual dismissal via localStorage across
                //   page reloads, Turbo navigations and Livewire morphs.
                // - Re-applies the visible state on every navigation, defending
                //   against any global auto-hide code that may slip through.
                (function () {
                    var STORAGE_KEY = 'feedbackInstructionsDismissed';

                    function applyState() {
                        var box = document.getElementById('feedbackInstructionsBox');
                        if (!box) return;
                        var dismissed = false;
                        try { dismissed = (localStorage.getItem(STORAGE_KEY) === '1'); } catch (e) {}
                        if (dismissed) {
                            box.style.setProperty('display', 'none', 'important');
                        } else {
                            // Force visible + fully opaque even if something else
                            // tried to slideUp/fade it.
                            box.style.setProperty('display', 'block', 'important');
                            box.style.setProperty('opacity', '1', 'important');
                            box.style.removeProperty('height');
                            box.style.removeProperty('margin-top');
                            box.style.removeProperty('margin-bottom');
                            box.style.removeProperty('padding-top');
                            box.style.removeProperty('padding-bottom');
                        }
                        var btn = document.getElementById('feedbackInstructionsCloseBtn');
                        if (btn && !btn.dataset.bound) {
                            btn.dataset.bound = '1';
                            btn.addEventListener('click', function () {
                                box.style.setProperty('display', 'none', 'important');
                                try { localStorage.setItem(STORAGE_KEY, '1'); } catch (e) {}
                            });
                        }
                    }

                    applyState();
                    document.addEventListener('DOMContentLoaded', applyState);
                    document.addEventListener('turbo:load',   applyState);
                    document.addEventListener('turbo:render', applyState);
                    if (window.Livewire) {
                        window.Livewire.hook('morph.updated', applyState);
                        window.Livewire.hook('message.processed', applyState);
                    }
                    // Safety net: if ANY script later tries to slideUp/hide the
                    // banner, this interval restores it for the next 10s
                    // (only during that initial page-paint race window).
                    var ticks = 0;
                    var guard = setInterval(function () {
                        ticks++;
                        applyState();
                        if (ticks >= 20) clearInterval(guard); // ~10s @ 500ms
                    }, 500);
                })();
            </script>
        @endif

        <div class="d-flex flex-column">
            {{Form::hidden('patientRole',getLogInUser()->hasRole('patient'),['id' => 'patientRole'])}}
            <livewire:feeback-appointment-table/>
            @include('appointments.models.patient-payment-model')
            @include('appointments.models.change-payment-status-model')
        </div>
    </div>
@endsection

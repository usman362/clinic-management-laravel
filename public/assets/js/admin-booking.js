$(function () {

    const steps = $('.form-step');
    const headers = $('.step-item');
    const progressBar = $('.progress-indicator');
    let currentStep = 0;
    let appointmentIndex = 0;

    function showStep(index) {
        steps.removeClass('active').eq(index).addClass('active');

        headers.removeClass('active completed');
        headers.each(function (i) {
            if (i < index) $(this).addClass('completed');
            if (i === index) $(this).addClass('active');
        });

        progressBar.css('width', (index / (steps.length - 1)) * 100 + '%');
    }

    function notify(msg) {
        $('#notification').text(msg).addClass('show');
        setTimeout(() => $('#notification').removeClass('show'), 3000);
    }

    /* ── Fetch doctors for a given service and populate the doctor dropdown ──
       `onDone` is optional; it fires once the doctor list is populated (or on
       error). AP-14 draft-restore uses it to chain: set service → wait for
       doctors → set doctor value. */
    function loadDoctorsForService(serviceSelect, onDone) {
        var $section = $(serviceSelect).closest('.appointments-section');
        var $doctorSelect = $section.find('.adminAppointmentDoctorId');
        var $duration = $section.find('.duration');
        var serviceId = $(serviceSelect).val();

        // Reset doctor dropdown
        $doctorSelect.html('<option value="">Select doctor...</option>');

        if (!serviceId) {
            $duration.text('Select a service to view duration');
            if (typeof onDone === 'function') onDone();
            return;
        }

        $.ajax({
            url: '/get-doctors-by-service',
            type: 'GET',
            data: { service_id: serviceId },
            success: function (response) {
                if (response.success && response.data) {
                    var doctors = response.data.doctors;
                    var service = response.data.service;

                    // Populate doctor dropdown
                    if (doctors && doctors.length > 0) {
                        $.each(doctors, function (i, doctor) {
                            $doctorSelect.append(
                                '<option value="' + doctor.id + '">' + doctor.name + '</option>'
                            );
                        });
                    } else {
                        $doctorSelect.html('<option value="">No doctors available for this service</option>');
                    }

                    // Show duration info
                    if (service && service.duration) {
                        $duration.text(service.name + ' — ' + service.duration + ' min');
                    }
                }
                if (typeof onDone === 'function') onDone();
            },
            error: function () {
                $doctorSelect.html('<option value="">Error loading doctors</option>');
                if (typeof onDone === 'function') onDone();
            }
        });
    }

    /* ── Listen for service selection changes (works for dynamic rows too) ── */
    $(document).on('change', '.appointmentServiceId', function () {
        loadDoctorsForService(this);
    });

    /* AP-17: On the feedback-package create wizard, populate the "Parent
       Assessment Package" dropdown when the client is picked. The hidden
       `<input name="appointment_type" value="feedback">` baked into that
       blade is our trigger — no need to scope by URL. The dropdown is
       absent on the assessment wizard so this code is a no-op there.

       AP-19: Render each option as `Package title  [Status badge]` via a
       Select2 templateResult that reads the package status off
       data-attributes on the option element. */
    function escapeHtml(s) {
        return $('<div/>').text(s == null ? '' : String(s)).html();
    }

    function renderPackageOption(opt) {
        if (!opt.id) {
            return opt.text; // placeholder / "no eligible packages" rows pass through
        }
        var $el = $(opt.element);
        var statusLabel = $el.attr('data-status-label') || '';
        var badgeClass  = $el.attr('data-badge-class') || 'bg-light text-dark';
        if (!statusLabel) {
            return $('<span/>').text(opt.text);
        }
        // Inline-flex keeps text and badge aligned across long labels.
        return $(
            '<span class="d-inline-flex align-items-center justify-content-between w-100">' +
                '<span class="me-2">' + escapeHtml(opt.text) + '</span>' +
                '<span class="badge ' + badgeClass + '" style="white-space:nowrap;">' +
                    escapeHtml(statusLabel) +
                '</span>' +
            '</span>'
        );
    }

    function ensureParentPackageSelect2() {
        var $sel = $('#parent_package_id');
        if (!$sel.length) return null;
        if (!$sel.hasClass('select2-hidden-accessible')) {
            $sel.select2({
                width: '100%',
                placeholder: 'Select an assessment package…',
                templateResult: renderPackageOption,
                templateSelection: renderPackageOption,
                escapeMarkup: function (m) { return m; },
            });
        }
        return $sel;
    }

    function loadParentPackagesForClient(patientId) {
        var $sel = $('#parent_package_id');
        if (!$sel.length) return; // Field only exists on the feedback wizard.
        ensureParentPackageSelect2();

        function setOptions(html, disabled) {
            $sel.html(html).prop('disabled', !!disabled);
            // Repaint Select2 so the new options + their data-attrs are picked up.
            if ($sel.hasClass('select2-hidden-accessible')) {
                $sel.trigger('change.select2');
            }
        }

        if (!patientId) {
            setOptions('<option value="">Select a client first</option>', true);
            return;
        }
        setOptions('<option value="">Loading…</option>', true);

        $.ajax({
            url: '/get-patient-assessment-packages',
            type: 'GET',
            data: { patient_id: patientId },
            success: function (response) {
                var packages = (response && response.data && response.data.packages) || [];
                if (!packages.length) {
                    setOptions('<option value="">No eligible assessment packages — create one first or use "Send Feedback Package" from a completed package</option>', true);
                    return;
                }
                var html = '<option value="">Select an assessment package…</option>';
                packages.forEach(function (p) {
                    html += '<option value="' + p.id + '"'
                        + ' data-status="' + escapeHtml(p.status) + '"'
                        + ' data-status-label="' + escapeHtml(p.status_label) + '"'
                        + ' data-badge-class="' + escapeHtml(p.status_badge_class) + '"'
                        + '>' + escapeHtml(p.label) + '</option>';
                });
                setOptions(html, false);
            },
            error: function () {
                setOptions('<option value="">Failed to load packages</option>', true);
            }
        });
    }
    $(document).on('change', '#client_id', function () {
        loadParentPackagesForClient($(this).val());
    });

    /* ── Build a new appointment row dynamically using the services already in the first dropdown ── */
    function buildAppointmentRow(index) {
        // Clone options from the first service dropdown
        var $firstServiceSelect = $('.appointments-section').first().find('.appointmentServiceId');
        var serviceOptions = '<option value="">Select service...</option>';
        $firstServiceSelect.find('option').each(function () {
            if ($(this).val()) {
                serviceOptions += '<option value="' + $(this).val() + '">' + $(this).text() + '</option>';
            }
        });

        return `
            <div class="appointments-section mb-4" data-index="${index}">
                <div class="card-body" style="background-color: #eff3f7;border-radius: 12px;">
                    <div class="row">
                        <div class="col-12 text-end">
                            <button type="button" class="btn btn-sm btn-danger remove-appointment">
                                <svg class="svg-inline--fa fa-trash" aria-hidden="true" focusable="false"
                                    data-prefix="fas" data-icon="trash" role="img"
                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                                    <path fill="currentColor"
                                        d="M135.2 17.69C140.6 6.848 151.7 0 163.8 0H284.2C296.3 0 307.4 6.848 312.8 17.69L320 32H416C433.7 32 448 46.33 448 64C448 81.67 433.7 96 416 96H32C14.33 96 0 81.67 0 64C0 46.33 14.33 32 32 32H128L135.2 17.69zM394.8 466.1C393.2 492.3 372.3 512 346.9 512H101.1C75.75 512 54.77 492.3 53.19 466.1L31.1 128H416L394.8 466.1z">
                                    </path>
                                </svg>
                            </button>
                        </div>
                        <div class="col-lg-6 col-sm-12 mb-5">
                            <label class="form-label required">Service:</label>
                            <select class="io-select2 form-select appointmentServiceId" name="appointments[${index}][service_id]" required>
                                ${serviceOptions}
                            </select>
                        </div>
                        <div class="col-sm-12 col-lg-6 mb-5">
                            <label class="form-label required">Doctor:</label>
                            <select class="io-select2 form-select adminAppointmentDoctorId" name="appointments[${index}][doctor_id]" required>
                                <option value="">Select doctor...</option>
                            </select>
                        </div>
                        <div class="col-sm-12">
                            <h5>Duration</h5>
                            <h5 class="duration">Select a service to view duration</h5>
                            <p>Duration is defined by the service selection.</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /* ADD APPOINTMENT ROW
       AP-09: Two sources bind a click on #addAppointment — this script and a
       legacy inline handler in layouts/app.blade.php that clones the first
       row. Both firing produced TWO rows per click. `.off('click')` (no
       namespace) removes ALL existing click handlers on this button so only
       the packages-page handler below runs on this page.

       AP-13: On the EDIT page, the wrapper already contains N rendered rows
       with data-index 0..N-1 and form names `appointments[0..N-1][...]`.
       A closure counter starting at 0 would produce a NEW row named
       `appointments[1][...]` that COLLIDES with the existing row[1] — PHP
       dedupes the duplicated name on POST, silently dropping one appointment
       from the saved package. Compute the next index from the current DOM
       each click so there is never a collision regardless of add/remove
       history or how many rows the server pre-rendered. */
    function nextAppointmentIndex() {
        var max = -1;
        $('.appointments-section').each(function () {
            var i = parseInt($(this).attr('data-index'), 10);
            if (!isNaN(i) && i > max) { max = i; }
        });
        return max + 1;
    }

    $('#addAppointment').off('click').on('click.addAppointment', function () {
        appointmentIndex = nextAppointmentIndex();
        $('.appointments-wrapper').append(buildAppointmentRow(appointmentIndex));
    });

    /* AP-13: When a row is removed on the EDIT page, its sibling hidden
       `appointments[N][appointment_id]` input (rendered outside the
       section div in older blades) is left orphaned in the DOM. On POST
       the backend sees `appointments[N]` with only an appointment_id and
       no service/doctor — which the repository logs as "missing
       service/doctor, skipping" and silently drops. Remove the sibling
       hidden input alongside the section. */
    $(document).off('click.removeAppointment').on('click.removeAppointment', '.remove-appointment', function () {
        var $section = $(this).closest('.appointments-section');
        var idx = $section.attr('data-index');
        if (idx !== undefined && idx !== '') {
            $section.siblings('input[name^="appointments[' + idx + ']"]').remove();
        }
        $section.remove();
    });

    /* NEXT / PREV */
    $(document).off('click.nextBtn').on('click.nextBtn', '.next-btn', function () {

        if (currentStep === 0 && !$('#client_id').val()) {
            notify('Please select a client');
            return;
        }
        // AP-17: On the feedback-package wizard, require a parent assessment
        // package before advancing past step 1. Field is absent on the
        // assessment wizard so this is a no-op there.
        if (currentStep === 0 && $('#parent_package_id').length && !$('#parent_package_id').val()) {
            notify('Please select the underlying assessment package');
            return;
        }

        if (currentStep === 1) {
            let valid = true;
            $('.appointments-section').each(function () {
                if (!$(this).find('.appointmentServiceId').val() || !$(this).find('.adminAppointmentDoctorId').val()) {
                    valid = false;
                }
            });
            if (!valid || $('.appointments-section').length === 0) {
                notify('Add at least one valid appointment');
                return;
            }

            // Build review
            let html = `
                        <div class="card-body" style="border: 1px solid #d9d9d9;border-radius: 12px; padding:1.5rem">
                            <h3>Client Information</h3>
                                <div class="row mt-2">
                                    <div class="col-md-6">Name:</div>
                                    <div class="col-md-6">${$('#client_id option:selected').text()}</div>
                                </div>
                        </div>
            `;
            html += `<div class="card-body mt-4" style="border: 1px solid #d9d9d9;border-radius: 12px; padding:1.5rem">
                    <h3>Required Appointments</h3>`
            $('.appointments-section').each(function (i) {
                html +=
                `
                    <div class="row mt-2">
                        <div class="col-md-2">
                            <div class="mt-2" style="background-color: black;color: white;font-weight: bold;border-radius: 40px;
                                        width: 44px;height: 44px;text-align: center;padding-top: 11px;">${i + 1}</div>
                        </div>
                        <div class="col-md-10"><p class="mt-4">${$(this).find('.appointmentServiceId option:selected').text()} —
                    ${$(this).find('.adminAppointmentDoctorId option:selected').text()}</p></div>
                    </div>
                `;
            });
            html += `</div>`;

            html += `
                    <div class="card-body mt-4" style="border: 1px solid #d9d9d9;border-radius: 12px; padding:1.5rem">
                        <h3>Internal Notes</h3>
                            <div class="row mt-2">
                                <p>${$('#internal_notes').val() || 'No notes added.'}</p>
                            </div>
                    </div>
            `;

            html += `
                    <div class="card-body mt-4" style="border: 1px solid #d9d9d9;border-radius: 12px; padding:1.5rem">
                        <h3>What happens on activation?</h3>
                            <div class="row mt-2" style="margin-left:20px">
                                • A unique booking link will be generated <br>
                                • An email will be sent to the client <br>
                                • The client will be asked to create a password on first login <br>
                                • The booking flow will become accessible through their dashboard
                            </div>
                    </div>
            `;
            $('#review-summary').html(html);
        }

        currentStep++;
        showStep(currentStep);
    });

    $(document).off('click.prevBtn').on('click.prevBtn', '.prev-btn', function () {
        currentStep--;
        showStep(currentStep);
    });

    /* ──────────────────────────────────────────────────────────────────────
       AP-14: Draft persistence for admin package + feedback package create
       wizards. The wizard has 3 steps; refreshing (or accidental navigation)
       on step 2 or 3 used to wipe all in-progress data. We persist the form
       state to localStorage on every change and restore it on page load.

       Scope:
         - CREATE only (edit pages render values from the DB — no loss to
           worry about, and restoring a stale draft over real DB state would
           be confusing).
         - Both admin/appointments/create (assessment packages) and
           admin/feedback-appointments/create (feedback packages). Separate
           keys per type so drafts don't bleed between wizards.

       Lifecycle:
         - Save on any form change (debounced 300ms) AND on step navigation.
         - Restore on DOM-ready if a non-expired draft exists.
         - Clear on successful submit.
         - TTL: 24 hours — a stale draft from yesterday is noise, not help.

       Doctor dropdowns are empty until a service triggers the AJAX populate;
       restore therefore chains service → wait → doctor sequentially per row.
       ────────────────────────────────────────────────────────────────────── */
    var DRAFT_TTL_MS      = 24 * 60 * 60 * 1000;
    var DRAFT_KEY_PREFIX  = 'adminPkgDraft:';
    var isRestoringDraft  = false;

    function isCreatePage() {
        // Admin create routes end in /appointments/create or
        // /feedback-appointments/create. Edit routes look like
        // /appointments/{id}/edit — don't persist drafts there.
        return /\/(appointments|feedback-appointments)\/create\/?$/.test(window.location.pathname);
    }

    function draftKey() {
        var type = window.location.pathname.indexOf('feedback-appointments') !== -1
            ? 'feedback' : 'package';
        return DRAFT_KEY_PREFIX + type;
    }

    function collectDraft() {
        var rows = [];
        $('.appointments-section').each(function () {
            rows.push({
                service_id: $(this).find('.appointmentServiceId').val() || '',
                doctor_id:  $(this).find('.adminAppointmentDoctorId').val() || ''
            });
        });
        return {
            v: 1,
            savedAt: Date.now(),
            step: currentStep,
            client_id: $('#client_id').val() || '',
            description: $('#internal_notes').val() || '',
            appointments: rows
        };
    }

    function hasMeaningfulDraft(d) {
        if (!d) return false;
        if (d.client_id) return true;
        if (d.description) return true;
        if (d.step && d.step > 0) return true;
        if (d.appointments && d.appointments.some(function (r) { return r.service_id || r.doctor_id; })) return true;
        return false;
    }

    function saveDraft() {
        if (!isCreatePage() || isRestoringDraft) return;
        try {
            var d = collectDraft();
            if (!hasMeaningfulDraft(d)) {
                localStorage.removeItem(draftKey());
                return;
            }
            localStorage.setItem(draftKey(), JSON.stringify(d));
        } catch (e) { /* quota exceeded / disabled — ignore */ }
    }

    function clearDraft() {
        try { localStorage.removeItem(draftKey()); } catch (e) {}
    }

    // Debounce tiny helper — avoids a write on every keystroke in the notes field.
    function debounce(fn, wait) {
        var t;
        return function () {
            var ctx = this, args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, wait);
        };
    }
    var saveDraftDebounced = debounce(saveDraft, 300);

    /* Restore chain: rows are filled one at a time. For each row with a
       saved service, we set the value, call loadDoctorsForService() and
       only set the doctor once its AJAX populate completes. */
    function restoreRow(idx, rows, done) {
        if (idx >= rows.length) { done(); return; }
        var $section = $('.appointments-section').eq(idx);
        var r = rows[idx] || {};
        if (!r.service_id) { restoreRow(idx + 1, rows, done); return; }
        var $svc = $section.find('.appointmentServiceId');
        $svc.val(r.service_id);
        loadDoctorsForService($svc[0], function () {
            if (r.doctor_id) {
                $section.find('.adminAppointmentDoctorId').val(r.doctor_id);
            }
            restoreRow(idx + 1, rows, done);
        });
    }

    function restoreDraft() {
        // Always settle on SOMETHING for the initial step first. If there's
        // no (usable) draft, we fall through with the default step 0 already
        // painted. If there is one, step 0 shows briefly while we async-
        // populate doctors, then the callback flips to the saved step.
        showStep(0);

        if (!isCreatePage()) return;
        var raw;
        try { raw = localStorage.getItem(draftKey()); } catch (e) { return; }
        if (!raw) return;

        var data;
        try { data = JSON.parse(raw); } catch (e) { clearDraft(); return; }
        if (!data || (Date.now() - (data.savedAt || 0)) > DRAFT_TTL_MS) {
            clearDraft();
            return;
        }
        if (!hasMeaningfulDraft(data)) { clearDraft(); return; }

        isRestoringDraft = true;

        // Simple fields first.
        if (data.client_id) {
            // If Select2 is bound on #client_id it listens to .trigger('change').
            $('#client_id').val(data.client_id).trigger('change');
        }
        if (data.description) {
            $('#internal_notes').val(data.description);
        }

        // Ensure we have enough row sections before filling values.
        var rows = data.appointments || [];
        while ($('.appointments-section').length < rows.length) {
            appointmentIndex = nextAppointmentIndex();
            $('.appointments-wrapper').append(buildAppointmentRow(appointmentIndex));
        }

        restoreRow(0, rows, function () {
            // Jump to the saved step (clamped to the valid range).
            var maxStep = steps.length - 1;
            var target  = Math.max(0, Math.min(maxStep, parseInt(data.step, 10) || 0));

            if (target === 2) {
                // The review panel is rendered by the next-btn handler's
                // step-1 → step-2 branch. Position ourselves at step 1
                // (Services) and fire the next-btn so validation runs
                // and the review HTML is (re)built. If any row is
                // invalid the handler will halt at step 1, which is the
                // right place for the user to fix it — don't force
                // them onto a blank review.
                currentStep = 1;
                showStep(currentStep);
                $('.next-btn').first().trigger('click');
            } else {
                currentStep = target;
                showStep(currentStep);
            }

            isRestoringDraft = false;
        });
    }

    // Persist on any form change (select, textarea, input inside the wizard form).
    $(document).off('change.pkgDraft input.pkgDraft')
        .on('change.pkgDraft input.pkgDraft', '#addAppointmentForm select, #addAppointmentForm textarea, #addAppointmentForm input', function () {
            saveDraftDebounced();
        });

    // Persist on Add / Remove row and on step navigation.
    $(document).off('click.pkgDraftAdd').on('click.pkgDraftAdd', '#addAppointment, .remove-appointment, .next-btn, .prev-btn', function () {
        // Defer one tick so the DOM change (row append/remove, step flip) is in place first.
        setTimeout(saveDraft, 0);
    });

    // Clear the draft the moment the form actually submits. If the server
    // rejects and sends the admin back, they lose the in-flight values —
    // acceptable tradeoff because validation on this wizard is minimal
    // (required service + doctor already enforced client-side).
    $(document).off('submit.pkgDraft').on('submit.pkgDraft', '#addAppointmentForm', function () {
        clearDraft();
    });

    // Kick off restore. `restoreDraft` itself paints step 0 first so the
    // wizard is never blank, then asynchronously jumps to the saved step
    // once doctor dropdowns are populated.
    restoreDraft();
});

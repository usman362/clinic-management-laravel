(function ($) {

    /* ── Inject available-dates CSS ── */
    var _sid = 'appointment-available-dates-style';
    if (!document.getElementById(_sid)) {
        var _css = document.createElement('style');
        _css.id = _sid;
        _css.textContent = [
            /* Available dates — solid green */
            '.flatpickr-appointment-available-dates .flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay){background:#28a745 !important;color:#fff !important;border-color:#218838 !important;font-weight:600}',
            /* Available dates — hover: darker green */
            '.flatpickr-appointment-available-dates .flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay):hover{background:#1e7e34 !important;color:#fff !important;border-color:#1c7430 !important;box-shadow:0 2px 8px rgba(40,167,69,.45)}',
            /* Selected date — dark green with ring */
            '.flatpickr-appointment-available-dates .flatpickr-day.selected,.flatpickr-appointment-available-dates .flatpickr-day.selected:hover{background:#155724 !important;color:#fff !important;border-color:#155724 !important;box-shadow:0 0 0 3px rgba(40,167,69,.35)}',
            /* Unavailable / disabled dates — red */
            '.flatpickr-appointment-available-dates .flatpickr-day.flatpickr-disabled{background:#fddede !important;color:#c0392b !important;border-color:#f5c6cb !important;text-decoration:line-through;cursor:not-allowed !important;opacity:1 !important}',
            '.flatpickr-appointment-available-dates .flatpickr-day.flatpickr-disabled:hover{background:#f8b4b4 !important;color:#922b21 !important;border-color:#e74c3c !important;cursor:not-allowed !important}',
            /* Today ring */
            '.flatpickr-appointment-available-dates .flatpickr-day.today:not(.flatpickr-disabled){border:2px solid #ffc107 !important}',
            '.flatpickr-appointment-available-dates .flatpickr-day.today.flatpickr-disabled{border:2px solid #e74c3c !important}',
            /* Prev/next month ghost */
            '.flatpickr-appointment-available-dates .flatpickr-day.prevMonthDay,.flatpickr-appointment-available-dates .flatpickr-day.nextMonthDay{background:transparent !important;color:#d5d5d5 !important;border-color:transparent !important;text-decoration:none !important}'
        ].join('');
        document.head.appendChild(_css);
    }

    let currentStep = 0;

    function initAppointmentForm() {

        const $form = $('#addAppointmentForm');
        if (!$form.length) return;

        const $steps = $form.find('.form-step');
        const $headers = $('.step-item');
        const $progressBar = $('.progress-indicator');
        const $notification = $('#notification');

        var draftGetUrl = $form.data('draft-get-url');
        var draftSaveUrl = $form.data('draft-save-url');
        var useDraft = !!(draftGetUrl && draftSaveUrl);
        var bookingMode = ($form.data('booking-mode') || 'edit').toString();
        var profileChild = {
            first_name: $form.data('profile-first-name') || '',
            last_name: $form.data('profile-last-name') || '',
            address: $form.data('profile-address') || '',
            dob: $form.data('profile-dob') || '',
            tax_code: $form.data('profile-tax-code') || '',
            school_name: $form.data('profile-school-name') || '',
            school_grade: $form.data('profile-school-grade') || '',
        };
        var saveDraftTimer;
        var isRestoringDraft = false;

        function getDraftPayload() {
            var form = $form[0];
            var data = {
                currentStep: currentStep,
                first_name: (form.querySelector('#first_name') || {}).value,
                last_name: (form.querySelector('#last_name') || {}).value,
                address: (form.querySelector('#address') || {}).value,
                dob: (form.querySelector('#dob') || {}).value,
                tax_code: (form.querySelector('#tax_code') || {}).value,
                school_name: (form.querySelector('#school_name') || {}).value,
                school_grade: (form.querySelector('#school_grade') || {}).value,
                consentConfirmed: $('#consentConfirmed').is(':checked'),
                assessmentInfoAccepted: $('#assessmentInfoAccepted').is(':checked'),
                paymentAcknowledged: $('#paymentAcknowledged').is(':checked'),
                documentationPolicy: $('#documentationPolicy').is(':checked'),
                appointments: []
            };
            $('.appointments-section').each(function () {
                var $s = $(this);
                data.appointments.push({
                    date: $s.find('.appointmentDate').val(),
                    from_time: $s.find('.timeSlot').val(),
                    to_time: $s.find('.toTime').val()
                });
            });
            return data;
        }

        function saveDraft() {
            if (!useDraft) return;
            var payload = getDraftPayload();
            $.ajax({
                url: draftSaveUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ form_data: payload }),
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' }
            });
        }

        function applyProfileChildToForm() {
            var form = $form[0];
            ['first_name', 'last_name', 'address', 'dob', 'tax_code', 'school_name', 'school_grade'].forEach(function (id) {
                var el = form.querySelector('#' + id);
                if (!el) return;
                if (!el.value && profileChild[id] != null && profileChild[id] !== '') {
                    el.value = profileChild[id];
                }
            });
            updateStep6ChildDetails();
        }

        function updateStep6ChildDetails(data) {
            if (!data) {
                var form = $form[0];
                data = {
                    first_name: (form.querySelector('#first_name') || {}).value,
                    last_name: (form.querySelector('#last_name') || {}).value,
                    address: (form.querySelector('#address') || {}).value,
                    dob: (form.querySelector('#dob') || {}).value,
                    tax_code: (form.querySelector('#tax_code') || {}).value,
                    school_name: (form.querySelector('#school_name') || {}).value,
                    school_grade: (form.querySelector('#school_grade') || {}).value
                };
            }
            var name = [data.first_name, data.last_name].filter(Boolean).join(' ');
            $('.client_name').text(name);
            $('.client_address').text(data.address || '');
            $('.client_dob').text(data.dob || '');
            $('.client_tax_code').text(data.tax_code || '');
            $('.client_school').text(data.school_name || '');
            $('.client_grade').text(data.school_grade || '');
        }

        function applyDraftData(data) {
            if (!data) return;
            try {
                isRestoringDraft = true;
                clearTimeout(saveDraftTimer);

                if (data.currentStep != null && data.currentStep >= 0 && data.currentStep < $steps.length) {
                    currentStep = data.currentStep;
                }
                var form = $form[0];
                ['first_name', 'last_name', 'address', 'dob', 'tax_code', 'school_name', 'school_grade'].forEach(function (id) {
                    var el = form.querySelector('#' + id);
                    if (el && data[id] != null) el.value = data[id];
                });
                if (data.consentConfirmed) $('#consentConfirmed').prop('checked', true);
                if (data.assessmentInfoAccepted) $('#assessmentInfoAccepted').prop('checked', true);
                if (data.paymentAcknowledged) $('#paymentAcknowledged').prop('checked', true);
                if (data.documentationPolicy) $('#documentationPolicy').prop('checked', true);
                updateStep6ChildDetails(data);
                if (data.appointments && data.appointments.length) {
                    var pendingSlots = [];
                    $('.appointments-section').each(function (i) {
                        var d = data.appointments[i];
                        if (!d) return;
                        var $s = $(this);
                        if (d.date) $s.find('.appointmentDate').val(d.date);
                        if (d.from_time) $s.find('.timeSlot').val(d.from_time);
                        if (d.to_time) $s.find('.toTime').val(d.to_time);
                        if (d.date && d.from_time && d.to_time) {
                            $s.find('.date-time').text(d.date + ' ' + d.from_time + '-' + d.to_time);
                            pendingSlots.push({ $section: $s, date: d.date, from_time: d.from_time, to_time: d.to_time });
                        }
                    });
                    if (pendingSlots.length) {
                        pendingSlots.forEach(function (p) {
                            p.$section.find('.appointmentDate').trigger('change');
                        });
                        var elapsed = 0;
                        var interval = setInterval(function () {
                            elapsed += 300;
                            pendingSlots = pendingSlots.filter(function (p) {
                                var $slotData = p.$section.find('.appointment-slot-data');
                                var $slots = $slotData.find('.time-slot');
                                if ($slots.length === 0) return true;
                                p.$section.find('.timeSlot').val(p.from_time);
                                p.$section.find('.toTime').val(p.to_time);
                                p.$section.find('.date-time').text(p.date + ' ' + p.from_time + '-' + p.to_time);
                                var slotId = p.from_time + ' - ' + p.to_time;
                                var slotIdAlt = p.from_time + '-' + p.to_time;
                                function norm(s) { return (s || '').replace(/\s+/g, ' ').trim(); }
                                p.$section.find('.time-slot').removeClass('activeSlot');
                                var $match = p.$section.find('.time-slot[data-id="' + slotId + '"]');
                                if (!$match.length) {
                                    $slots.each(function () {
                                        var id = $(this).attr('data-id') || $(this).data('id') || '';
                                        if (norm(id) === norm(slotId) || norm(id) === norm(slotIdAlt) || (id.indexOf(p.from_time) !== -1 && id.indexOf(p.to_time) !== -1)) {
                                            $match = $(this);
                                            return false;
                                        }
                                    });
                                }
                                if ($match && $match.length) $match.addClass('activeSlot');
                                var dateId = p.$section.find('.date_id').val();
                                if (dateId) $('#' + dateId).text(p.date + ' ' + p.from_time + '-' + p.to_time);
                                return false;
                            });
                            if (pendingSlots.length === 0 || elapsed > 10000) {
                                clearInterval(interval);
                                // Restoration complete: re-enable draft saving and persist current state
                                isRestoringDraft = false;
                                if (useDraft) saveDraft();
                            }
                        }, 300);
                    } else {
                        isRestoringDraft = false;
                    }
                } else {
                    isRestoringDraft = false;
                }
            } catch (e) {
                isRestoringDraft = false;
            }
        }

        function restoreDraft(done) {
            if (!useDraft) {
                // No server-side draft: initialize from profile, and skip details step for rebook mode
                applyProfileChildToForm();
                if (bookingMode === 'rebook') {
                    currentStep = 1;
                }
                if (done) done();
                return;
            }
            $.ajax({
                url: draftGetUrl,
                method: 'GET',
                dataType: 'json',
                headers: { 'Accept': 'application/json' }
            }).done(function (res) {
                var data = (res && res.data) ? res.data : null;
                if (data) {
                    applyDraftData(data);
                } else {
                    // No existing draft for this appointment: use profile defaults
                    applyProfileChildToForm();
                    if (bookingMode === 'rebook') {
                        currentStep = 1;
                    }
                }
                if (done) done();
            }).fail(function () {
                // On error, still initialize from profile
                applyProfileChildToForm();
                if (bookingMode === 'rebook') {
                    currentStep = 1;
                }
                if (done) done();
            });
        }

        function scheduleSaveDraft() {
            if (!useDraft || isRestoringDraft) return;
            clearTimeout(saveDraftTimer);
            saveDraftTimer = setTimeout(saveDraft, 400);
        }

        /* ==========================
            STEP DISPLAY
        ========================== */
        function showStep(stepIndex) {

            $steps.removeClass('active').eq(stepIndex).addClass('active');

            $headers.removeClass('active completed').each(function (index) {
                if (index < stepIndex) {
                    $(this).addClass('completed');
                } else if (index === stepIndex) {
                    $(this).addClass('active');
                }
            });

            const progress = (stepIndex / ($steps.length - 1)) * 100;
            $progressBar.css('width', progress + '%');

            if (stepIndex === 5) {
                updateStep6ChildDetails();
            }
        }

        /* ==========================
            NOTIFICATION
        ========================== */
        function showNotification(message) {
            $notification.text(message).addClass('show');

            setTimeout(() => {
                $notification.removeClass('show');
            }, 3000);
        }

        /* ==========================
            VALIDATION
        ========================== */
        function validateStep(stepIndex) {

            /* STEP 1 */
            if (stepIndex === 0) {
                let valid = true;

                $form.find('#step1 input[required]').each(function () {
                    if (!$(this).val().trim()) {
                        $(this).addClass('is-invalid');
                        valid = false;
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });

                if (!valid) {
                    showNotification('Please fill all required fields.');
                    return false;
                }
            }

            /* STEP 2 */
            if (stepIndex === 1) {
                let isValid = true;

                $('.appointments-section').each(function () {

                    const date = $(this).find('.appointmentDate').val();
                    const from = $(this).find('.timeSlot').val();
                    const to = $(this).find('.toTime').val();

                    if (!date || !from || !to) {
                        showNotification('Please select date and time for all appointments.');
                        $(this).find('.appointmentDate').addClass('is-invalid');
                        isValid = false;
                        return false;
                    }
                });

                if (!isValid) return false;
            }

            /* STEP 3 */
            if (stepIndex === 2) {
                if (!$('#consentConfirmed').is(':checked')) {
                    showNotification('You must confirm that you have signed the consent form.');
                    return false;
                }
            }

            /* STEP 4 */

            if (stepIndex === 3) {
                if (!$('#assessmentInfoAccepted').is(':checked')) {
                    showNotification(
                        'Please confirm that you have read and understood how the assessment works.'
                    );
                    return false;
                }
            }

            /* STEP 5 */
            if (stepIndex === 4) {

                if (!$('#paymentAcknowledged').is(':checked')) {
                    showNotification('Please acknowledge the payment terms.');
                    return false;
                }

                if (!$('#documentationPolicy').is(':checked')) {
                    showNotification('Please acknowledge the documentation policy.');
                    return false;
                }
            }

            return true;
        }

        /* ==========================
            EVENT DELEGATION (KEY FIX)
        ========================== */
        $(document).off('click.appointmentWizard').on('click.appointmentWizard', '.next-btn', function () {

            if (!validateStep(currentStep)) return;

            if (currentStep < $steps.length - 1) {
                currentStep++;
                // In rebook mode, skip steps 3-5 (consent, booking info, payment) — jump from step 2 to step 6
                if (bookingMode === 'rebook' && currentStep >= 2 && currentStep <= 4) {
                    currentStep = 5;
                }
                showStep(currentStep);
                saveDraft();
            }
        });

        $(document).off('click.prevAppointment').on('click.prevAppointment', '.prev-btn', function () {

            if (currentStep > 0) {
                currentStep--;
                // In rebook mode, skip steps 3-5 going backwards — jump from step 6 back to step 2
                if (bookingMode === 'rebook' && currentStep >= 2 && currentStep <= 4) {
                    currentStep = 1;
                }
                showStep(currentStep);
                saveDraft();
            }
        });

        /* ==========================
            SUBMIT
        ========================== */
        $(document).off('submit.appointmentSubmit').on('submit.appointmentSubmit', '#addAppointmentForm', function (e) {

            e.preventDefault();

            var $btn = $(this).find('.submitAppointmentBtn');
            $btn.prop('disabled', true).text('Saving…');

            var formData = $(this).serialize();

            $.ajax({
                url: $(this).attr('action'),
                method: 'PUT',
                data: formData,
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (res) {
                    if (res && res.data && res.data.url) {
                        showNotification('Booking completed successfully.');
                        setTimeout(function () { window.location.href = res.data.url; }, 800);
                    } else {
                        showNotification('Booking saved.');
                        $btn.prop('disabled', false).text('Finish');
                    }
                },
                error: function (xhr) {
                    var msg = 'An error occurred. Please try again.';
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp && resp.message) msg = resp.message;
                    } catch (ex) {}
                    showNotification(msg);
                    $btn.prop('disabled', false).text('Finish');
                }
            });
        });

        /* ==========================
            DRAFT: restore then bind save (patient edit only, AJAX)
        ========================== */
        if (useDraft) {
            $form.find('#first_name, #last_name, #address, #dob, #tax_code, #school_name, #school_grade').on('input change', scheduleSaveDraft);
            $form.find('.appointmentDate, .timeSlot, .toTime').on('change', scheduleSaveDraft);
            $form.find('#consentConfirmed, #assessmentInfoAccepted, #paymentAcknowledged, #documentationPolicy').on('change', scheduleSaveDraft);
        }

        /* ==========================
            JOTFORM postMessage LISTENER
            Detects when individual JotForm iframe submissions complete
            and tracks which forms have been signed.
        ========================== */
        var signedConsentForms = {}; // Track which doctor's forms have been signed
        var _consentWebhookPending = {}; // Prevent duplicate AJAX calls

        /**
         * Record consent via AJAX for a specific doctor
         */
        function recordConsentForDoctor(doctorId, submissionId) {
            var appointmentId = $('#appointment_id_for_draft').val();
            if (!appointmentId || !doctorId) return;
            if (_consentWebhookPending[doctorId]) return; // Already processing
            if (signedConsentForms[doctorId]) return; // Already signed

            _consentWebhookPending[doctorId] = true;

            $.ajax({
                url: '/api/consent-webhook',
                type: 'POST',
                data: {
                    appointment_id: appointmentId,
                    doctor_id: doctorId,
                    submission_id: submissionId || ''
                },
                success: function (result) {
                    console.log('Consent webhook recorded for doctor ' + doctorId + ':', result);
                    signedConsentForms[doctorId] = true;
                    updateConsentSignStatus();
                },
                error: function (xhr) {
                    console.warn('Consent webhook failed for doctor ' + doctorId + ':', xhr.responseText);
                },
                complete: function () {
                    _consentWebhookPending[doctorId] = false;
                }
            });
        }

        window.addEventListener('message', function (event) {
            var data = event.data;
            if (typeof data === 'string') {
                try { data = JSON.parse(data); } catch (e) { return; }
            }
            if (!data || typeof data !== 'object') return;

            // Handle our OWN success page postMessage (from consent-success-fallback view)
            if (data.action === 'consent-form-completed' && data.source === 'clinic-consent-webhook') {
                showNotification('Consent form signed successfully!');
                // Record consent for all unsigned doctors
                $('.consent-form-wrapper .consent-form-container').each(function () {
                    var doctorId = $(this).data('doctor-id');
                    if (doctorId && !signedConsentForms[doctorId]) {
                        recordConsentForDoctor(doctorId, '');
                    }
                });
                scheduleSaveDraft();
                return;
            }

            // Handle JotForm postMessage
            if (!event.origin || event.origin.indexOf('jotform') === -1) {
                return;
            }

            // JotForm sends messages with action: 'submission-completed' or
            // a 'formData' payload once the form is submitted
            if (data.action === 'submission-completed' || data.formData || data.submissionID) {
                var submissionId = data.submissionID || data.id || '';

                // Store this submission ID
                if (submissionId) {
                    window._lastJotformSubmission = {
                        id: submissionId,
                        timestamp: Date.now(),
                        data: data
                    };
                }

                showNotification('Consent form signed successfully!');

                // Get all doctor IDs and record consent for each unsigned one
                var doctorIds = [];
                $('.consent-form-wrapper .consent-form-container').each(function () {
                    var doctorId = $(this).data('doctor-id');
                    if (doctorId) doctorIds.push(doctorId);
                });

                // Try to determine which iframe sent this (by checking which hasn't been signed yet)
                var unsignedDoctors = doctorIds.filter(function (id) { return !signedConsentForms[id]; });
                if (unsignedDoctors.length > 0) {
                    recordConsentForDoctor(unsignedDoctors[0], submissionId);
                } else if (doctorIds.length > 0) {
                    recordConsentForDoctor(doctorIds[0], submissionId);
                }

                scheduleSaveDraft();
            }
        });

        /**
         * IFRAME LOAD MONITORING
         * Detects when a Jotform iframe navigates to our webhook (redirect with HTTP POST)
         * and automatically records consent. This handles the case where Jotform
         * redirects the iframe to /api/consent-webhook which shows the success page.
         */
        $('.consent-iframe').on('load', function () {
            var $iframe = $(this);
            var doctorId = $iframe.data('doctor-id');
            if (!doctorId || signedConsentForms[doctorId]) return;

            try {
                // If iframe navigated to our domain (consent webhook redirect), it means form was submitted
                var iframeUrl = $iframe[0].contentWindow.location.href;
                if (iframeUrl && iframeUrl.indexOf('consent-webhook') !== -1) {
                    // The iframe was redirected to our webhook — record consent
                    recordConsentForDoctor(doctorId, '');
                    showNotification('Consent form signed successfully!');
                }
            } catch (e) {
                // Cross-origin — iframe is still on jotform.com, which is normal on initial load
            }
        });

        /**
         * Update the UI to show which consent forms have been signed
         */
        function updateConsentSignStatus() {
            var allFormsRequired = $('.consent-form-wrapper .consent-form-container').length;
            var formsSigned = Object.keys(signedConsentForms).filter(function (k) { return signedConsentForms[k]; }).length;

            $('.consent-form-wrapper .consent-form-container').each(function () {
                var doctorId = $(this).data('doctor-id');
                var $status = $(this).find('.consent-status[data-doctor-id="' + doctorId + '"]');

                if (signedConsentForms[doctorId]) {
                    $status.show();
                } else {
                    $status.hide();
                }
            });

            // Only enable the checkbox if all forms are signed OR no forms are required
            if (allFormsRequired === 0 || formsSigned === allFormsRequired) {
                $('#consentConfirmed').prop('disabled', false);
            } else {
                $('#consentConfirmed').prop('disabled', true);
            }
        }

        /* ==========================
            TIME SLOT LOADING (per appointment section)
        ========================== */
        function getSessionTimeUrl() {
            var routeFn = typeof route === 'function' ? route : (window.route || function () { return ''; });
            var ur = (typeof userRole !== 'undefined') ? userRole : '';
            var dr = (typeof doctorRole !== 'undefined') ? doctorRole : '';
            if (ur && ur !== '' && ur !== '0' && ur !== 'false') return routeFn('patients.doctor-session-time');
            if (dr && dr !== '' && dr !== '0' && dr !== 'false') return routeFn('doctors.doctor-session-time');
            return routeFn('doctor-session-time');
        }

        $(document).off('change.slotLoad').on('change.slotLoad', '.appointmentDate', function () {
            var $section = $(this).closest('.appointments-section');
            if (!$section.length) return;

            var selectedDate = $(this).val();
            var doctorId = $section.find('.adminAppointmentDoctorId').val();
            var serviceId = $section.find('.appointmentServiceId').val();
            var appointmentType = $section.find('.appointmentType').val() || '';
            var $slotData = $section.find('.appointment-slot-data');
            var $noSlot = $section.find('.no-time-slot');
            var $timeOver = $section.find('.doctor-time-over');
            var $timeSlot = $section.find('.timeSlot');
            var $toTime = $section.find('.toTime');
            var $dateTime = $section.find('.date-time');

            // Clear previous slots
            $slotData.html('');
            $noSlot.removeClass('d-none');
            $timeOver.addClass('d-none');
            $timeSlot.val('');
            $toTime.val('');
            $dateTime.text('Date & Time not selected');

            if (!selectedDate || !doctorId) return;

            var tz = new Date().getTimezoneOffset();
            tz = tz === 0 ? 0 : -tz;

            $.ajax({
                url: getSessionTimeUrl(),
                type: 'GET',
                data: {
                    adminAppointmentDoctorId: doctorId,
                    appointmentServiceId: serviceId || '',
                    date: selectedDate,
                    timezone_offset_minutes: tz,
                    appointment_type: appointmentType
                },
                success: function (result) {
                    if (!result.success) return;

                    var slots = result.data['slots'] || [];
                    var bookedSlots = result.data['bookedSlot'] || [];

                    if (slots.length === 0) {
                        $noSlot.removeClass('d-none');
                        if (bookedSlots && bookedSlots.length > 0) {
                            $noSlot.addClass('d-none');
                            $timeOver.removeClass('d-none');
                        }
                        return;
                    }

                    $noSlot.addClass('d-none');
                    $timeOver.addClass('d-none');

                    $.each(slots, function (index, value) {
                        var isBooked = (bookedSlots && $.inArray(value, bookedSlots) !== -1);
                        var cls = 'time-slot col-lg-2' + (isBooked ? ' bookedSlot' : '');
                        $slotData.append('<span class="' + cls + '" data-id="' + value + '">' + value + '</span>');
                    });
                },
                error: function () {
                    $noSlot.removeClass('d-none');
                    $timeOver.addClass('d-none');
                }
            });
        });

        /* Time slot click handler (per section) */
        $(document).off('click.slotSelect').on('click.slotSelect', '.time-slot:not(.bookedSlot)', function () {
            var $section = $(this).closest('.appointments-section');
            $section.find('.time-slot').removeClass('activeSlot');
            $(this).addClass('activeSlot');

            var fromToTime = $(this).attr('data-id').split('-');
            var fromTime = (fromToTime[0] || '').trim();
            var toTime = (fromToTime[1] || '').trim();
            $section.find('.timeSlot').val(fromTime);
            $section.find('.toTime').val(toTime);

            // Update the summary card
            var selectedDate = $section.find('.appointmentDate').val();
            $section.find('.date-time').text(selectedDate + ' ' + fromTime + '-' + toTime);

            scheduleSaveDraft();
        });

        /* ==========================
            INIT FLATPICKR + AVAILABLE DATES
        ========================== */
        var lang = $('.currentLanguage').val() || 'en';
        var tzOffset = new Date().getTimezoneOffset();
        tzOffset = tzOffset === 0 ? 0 : -tzOffset;
        var rangeStart = new Date();
        var rangeEnd = new Date();
        rangeEnd.setDate(rangeEnd.getDate() + 90);
        var rangeStartStr = rangeStart.toISOString().slice(0, 10);
        var rangeEndStr = rangeEnd.toISOString().slice(0, 10);

        function getAvailDatesUrl() {
            var routeFn = typeof route === 'function' ? route : (window.route || function () { return ''; });
            var ur = (typeof userRole !== 'undefined') ? userRole : '';
            var dr = (typeof doctorRole !== 'undefined') ? doctorRole : '';
            if (ur && ur !== '' && ur !== '0' && ur !== 'false') return routeFn('patients.doctor-available-dates');
            if (dr && dr !== '' && dr !== '0' && dr !== 'false') return routeFn('doctors.doctor-available-dates');
            return routeFn('doctor-available-dates');
        }

        function fetchAvailableDates(inputEl) {
            var fp = inputEl._flatpickr;
            if (!fp) return;
            var $section = $(inputEl).closest('.appointments-section');
            if (!$section.length) $section = $(inputEl).closest('form');
            var doctorId = $section.find('.adminAppointmentDoctorId').val();
            var serviceId = $section.find('.appointmentServiceId').val();
            if (!doctorId) return;

            $.ajax({
                url: getAvailDatesUrl(),
                type: 'GET',
                data: {
                    adminAppointmentDoctorId: doctorId,
                    appointmentServiceId: serviceId || '',
                    start_date: rangeStartStr,
                    end_date: rangeEndStr,
                    timezone_offset_minutes: tzOffset
                },
                success: function (result) {
                    if (result.success && result.data && result.data.dates && result.data.dates.length) {
                        fp.config.enable = result.data.dates;
                        fp._availableDates = result.data.dates;
                    } else {
                        fp.config.enable = [];
                        fp._availableDates = [];
                    }
                    fp.redraw();
                    if (fp.calendarContainer) {
                        fp.calendarContainer.classList.add('flatpickr-appointment-available-dates');
                    }
                }
            });
        }

        $('.appointmentDate').each(function () {
            var inputEl = this;
            if (!inputEl._flatpickr) {
                $(inputEl).flatpickr({
                    locale: lang,
                    minDate: new Date(),
                    disableMobile: true,
                    dateFormat: 'Y-m-d',
                    onOpen: function () {
                        if (inputEl._flatpickr && inputEl._flatpickr.calendarContainer) {
                            inputEl._flatpickr.calendarContainer.classList.add('flatpickr-appointment-available-dates');
                        }
                        fetchAvailableDates(inputEl);
                    },
                    onReady: function () {
                        // Fetch available dates immediately after init
                        setTimeout(function () { fetchAvailableDates(inputEl); }, 200);
                    }
                });
            }
        });

        /* ==========================
            INIT: restore draft then show step
        ========================== */
        restoreDraft(function () {
            showStep(currentStep);
        });
    }

    /* ==========================
        LOAD HANDLERS
    ========================== */
    $(document).ready(initAppointmentForm);

    // For Turbo / AJAX / SPA navigation
    document.addEventListener('turbo:load', initAppointmentForm);
    document.addEventListener('livewire:navigated', initAppointmentForm);

})(jQuery);

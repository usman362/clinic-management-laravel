(function ($) {

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
                method: 'POST',
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
            Detects when the JotForm iframe submission completes
            and auto-checks the consent checkbox.
        ========================== */
        window.addEventListener('message', function (event) {
            // JotForm sends a postMessage with submission data on completion
            // The origin will be from jotform.com or a custom domain
            if (!event.origin || event.origin.indexOf('jotform') === -1) {
                return;
            }

            var data = event.data;
            if (typeof data === 'string') {
                try { data = JSON.parse(data); } catch (e) { return; }
            }

            // JotForm sends messages with action: 'submission-completed' or
            // a 'formData' payload once the form is submitted
            if (data && (data.action === 'submission-completed' || data.formData || data.submissionID)) {
                // Auto-check the consent checkbox
                if (!$('#consentConfirmed').is(':checked')) {
                    $('#consentConfirmed').prop('checked', true).trigger('change');
                }

                // Notify the user
                showNotification('Consent form signed successfully!');

                // Optionally POST the consent to our authenticated endpoint
                var appointmentId = $('#appointment_id_for_draft').val();
                var doctorIds = [];
                $('.consent-form-wrapper iframe').each(function () {
                    var src = $(this).attr('src') || '';
                    // Try to get the doctor_id from a data attribute on the parent
                    var doctorId = $(this).closest('[data-doctor-id]').data('doctor-id');
                    if (doctorId) doctorIds.push(doctorId);
                });

                if (appointmentId && doctorIds.length > 0) {
                    $.ajax({
                        url: '/api/consent-webhook',
                        type: 'POST',
                        data: {
                            appointment_id: appointmentId,
                            doctor_id: doctorIds[0],
                        },
                        success: function (result) {
                            console.log('Consent webhook recorded:', result);
                        },
                        error: function (xhr) {
                            console.warn('Consent webhook failed:', xhr.responseText);
                        }
                    });
                }

                scheduleSaveDraft();
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

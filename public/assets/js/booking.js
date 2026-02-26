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
        var saveDraftTimer;

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

        function applyDraftData(data) {
            if (!data) return;
            try {
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
                if (data.appointments && data.appointments.length) {
                    $('.appointments-section').each(function (i) {
                        var d = data.appointments[i];
                        if (!d) return;
                        var $s = $(this);
                        if (d.date) $s.find('.appointmentDate').val(d.date);
                        if (d.from_time) $s.find('.timeSlot').val(d.from_time);
                        if (d.to_time) $s.find('.toTime').val(d.to_time);
                    });
                }
            } catch (e) {}
        }

        function restoreDraft(done) {
            if (!useDraft) { if (done) done(); return; }
            $.ajax({
                url: draftGetUrl,
                method: 'GET',
                dataType: 'json',
                headers: { 'Accept': 'application/json' }
            }).done(function (res) {
                var data = (res && res.data) ? res.data : null;
                applyDraftData(data);
                if (done) done();
            }).fail(function () { if (done) done(); });
        }

        function scheduleSaveDraft() {
            if (!useDraft) return;
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
                showStep(currentStep);
                saveDraft();
            }
        });

        $(document).off('click.prevAppointment').on('click.prevAppointment', '.prev-btn', function () {

            if (currentStep > 0) {
                currentStep--;
                showStep(currentStep);
                saveDraft();
            }
        });

        /* ==========================
            SUBMIT
        ========================== */
        $(document).off('submit.appointmentSubmit').on('submit.appointmentSubmit', '#addAppointmentForm', function (e) {

            e.preventDefault();

            showNotification('Booking completed successfully.');

            setTimeout(() => {
                console.log('Booking flow finished');
                // this.submit(); // uncomment when backend ready
            }, 1500);
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

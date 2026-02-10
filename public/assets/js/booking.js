(function ($) {

    let currentStep = 0;

    function initAppointmentForm() {

        const $form = $('#addAppointmentForm');
        if (!$form.length) return;

        const $steps = $form.find('.form-step');
        const $headers = $('.step-item');
        const $progressBar = $('.progress-indicator');
        const $notification = $('#notification');

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
            }
        });

        $(document).off('click.prevAppointment').on('click.prevAppointment', '.prev-btn', function () {

            if (currentStep > 0) {
                currentStep--;
                showStep(currentStep);
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
            INIT
        ========================== */
        showStep(currentStep);
    }

    /* ==========================
        LOAD HANDLERS
    ========================== */
    $(document).ready(initAppointmentForm);

    // For Turbo / AJAX / SPA navigation
    document.addEventListener('turbo:load', initAppointmentForm);
    document.addEventListener('livewire:navigated', initAppointmentForm);

})(jQuery);

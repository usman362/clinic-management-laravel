document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('addAppointmentForm');
    const steps = form.querySelectorAll('.form-step');
    const headers = document.querySelectorAll('.step-item');
    const progressBar = document.querySelector('.progress-indicator');
    const notification = document.getElementById('notification');

    let currentStep = 0;

    /* ==========================
        STEP DISPLAY
    ========================== */
    function showStep(stepIndex) {
        steps.forEach((step, index) => {
            step.classList.toggle('active', index === stepIndex);
        });

        headers.forEach((header, index) => {
            header.classList.remove('active', 'completed');

            if (index < stepIndex) {
                header.classList.add('completed');
            } else if (index === stepIndex) {
                header.classList.add('active');
            }
        });

        const progress = (stepIndex / (steps.length - 1)) * 100;
        progressBar.style.width = progress + '%';
    }

    /* ==========================
        NOTIFICATION
    ========================== */
    function showNotification(message) {
        notification.textContent = message;
        notification.classList.add('show');

        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
    }

    /* ==========================
        VALIDATION PER STEP
    ========================== */
    function validateStep(stepIndex) {

        /* STEP 1 – CLIENT DETAILS */
        if (stepIndex === 0) {
            const firstName = form.querySelector('[name="first_name"]').value.trim();
            const lastName = form.querySelector('[name="last_name"]').value.trim();
            const dob = form.querySelector('[name="dob"]').value.trim();
            const taxCode = form.querySelector('[name="tax_code"]').value.trim();
            const schoolName = form.querySelector('[name="school_name"]').value.trim();
            const schoolGrade = form.querySelector('[name="school_grade"]').value.trim();
            const address = form.querySelector('[name="address"]').value.trim();

            if (!firstName || !lastName || !dob || !taxCode || !schoolName || !schoolGrade || !address) {
                showNotification('Please fill all required fields.');
                return false;
            }
        }

        /* STEP 2 – APPOINTMENTS */
        if (stepIndex === 1) {
            let isValid = true;

            $('.appointments-section').each(function () {
                const appointmentDate = $(this).find('.appointmentDate').val();
                const timeSlot = $(this).find('.timeSlot').val();
                const toTime = $(this).find('.toTime').val();

                if (!appointmentDate || !timeSlot || !toTime || timeSlot == "" || toTime == "") {
                    showNotification('Please select date and time for all appointments.');

                    // Optional UI highlight
                    $(this).find('.appointmentDate').addClass('is-invalid');

                    isValid = false;
                    return false; // break loop
                }
            });

            if (!isValid) {
                return false;
            }
        }


        /* STEP 3 – CONSENT */
        if (stepIndex === 2) {
            const consentChecked = document.getElementById('consentConfirmed').checked;

            if (!consentChecked) {
                showNotification('You must confirm that you have signed the consent form.');
                return false;
            }
        }

        /* STEP 4 – PAYMENT ACKNOWLEDGEMENT */
        if (stepIndex === 3) {
            const paymentAck = document.getElementById('paymentAcknowledged').checked;
            const documentationAck = document.getElementById('documentationPolicy').checked;

            if (!paymentAck) {
                showNotification('Please acknowledge the payment terms.');
                return false;
            }

            if (!documentationAck) {
                showNotification('Please acknowledge the documentation policy.');
                return false;
            }
        }

        return true;
    }

    /* ==========================
        BUTTON HANDLING
    ========================== */
    form.addEventListener('click', function (e) {

        /* NEXT */
        if (e.target.classList.contains('next-btn')) {

            if (!validateStep(currentStep)) {
                return;
            }

            if (currentStep < steps.length - 1) {
                currentStep++;
                showStep(currentStep);
            }
        }

        /* PREVIOUS */
        if (e.target.classList.contains('prev-btn')) {
            if (currentStep > 0) {
                currentStep--;
                showStep(currentStep);
            }
        }
    });

    /* ==========================
        FINAL SUBMIT
    ========================== */
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        showNotification('Booking completed successfully.');

        /*
         NEXT STEP (backend):
         - Send AJAX to Laravel
         - Create real appointments
         - Mark package as completed
         */

        setTimeout(() => {
            // window.location.href = '/client/dashboard';
            console.log('Booking flow finished');
        }, 1500);
    });

    showStep(0);
});

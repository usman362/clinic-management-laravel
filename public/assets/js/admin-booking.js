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

    /* ADD APPOINTMENT ROW */
    $('#add-appointment').on('click', function () {
        appointmentIndex++;

        $('#appointments-wrapper').append(`
            <div class="card p-3 mb-3 appointments-section">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Service</label>
                        <select class="form-control service">
                            <option value="">Select service</option>
                            <option value="1">Cognitive Assessment (2h)</option>
                            <option value="2">Language Assessment (1h)</option>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Doctor</label>
                        <select class="form-control doctor">
                            <option value="">Select doctor</option>
                            <option value="10">Dr. Sara T.</option>
                            <option value="11">Dr. Erika S.</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-danger remove-appointment">✕</button>
                    </div>
                </div>
            </div>
        `);
    });

    $(document).on('click', '.remove-appointment', function () {
        $(this).closest('.appointments-section').remove();
    });

    /* NEXT / PREV */
    $(document).on('click', '.next-btn', function () {

        if (currentStep === 0 && !$('#client_id').val()) {
            notify('Please select a client');
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

                                    <div class="col-md-6">Email:</div>
                                    <div class="col-md-6">luca.verdi@example.com</div>
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
                                <p>Notes internal, not shown to clients</p>
                            </div>
                    </div>
            `;

            html += `
                    <div class="card-body mt-4" style="border: 1px solid #d9d9d9;border-radius: 12px; padding:1.5rem">
                        <h3>What happens on activation?</h3>
                            <div class="row mt-2" style="margin-left:20px">
                                • A unique booking link will be generated <br>
                                • An email will be sent to luca.verdi@example.com <br>
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

    $(document).on('click', '.prev-btn', function () {
        currentStep--;
        showStep(currentStep);
    });

    /* SUBMIT */
    // $('#addAppointmentForm').on('submit', function (e) {
    //     e.preventDefault();

    //     notify('Package activated & booking link sent');

    //     /*
    //       BACKEND:
    //       - Save package
    //       - Status → ready_for_booking
    //       - Generate token link
    //       - Email client
    //     */
    // });

    showStep(0);
});

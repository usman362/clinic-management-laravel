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

    /* ── Fetch doctors for a given service and populate the doctor dropdown ── */
    function loadDoctorsForService(serviceSelect) {
        var $section = $(serviceSelect).closest('.appointments-section');
        var $doctorSelect = $section.find('.adminAppointmentDoctorId');
        var $duration = $section.find('.duration');
        var serviceId = $(serviceSelect).val();

        // Reset doctor dropdown
        $doctorSelect.html('<option value="">Select doctor...</option>');

        if (!serviceId) {
            $duration.text('Select a service to view duration');
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
            },
            error: function () {
                $doctorSelect.html('<option value="">Error loading doctors</option>');
            }
        });
    }

    /* ── Listen for service selection changes (works for dynamic rows too) ── */
    $(document).on('change', '.appointmentServiceId', function () {
        loadDoctorsForService(this);
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
       AP-09: Each visit to the page re-runs this IIFE (via Turbo navigation
       or subsequent re-entries), which previously stacked a new `click`
       handler on top of the old ones — so clicking + Add Appointment ONCE
       appended TWO (or more) rows. Namespaced `.off()` before `.on()`
       guarantees exactly one live binding regardless of how many times
       this script runs. Same pattern applied to all other handlers below. */
    $('#addAppointment').off('click.addAppointment').on('click.addAppointment', function () {
        appointmentIndex++;
        $('.appointments-wrapper').append(buildAppointmentRow(appointmentIndex));
    });

    $(document).off('click.removeAppointment').on('click.removeAppointment', '.remove-appointment', function () {
        $(this).closest('.appointments-section').remove();
    });

    /* NEXT / PREV */
    $(document).off('click.nextBtn').on('click.nextBtn', '.next-btn', function () {

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

    showStep(0);
});

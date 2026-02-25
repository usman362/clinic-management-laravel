/**
 * Restrict appointment date picker to dates that have available slots.
 * Available dates are highlighted in green; others are disabled.
 */
function initAppointmentAvailableDates() {
    if (!$('.appointmentDate').length) return;

    var timezoneOffsetMinutes = new Date().getTimezoneOffset();
    timezoneOffsetMinutes = timezoneOffsetMinutes === 0 ? 0 : -timezoneOffsetMinutes;

    var startDate = new Date();
    var endDate = new Date();
    endDate.setDate(endDate.getDate() + 90);
    var startStr = startDate.toISOString().slice(0, 10);
    var endStr = endDate.toISOString().slice(0, 10);

    function getAvailableDatesUrl() {
        var userRole = $('#userRole').val();
        var doctorRole = $('#doctorRole').val();
        if (typeof isEmpty !== 'function') return route('doctor-available-dates');
        if (!isEmpty(userRole)) return route('patients.doctor-available-dates');
        if (!isEmpty(doctorRole)) return route('doctors.doctor-available-dates');
        return route('doctor-available-dates');
    }

    function applyAvailableDatesToPicker(inputEl) {
        var fp = inputEl._flatpickr;
        if (!fp) return;

        var $input = $(inputEl);
        var section = $input.closest('.appointments-section');
        if (!section.length) section = $input.closest('form');
        var doctorId = section.find('.adminAppointmentDoctorId').val();
        var serviceId = section.find('.appointmentServiceId').val();

        if (!doctorId) {
            fp.config.enable = undefined;
            fp.config.disable = undefined;
            fp.config.minDate = fp.config.minDate || new Date();
            fp.redraw();
            return;
        }

        $.ajax({
            url: getAvailableDatesUrl(),
            type: 'GET',
            data: {
                adminAppointmentDoctorId: doctorId,
                appointmentServiceId: serviceId || '',
                start_date: startStr,
                end_date: endStr,
                timezone_offset_minutes: timezoneOffsetMinutes
            },
            success: function (result) {
                if (result.success && result.data && result.data.dates && result.data.dates.length) {
                    fp.config.enable = result.data.dates;
                    fp.config.disable = undefined;
                    fp._availableDates = result.data.dates;
                } else {
                    fp.config.enable = [];
                    fp._availableDates = [];
                }
                fp.redraw();
                if (fp.calendarContainer) {
                    fp.calendarContainer.classList.add('flatpickr-appointment-available-dates');
                }
            },
            error: function () {
                fp.config.enable = undefined;
                fp.config.disable = undefined;
                fp.redraw();
            }
        });
    }

    function hookFlatpickrOpen(inputEl) {
        var fp = inputEl._flatpickr;
        if (!fp || fp._availableDatesHooked) return;
        fp._availableDatesHooked = true;

        var origOnOpen = fp.config.onOpen;
        fp.config.onOpen = function (args) {
            if (fp.calendarContainer) {
                fp.calendarContainer.classList.add('flatpickr-appointment-available-dates');
            }
            applyAvailableDatesToPicker(inputEl);
            if (origOnOpen && typeof origOnOpen === 'function') origOnOpen(args);
        };
    }

    // Run after default flatpickr init (turbo:load handler runs first)
    setTimeout(function () {
        $('.appointmentDate').each(function () {
            hookFlatpickrOpen(this);
            applyAvailableDatesToPicker(this);
        });
    }, 200);

    // Hook when an appointment date input is focused (covers dynamically added sections)
    $(document).on('focus', '.appointmentDate', function () {
        hookFlatpickrOpen(this);
        applyAvailableDatesToPicker(this);
    });

    // When doctor or service changes, refresh available dates for that section
    $(document).on('change', '.adminAppointmentDoctorId, .appointmentServiceId', function () {
        var section = $(this).closest('.appointments-section');
        if (!section.length) return;
        section.find('.appointmentDate').each(function () {
            applyAvailableDatesToPicker(this);
        });
    });
}

document.addEventListener('turbo:load', initAppointmentAvailableDates);
if (document.readyState === 'complete' || document.readyState === 'loading') {
    setTimeout(initAppointmentAvailableDates, 500);
}

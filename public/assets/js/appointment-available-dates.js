/**
 * Calendar me available dates green, unavailable dates disabled dikhane ke liye.
 * Standalone script - appointment create/edit pages par load hota hai.
 */
(function () {
    function initAppointmentAvailableDates() {
        if (!window.$ || !window.$.fn || !document.querySelector('.appointmentDate')) return;

        var $ = window.$;
        var timezoneOffsetMinutes = new Date().getTimezoneOffset();
        timezoneOffsetMinutes = timezoneOffsetMinutes === 0 ? 0 : -timezoneOffsetMinutes;

        var startDate = new Date();
        var endDate = new Date();
        endDate.setDate(endDate.getDate() + 90);
        var startStr = startDate.toISOString().slice(0, 10);
        var endStr = endDate.toISOString().slice(0, 10);

        function getAvailableDatesUrl() {
            var routeFn = typeof route !== 'undefined' ? route : (window.route || function () { return ''; });
            var userRole = $('#userRole').val();
            var doctorRole = $('#doctorRole').val();
            if (typeof isEmpty !== 'undefined' && typeof isEmpty === 'function' && !isEmpty(userRole)) return routeFn('patients.doctor-available-dates');
            if (typeof isEmpty !== 'undefined' && typeof isEmpty === 'function' && !isEmpty(doctorRole)) return routeFn('doctors.doctor-available-dates');
            return routeFn('doctor-available-dates');
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

        function runHook() {
            $('.appointmentDate').each(function () {
                hookFlatpickrOpen(this);
                applyAvailableDatesToPicker(this);
            });
        }

        // Pehle run: flatpickr init ke baad (delay se)
        setTimeout(runHook, 600);

        // Jab date field pe focus ho to fetch karein taake calendar open hote hi data ready ho
        $(document).on('focus', '.appointmentDate', function () {
            hookFlatpickrOpen(this);
            applyAvailableDatesToPicker(this);
        });

        // Doctor/Service change pe us section ka calendar update
        $(document).on('change', '.adminAppointmentDoctorId, .appointmentServiceId', function () {
            var section = $(this).closest('.appointments-section');
            if (!section.length) return;
            section.find('.appointmentDate').each(function () {
                applyAvailableDatesToPicker(this);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(initAppointmentAvailableDates, 100); });
    } else {
        setTimeout(initAppointmentAvailableDates, 100);
    }
    if (typeof document.addEventListener === 'function') {
        document.addEventListener('turbo:load', function () { setTimeout(initAppointmentAvailableDates, 300); });
    }
})();

/**
 * Calendar: available dates green, unavailable dates disabled/red.
 * Standalone script - appointment create/edit pages par load hota hai.
 */
(function () {

    /* ── Inject CSS once ── */
    var styleId = 'appointment-available-dates-style';
    if (!document.getElementById(styleId)) {
        var css = document.createElement('style');
        css.id = styleId;
        css.textContent = [
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
            '.flatpickr-appointment-available-dates .flatpickr-day.prevMonthDay,.flatpickr-appointment-available-dates .flatpickr-day.nextMonthDay{background:transparent !important;color:#d5d5d5 !important;border-color:transparent !important;text-decoration:none !important}',
            /* Legend row */
            '.appointment-date-legend{display:flex;gap:16px;align-items:center;margin-top:4px;font-size:12px}',
            '.appointment-date-legend .legend-dot{width:12px;height:12px;border-radius:50%;display:inline-block;margin-right:4px}',
            '.appointment-date-legend .legend-available{background:#28a745}',
            '.appointment-date-legend .legend-unavailable{background:#c0392b}'
        ].join('');
        document.head.appendChild(css);
    }

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
                fp._availableDates = null;
                fp.redraw();
                // Remove legend if no doctor
                $input.closest('.appointments-section').find('.appointment-date-legend').remove();
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
                    // Add legend below the date input if not present
                    addLegend($input);
                },
                error: function () {
                    fp.config.enable = undefined;
                    fp.config.disable = undefined;
                    fp._availableDates = null;
                    fp.redraw();
                }
            });
        }

        function addLegend($input) {
            var $wrapper = $input.closest('.col-lg-12, .col-md-12, .col-sm-12, .mb-5, .mb-3');
            if (!$wrapper.length) $wrapper = $input.parent();
            if ($wrapper.find('.appointment-date-legend').length) return;
            $wrapper.append(
                '<div class="appointment-date-legend mt-1">' +
                '<span><span class="legend-dot legend-available"></span> Available</span>' +
                '<span><span class="legend-dot legend-unavailable"></span> Unavailable</span>' +
                '</div>'
            );
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

        // First run after flatpickr init (with delay)
        setTimeout(runHook, 600);

        // On date field focus — fetch so calendar is ready when opened
        $(document).on('focus', '.appointmentDate', function () {
            hookFlatpickrOpen(this);
            applyAvailableDatesToPicker(this);
        });

        // Doctor/Service change — update that section's calendar
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

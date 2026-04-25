document.addEventListener('turbo:load', loadDoctorShowApptmentFilterDate)

let doctorShowApptmentFilterDate = $('#doctorShowAppointmentDateFilter')

function loadDoctorShowApptmentFilterDate () {
    if (!$('#doctorShowAppointmentDateFilter').length) {
        return
    }

    let doctorShowApptmentStart = moment().startOf('week')
    let doctorShowApptmentEnd = moment().endOf('week')

    function cb (start, end) {
        $('#doctorShowAppointmentDateFilter').html(
            start.format('YYYY-MM-DD') + ' - ' + end.format('YYYY-MM-DD'))
    }

    $('#doctorShowAppointmentDateFilter').daterangepicker({
        startDate: doctorShowApptmentStart,
        endDate: doctorShowApptmentEnd,
        opens: 'left',
        showDropdowns: true,
        locale: {
            customRangeLabel: Lang.get('js.custom'),
            applyLabel:Lang.get('js.apply'),
            cancelLabel: Lang.get('js.cancel'),
            fromLabel:Lang.get('js.from'),
            toLabel: Lang.get('js.to'),
            monthNames: [
                Lang.get('js.jan'),
                Lang.get('js.feb'),
                Lang.get('js.mar'),
                Lang.get('js.apr'),
                Lang.get('js.may'),
                Lang.get('js.jun'),
                Lang.get('js.jul'),
                Lang.get('js.aug'),
                Lang.get('js.sep'),
                Lang.get('js.oct'),
                Lang.get('js.nov'),
                Lang.get('js.dec')
            ],

            daysOfWeek: [
                Lang.get('js.sun'),
                Lang.get('js.mon'),
                Lang.get('js.tue'),
                Lang.get('js.wed'),
                Lang.get('js.thu'),
                Lang.get('js.fri'),
                Lang.get('js.sat')],
        },
        ranges: {
            [Lang.get('js.today')]: [moment(), moment()],
            [Lang.get('js.yesterday')]: [
                moment().subtract(1, 'days'),
                moment().subtract(1, 'days')],
            [Lang.get('js.this_week')]: [moment().startOf('week'), moment().endOf('week')],
            [Lang.get('js.last_30_days')]: [moment().subtract(29, 'days'), moment()],
            [Lang.get('js.this_month')]: [moment().startOf('month'), moment().endOf('month')],
            [Lang.get('js.last_month')]: [
                moment().subtract(1, 'month').startOf('month'),
                moment().subtract(1, 'month').endOf('month')],
        },
    }, cb)

    cb(doctorShowApptmentStart, doctorShowApptmentEnd)
}

// CP-21: Doctor's trash icon used to hit the resource `destroy` route,
// which (since AP-16 trash) cascades the WHOLE package out of view —
// one click and every appointment vanishes. The trash now performs a
// single-appointment cancel (status=CANCELLED, cancel_reason=null) so
// the row stays visible with a rebook button per AP-11.
listenClick('.doctor-show-apptment-delete-btn', function (event) {
    let recordId = $(event.currentTarget).attr('data-id');
    let cancelUrl = !isEmpty($('#patientRoleDoctorDetail').val())
        ? route('patients.cancel-status')           // doctor viewing a patient detail page (rare)
        : route('doctors.appointments.cancel-single');
    swal({
        title: Lang.get('js.cancelled_appointment'),
        text: Lang.get('js.are_you_sure_cancel'),
        icon: 'warning',
        buttons: { confirm: Lang.get('js.yes'), cancel: Lang.get('js.no') },
        reverseButtons: true,
        dangerMode: true,
    }).then(function (ok) {
        if (!ok) return;
        $.ajax({
            url: cancelUrl,
            type: 'POST',
            data: { appointmentId: recordId },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function () {
                if (typeof Livewire !== 'undefined') Livewire.dispatch('refresh');
                swal({ icon: 'success', title: Lang.get('js.cancelled_appointment'), timer: 1500, buttons: false });
            },
            error: function (xhr) {
                swal({
                    icon: 'error',
                    title: 'Error',
                    text: (xhr.responseJSON && xhr.responseJSON.message) || 'Cancel failed.',
                });
            },
        });
    });
});

listenChange('.doctor-show-apptment-status', function () {
    let doctorShowAppointmentStatus = $(this).val()
    let doctorShowAppointmentId = $(this).attr('data-id')
    let currentData = $(this)

    $.ajax({
        url: route('change-status', doctorShowAppointmentId),
        type: 'POST',
        data: {
            appointmentId: doctorShowAppointmentId,
            appointmentStatus: doctorShowAppointmentStatus,
        },
        success: function (result) {
            $(currentData).children('option.booked').addClass('hide')
            Livewire.dispatch('refresh')
            displaySuccessMessage(result.message)
        },
    });
});

listenChange('#doctorShowAppointmentDateFilter', function () {
    Livewire.dispatch('changeDateFilter', $(this).val())
})

listenChange('#doctorShowAppointmentStatus', function () {
    Livewire.dispatch('changeDateFilter', $('#doctorShowAppointmentDateFilter').val())
    Livewire.dispatch('changeStatusFilter', $(this).val())
})

listenClick('#doctorShowApptmentResetFilter', function () {
    $('#doctorShowAppointmentStatus').val(1).trigger('change')
    $('#doctorShowAppointmentDateFilter').
        val(moment().startOf('week').format('MM/DD/YYYY') + ' - ' +
            moment().endOf('week').format('MM/DD/YYYY')).
        trigger('change')
        Livewire.dispatch('refresh')
})

document.addEventListener('livewire:load', function () {
    window.livewire.hook('message.processed', () => {
        if ($('#doctorShowAppointmentStatus').length) {
            $('#doctorShowAppointmentStatus').select2()
        }
        if ($('.doctor-show-apptment-status').length) {
            $('.doctor-show-apptment-status').select2()
        }
    })
})

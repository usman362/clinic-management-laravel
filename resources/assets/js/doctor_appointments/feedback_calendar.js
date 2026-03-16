document.addEventListener('turbo:load', loadDoctorFeedbackAppointmentCalendar)

let feedbackPopover
let feedbackPopoverState = false
let feedbackAppointmentStatusId = null
let doctorFeedbackAppointmentCalendar
let feedbackData = {
    id: '',
    uId: '',
    eventName: '',
    eventDescription: '',
    eventStatus: '',
    startDate: '',
    endDate: '',
    amount: 0,
    service: '',
    patientName: '',
    location: '',
    instructions: '',
}

// View event variables
let feedbackViewEventName, feedbackViewEventDescription, feedbackViewEventStatus, feedbackViewStartDate,
    feedbackViewEndDate,
    feedbackViewModal,
    feedbackViewService,
    feedbackViewUId,
    feedbackViewAmount,
    feedbackViewLocation,
    feedbackViewInstructions

function loadDoctorFeedbackAppointmentCalendar () {
    initFeedbackCalendarApp()
    initFeedbackModal()
}

const initFeedbackCalendarApp = function () {
    if (usersRole != 'doctor') {
        return;
    }
    let calendarEl = document.getElementById('doctorFeedbackAppointmentCalendar')

    if (!$(calendarEl).length) {
        return
    }
    let lang = $('.currentLanguage').val()
    doctorFeedbackAppointmentCalendar = new FullCalendar.Calendar(calendarEl, {
        locale: lang,
        themeSystem: 'bootstrap5',
        height: 750,
        buttonText: {
            today: Lang.get('js.today'),
            day: Lang.get('js.day'),
            month: Lang.get('js.month'),
        },
        headerToolbar: {
            left: 'title',
            center: 'prev,next today',
            right: 'dayGridDay,dayGridMonth',
        },
        initialDate: new Date(),
        timeZone: 'UTC',
        dayMaxEvents: true,
        events: function (info, successCallback, failureCallback) {
            $.ajax({
                url: route('doctors.feedback_appointments.calendar'),
                type: 'GET',
                data: info,
                success: function (result) {
                    if (result.success) {
                        successCallback(result.data)
                    }
                },
                error: function (result) {
                    displayErrorMessage(result.responseJSON.message)
                    failureCallback()
                },
            })
        },
        eventMouseEnter: function (arg) {
            feedbackFormatArgs({
                id: arg.event.id,
                title: arg.event.title,
                startStr: arg.event.startStr,
                endStr: arg.event.endStr,
                description: arg.event.extendedProps.description,
                status: arg.event.extendedProps.status,
                amount: arg.event.extendedProps.amount,
                uId: arg.event.extendedProps.uId,
                service: arg.event.extendedProps.service,
                patientName: arg.event.extendedProps.patientName,
                location: arg.event.extendedProps.location,
                instructions: arg.event.extendedProps.instructions,
            })

            initFeedbackPopovers(arg.el)
        },
        eventMouseLeave: function () {
            hideFeedbackPopovers()
        },
        eventClick: function (arg) {
            hideFeedbackPopovers()
            feedbackAppointmentStatusId = arg.event.id
            feedbackFormatArgs({
                id: arg.event.id,
                title: arg.event.title,
                startStr: arg.event.startStr,
                endStr: arg.event.endStr,
                description: arg.event.extendedProps.description,
                status: arg.event.extendedProps.status,
                amount: arg.event.extendedProps.amount,
                uId: arg.event.extendedProps.uId,
                service: arg.event.extendedProps.service,
                patientName: arg.event.extendedProps.patientName,
                location: arg.event.extendedProps.location,
                instructions: arg.event.extendedProps.instructions,
            })
            handleFeedbackViewEvent()
        },
    })
    doctorFeedbackAppointmentCalendar.render()
}

const initFeedbackModal = () => {
    if (!$('#doctorFeedbackAppointmentCalendarModal').length) {
        return
    }
    const viewElement = document.getElementById(
        'doctorFeedbackAppointmentCalendarModal')
    feedbackViewModal = new bootstrap.Modal(viewElement)
    feedbackViewEventName = viewElement.querySelector(
        '[data-calendar="event_name"]')
    feedbackViewEventDescription = viewElement.querySelector(
        '[data-calendar="event_description"]')
    feedbackViewEventStatus = viewElement.querySelector(
        '[data-calendar="event_status"]')
    feedbackViewAmount = viewElement.querySelector('[data-calendar="event_amount"]')
    feedbackViewUId = viewElement.querySelector('[data-calendar="event_uId"]')
    feedbackViewService = viewElement.querySelector(
        '[data-calendar="event_service"]')
    feedbackViewStartDate = viewElement.querySelector(
        '[data-calendar="event_start_date"]')
    feedbackViewEndDate = viewElement.querySelector(
        '[data-calendar="event_end_date"]')
    feedbackViewLocation = viewElement.querySelector(
        '[data-calendar="event_location"]')
    feedbackViewInstructions = viewElement.querySelector(
        '[data-calendar="event_instructions"]')
}

const feedbackFormatArgs = (res) => {
    feedbackData.id = res.id
    feedbackData.eventName = res.title
    feedbackData.eventStatus = res.status
    feedbackData.startDate = res.startStr
    feedbackData.endDate = res.endStr
    feedbackData.amount = res.amount
    feedbackData.uId = res.uId
    feedbackData.service = res.service
    feedbackData.patientName = res.patientName
    feedbackData.location = res.location || ''
    feedbackData.instructions = res.instructions || ''
}

const initFeedbackPopovers = (element) => {
    hideFeedbackPopovers()

    const startDate = moment(feedbackData.startDate).format('Do MMM, YYYY - h:mm a')
    const endDate = moment(feedbackData.endDate).format('Do MMM, YYYY - h:mm a')
    const popoverHtml = '<div class="fw-bolder mb-2"><b>Patient:</b> ' +
        feedbackData.patientName +
        '</div><div class="fs-7"><span class="fw-bold">Start:</span> ' +
        startDate +
        '</div><div class="fs-7 mb-4"><span class="fw-bold">End:</span> ' +
        endDate + '</div>'

    let options = {
        container: 'body',
        trigger: 'manual',
        boundary: 'window',
        placement: 'auto',
        dismiss: true,
        html: true,
        title: 'Appointment Details',
        content: popoverHtml,
    }

    feedbackPopover = new bootstrap.Popover(element, options)
    feedbackPopover.show()
    feedbackPopoverState = true
}

const hideFeedbackPopovers = () => {
    if (feedbackPopoverState) {
        feedbackPopover.dispose()
        feedbackPopoverState = false
    }
}

const handleFeedbackViewEvent = () => {
    $('.fc-popover').addClass('hide')
    feedbackViewModal.show()

    let book = $('#bookCalenderConst').val();
    let checkIn = $('#checkInCalenderConst').val();
    let checkOut = $('#checkOutCalenderConst').val();
    let cancel = $('#cancelCalenderConst').val();

    let startDateMod = moment(feedbackData.startDate).utc().format('Do MMM, YYYY - h:mm A')
    let endDateMod = moment(feedbackData.endDate).utc().format('Do MMM, YYYY - h:mm A')
    feedbackViewEndDate.innerText = ': ' + endDateMod
    feedbackViewStartDate.innerText = ': ' + startDateMod

    feedbackViewEventName.innerText = 'Patient: ' + feedbackData.patientName
    $(feedbackViewEventStatus).empty()
    $(feedbackViewEventStatus).append(`
<option class="booked" disabled value="${book}" ${feedbackData.eventStatus == book
        ? 'selected'
        : ''}>${Lang.get('js.booked')}</option>
<option value="${checkIn}" ${feedbackData.eventStatus == checkIn
        ? 'selected'
        : ''} ${feedbackData.eventStatus == checkIn ? 'selected' : ''}
    ${(feedbackData.eventStatus == cancel || feedbackData.eventStatus == checkOut)
        ? 'disabled'
        : ''}>${Lang.get('js.check_in')}</option>
<option value="${checkOut}" ${feedbackData.eventStatus == checkOut ? 'selected' : ''}
    ${(feedbackData.eventStatus == cancel || feedbackData.eventStatus == book)
        ? 'disabled'
        : ''}>${Lang.get('js.check_out')}</option>
<option value="${cancel}" ${feedbackData.eventStatus == cancel
        ? 'selected'
        : ''} ${feedbackData.eventStatus == checkIn ? 'disabled' : ''}
   ${feedbackData.eventStatus == checkOut ? 'disabled' : ''}>${Lang.get('js.cancelled')}</option>
`)
    $(feedbackViewEventStatus).val(feedbackData.eventStatus).trigger('change')
    if (feedbackViewAmount) {
        feedbackViewAmount.innerText = addCommas(feedbackData.amount)
    }
    feedbackViewUId.innerText = feedbackData.uId
    feedbackViewService.innerText = feedbackData.service

    // Populate location and instructions
    if (feedbackViewLocation) {
        feedbackViewLocation.innerText = feedbackData.location || 'N/A'
        feedbackViewLocation.closest('.calendar-detail-row').style.display = feedbackData.location ? '' : 'none'
    }
    if (feedbackViewInstructions) {
        feedbackViewInstructions.innerText = feedbackData.instructions || 'N/A'
        feedbackViewInstructions.closest('.calendar-detail-row').style.display = feedbackData.instructions ? '' : 'none'
    }
}

listenChange('.doctor-feedback-apptnt-calendar-status-change', function () {
    if (!$(this).val()) {
        return false
    }

    let appointmentStatus = $(this).val()
    let appointmentId = feedbackAppointmentStatusId
    if (parseInt(appointmentStatus) === feedbackData.eventStatus) {
        return false
    }
    $.ajax({
        url: route('doctors.change-status', appointmentId),
        type: 'POST',
        data: {
            appointmentId: appointmentId,
            appointmentStatus: appointmentStatus,
        },
        success: function (result) {
            displaySuccessMessage(result.message)
            $('#doctorFeedbackAppointmentCalendarModal').modal('hide')
            doctorFeedbackAppointmentCalendar.refetchEvents()
        },
    })
})

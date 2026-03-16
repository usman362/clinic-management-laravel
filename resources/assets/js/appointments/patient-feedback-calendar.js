document.addEventListener('turbo:load', loadPatientFeedbackAppointmentCalendar)

let patFeedbackPopover
let patFeedbackPopoverState = false
let patFeedbackCalendar
let patFeedbackData = {
    id: '',
    uId: '',
    eventName: '',
    eventDescription: '',
    eventStatus: '',
    startDate: '',
    endDate: '',
    amount: 0,
    service: '',
    doctorName: '',
    location: '',
    instructions: '',
}

// View event variables
let patFeedbackViewEventName, patFeedbackViewEventDescription, patFeedbackViewEventStatus, patFeedbackViewStartDate,
    patFeedbackViewEndDate,
    patFeedbackViewModal,
    patFeedbackViewService,
    patFeedbackViewUId,
    patFeedbackViewAmount,
    patFeedbackViewLocation,
    patFeedbackViewInstructions

function loadPatientFeedbackAppointmentCalendar () {
    if (!$('#patientFeedbackAppointmentCalendar').length) {
        return
    }
    initPatFeedbackCalendarApp()
    initPatFeedbackModal()
}

const initPatFeedbackCalendarApp = function () {
    if (usersRole != 'patient') {
        return;
    }
    let lang = $('.currentLanguage').val()
    let calendarEl = document.getElementById('patientFeedbackAppointmentCalendar');
    patFeedbackCalendar = new FullCalendar.Calendar(calendarEl, {
        locale: lang,
        themeSystem: 'bootstrap5',
        height: 750,
        buttonText: {
            today: 'Today',
            day: 'Day',
            month: 'Month',
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
                url: route('patients.feedback_appointments.calendar'),
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
            patFeedbackFormatArgs({
                id: arg.event.id,
                title: arg.event.title,
                startStr: arg.event.startStr,
                endStr: arg.event.endStr,
                description: arg.event.extendedProps.description,
                status: arg.event.extendedProps.status,
                amount: arg.event.extendedProps.amount,
                uId: arg.event.extendedProps.uId,
                service: arg.event.extendedProps.service,
                doctorName: arg.event.extendedProps.doctorName,
                location: arg.event.extendedProps.location,
                instructions: arg.event.extendedProps.instructions,
            })

            initPatFeedbackPopovers(arg.el)
        },
        eventMouseLeave: function () {
            hidePatFeedbackPopovers()
        },
        eventClick: function (arg) {
            hidePatFeedbackPopovers()
            patFeedbackFormatArgs({
                id: arg.event.id,
                title: arg.event.title,
                startStr: arg.event.startStr,
                endStr: arg.event.endStr,
                description: arg.event.extendedProps.description,
                status: arg.event.extendedProps.status,
                amount: arg.event.extendedProps.amount,
                uId: arg.event.extendedProps.uId,
                service: arg.event.extendedProps.service,
                doctorName: arg.event.extendedProps.doctorName,
                location: arg.event.extendedProps.location,
                instructions: arg.event.extendedProps.instructions,
            })
            handlePatFeedbackViewEvent()
        },
    })
    patFeedbackCalendar.render()
}

const initPatFeedbackModal = () => {
    if (!$('#patientFeedbackEventModal').length) {
        return
    }

    const viewElement = document.getElementById('patientFeedbackEventModal')
    patFeedbackViewModal = new bootstrap.Modal(viewElement)
    patFeedbackViewEventName = viewElement.querySelector(
        '[data-calendar="event_name"]')
    patFeedbackViewEventDescription = viewElement.querySelector(
        '[data-calendar="event_description"]')
    patFeedbackViewEventStatus = viewElement.querySelector(
        '[data-calendar="event_status"]')
    patFeedbackViewAmount = viewElement.querySelector('[data-calendar="event_amount"]')
    patFeedbackViewUId = viewElement.querySelector('[data-calendar="event_uId"]')
    patFeedbackViewService = viewElement.querySelector(
        '[data-calendar="event_service"]')
    patFeedbackViewStartDate = viewElement.querySelector(
        '[data-calendar="event_start_date"]')
    patFeedbackViewEndDate = viewElement.querySelector(
        '[data-calendar="event_end_date"]')
    patFeedbackViewLocation = viewElement.querySelector(
        '[data-calendar="event_location"]')
    patFeedbackViewInstructions = viewElement.querySelector(
        '[data-calendar="event_instructions"]')
}

const patFeedbackFormatArgs = (res) => {
    patFeedbackData.id = res.id
    patFeedbackData.eventName = res.title
    patFeedbackData.eventDescription = res.description
    patFeedbackData.eventStatus = res.status
    patFeedbackData.startDate = res.startStr
    patFeedbackData.endDate = res.endStr
    patFeedbackData.amount = res.amount
    patFeedbackData.uId = res.uId
    patFeedbackData.service = res.service
    patFeedbackData.doctorName = res.doctorName
    patFeedbackData.location = res.location || ''
    patFeedbackData.instructions = res.instructions || ''
}

const initPatFeedbackPopovers = (element) => {
    hidePatFeedbackPopovers()

    const startDate = moment(patFeedbackData.startDate).format('Do MMM, YYYY - h:mm a')
    const endDate = moment(patFeedbackData.endDate).format('Do MMM, YYYY - h:mm a')
    const popoverHtml = '<div class="fw-bolder mb-2"><b>Doctor</b>: ' +
        patFeedbackData.doctorName +
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

    patFeedbackPopover = new bootstrap.Popover(element, options)
    patFeedbackPopover.show()
    patFeedbackPopoverState = true
}

const hidePatFeedbackPopovers = () => {
    if (patFeedbackPopoverState) {
        patFeedbackPopover.dispose()
        patFeedbackPopoverState = false
    }
}

const handlePatFeedbackViewEvent = () => {
    $('.fc-popover').addClass('hide')
    patFeedbackViewModal.show()

    let startDateMod = moment(patFeedbackData.startDate).utc().format('Do MMM, YYYY - h:mm A')
    let endDateMod = moment(patFeedbackData.endDate).utc().format('Do MMM, YYYY - h:mm A')
    patFeedbackViewEndDate.innerText = ': ' + endDateMod
    patFeedbackViewStartDate.innerText = ': ' + startDateMod

    patFeedbackViewEventName.innerText = 'Doctor: ' + patFeedbackData.doctorName
    $(patFeedbackViewEventStatus).val(patFeedbackData.eventStatus)
    if (patFeedbackViewAmount) {
        patFeedbackViewAmount.innerText = addCommas(patFeedbackData.amount)
    }
    patFeedbackViewUId.innerText = patFeedbackData.uId
    patFeedbackViewService.innerText = patFeedbackData.service

    if (patFeedbackViewLocation) {
        patFeedbackViewLocation.innerText = patFeedbackData.location || 'N/A'
        patFeedbackViewLocation.closest('.calendar-detail-row').style.display = patFeedbackData.location ? '' : 'none'
    }
    if (patFeedbackViewInstructions) {
        patFeedbackViewInstructions.innerText = patFeedbackData.instructions || 'N/A'
        patFeedbackViewInstructions.closest('.calendar-detail-row').style.display = patFeedbackData.instructions ? '' : 'none'
    }
}

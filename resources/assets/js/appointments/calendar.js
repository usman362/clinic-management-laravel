document.addEventListener('turbo:load', loadAppointmentCalendar)

/* -------------------------------------------------
 | GLOBAL STATE
 -------------------------------------------------*/
let appointmentCalendar = null
let feedbackCalendar = null
let activeCalendarType = null
let appointmentStatusId = null

let data = {
    id: '',
    uId: '',
    eventName: '',
    patientName: '',
    eventDescription: '',
    eventStatus: '',
    startDate: '',
    endDate: '',
    amount: 0,
    service: '',
    doctorName: '',
}

/* View event elements */
let viewEventName,
    viewEventDescription,
    viewEventStatus,
    viewStartDate,
    viewPatientName,
    viewEndDate,
    viewModal,
    viewService,
    viewUId,
    viewAmount

/* -------------------------------------------------
 | BOOTSTRAP
 -------------------------------------------------*/
function loadAppointmentCalendar () {
    initCalendarApp()
    initFeedbackCalendarApp()
    initModal()
}

/* -------------------------------------------------
 | APPOINTMENT CALENDAR
 -------------------------------------------------*/
const initCalendarApp = function () {
    if (!$('#adminAppointmentCalendar').length || usersRole === 'patient') return

    const calendarEl = document.getElementById('adminAppointmentCalendar')
    const lang = $('.currentLanguage').val()

    appointmentCalendar = new FullCalendar.Calendar(calendarEl, {
        locale: lang,
        themeSystem: 'bootstrap5',
        height: 750,
        headerToolbar: {
            left: 'title',
            center: 'prev,next today',
            right: 'dayGridDay,dayGridMonth',
        },
        initialDate: new Date(),
        timeZone: 'UTC',
        dayMaxEvents: true,

        events: (info, success, fail) => {
            $.get(route('appointments.calendar'), info)
                .done(res => res.success && success(res.data))
                .fail(err => {
                    displayErrorMessage(err.responseJSON.message)
                    fail()
                })
        },

        eventClick: arg => handleEventClick(arg, 'appointment'),
        eventMouseEnter: arg => handleMouseEnter(arg),
        eventMouseLeave: hidePopovers,
    })

    appointmentCalendar.render()
}

/* -------------------------------------------------
 | FEEDBACK CALENDAR
 -------------------------------------------------*/
const initFeedbackCalendarApp = function () {
    if (!$('#adminFeedbackAppointmentCalendar').length || usersRole === 'patient') return

    const calendarEl = document.getElementById('adminFeedbackAppointmentCalendar')
    const lang = $('.currentLanguage').val()

    feedbackCalendar = new FullCalendar.Calendar(calendarEl, {
        locale: lang,
        themeSystem: 'bootstrap5',
        height: 750,
        headerToolbar: {
            left: 'title',
            center: 'prev,next today',
            right: 'dayGridDay,dayGridMonth',
        },
        initialDate: new Date(),
        timeZone: 'UTC',
        dayMaxEvents: true,

        events: (info, success, fail) => {
            $.get(route('feedback-appointments.calendar'), info)
                .done(res => res.success && success(res.data))
                .fail(err => {
                    displayErrorMessage(err.responseJSON.message)
                    fail()
                })
        },

        eventClick: arg => handleEventClick(arg, 'feedback'),
        eventMouseEnter: arg => handleMouseEnter(arg),
        eventMouseLeave: hidePopovers,
    })

    feedbackCalendar.render()
}

/* -------------------------------------------------
 | EVENT HANDLERS
 -------------------------------------------------*/
function handleEventClick (arg, type) {
    hidePopovers()
    activeCalendarType = type
    appointmentStatusId = arg.event.id

    formatArgs(arg.event)
    showEventModal()
}

function handleMouseEnter (arg) {
    formatArgs(arg.event)
    initPopovers(arg.el)
}

/* -------------------------------------------------
 | MODAL
 -------------------------------------------------*/
const initModal = () => {
    if (!$('#eventModal').length) return

    const el = document.getElementById('eventModal')
    viewModal = new bootstrap.Modal(el)

    viewEventName = el.querySelector('[data-calendar="event_name"]')
    viewPatientName = el.querySelector('[data-calendar="event_patient_name"]')
    viewEventDescription = el.querySelector('[data-calendar="event_description"]')
    viewEventStatus = el.querySelector('[data-calendar="event_status"]')
    viewAmount = el.querySelector('[data-calendar="event_amount"]')
    viewUId = el.querySelector('[data-calendar="event_uId"]')
    viewService = el.querySelector('[data-calendar="event_service"]')
    viewStartDate = el.querySelector('[data-calendar="event_start_date"]')
    viewEndDate = el.querySelector('[data-calendar="event_end_date"]')
}

const showEventModal = () => {
    viewModal.show()

    viewEventName.innerText = Lang.get('js.doctor') + data.doctorName
    viewPatientName.innerText = Lang.get('js.patient') + data.patientName

    viewStartDate.innerText =
        ': ' + moment(data.startDate).utc().format('DD MMM, YYYY - h:mm A')
    viewEndDate.innerText =
        ': ' + moment(data.endDate).utc().format('DD MMM, YYYY - h:mm A')

    viewAmount.innerText = addCommas(data.amount)
    viewUId.innerText = data.uId
    viewService.innerText = data.service

    populateStatusDropdown()
}

/* -------------------------------------------------
 | STATUS DROPDOWN
 -------------------------------------------------*/
function populateStatusDropdown () {
    const book = $('#bookCalenderConst').val()
    const checkIn = $('#checkInCalenderConst').val()
    const checkOut = $('#checkOutCalenderConst').val()
    const cancel = $('#cancelCalenderConst').val()

    $(viewEventStatus).html(`
        <option disabled value="${book}" ${data.eventStatus == book ? 'selected' : ''}>
            ${Lang.get('js.booked')}
        </option>
        <option value="${checkIn}" ${data.eventStatus == checkIn ? 'selected' : ''}>
            ${Lang.get('js.check_in')}
        </option>
        <option value="${checkOut}" ${data.eventStatus == checkOut ? 'selected' : ''}>
            ${Lang.get('js.check_out')}
        </option>
        <option value="${cancel}" ${data.eventStatus == cancel ? 'selected' : ''}>
            ${Lang.get('js.cancelled')}
        </option>
    `)

    $(viewEventStatus).val(data.eventStatus)
}

/* -------------------------------------------------
 | STATUS CHANGE
 -------------------------------------------------*/
listenChange('#changeAppointmentStatus', function () {
    const status = $(this).val()
    if (!status || status == data.eventStatus) return

    $.post(route('change-status', appointmentStatusId), {
        appointmentId: appointmentStatusId,
        appointmentStatus: status,
    }).done(res => {
        displaySuccessMessage(res.message)
        $('#eventModal').modal('hide')

        activeCalendarType === 'feedback'
            ? feedbackCalendar.refetchEvents()
            : appointmentCalendar.refetchEvents()
    })
})

/* -------------------------------------------------
 | HELPERS
 -------------------------------------------------*/
function formatArgs (event) {
    data.id = event.id
    data.eventName = event.title
    data.patientName = event.extendedProps.patient
    data.eventDescription = event.extendedProps.description
    data.eventStatus = event.extendedProps.status
    data.startDate = event.start
    data.endDate = event.end
    data.amount = event.extendedProps.amount
    data.uId = event.extendedProps.uId
    data.service = event.extendedProps.service
    data.doctorName = event.extendedProps.doctorName
}

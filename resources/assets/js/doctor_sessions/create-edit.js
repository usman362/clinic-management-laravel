document.addEventListener('turbo:load', loadDoctorSessionData)

function loadDoctorSessionData () {
    let doctorSessionIsEdit = $('#doctorSessionIsEdit').val();
    if (!doctorSessionIsEdit == true) {
        $('.startTimeSlot').prop('disabled', true)
        $('.endTimeSlot').prop('disabled', true)
    }

    let lang = $('.currentLanguage').val()
    $('#addHolidayBtn').flatpickr({
        "locale": lang,
        disableMobile: true,
        minDate: new Date(),
    })

    $('select[name^="startTimes"]').each(function () {
        let selectedIndex = $(this)[0].selectedIndex
        let endSelectedIndex = $(this).closest('.add-slot').find('select[name^="endTimes"] option:selected')[0].index
        let endTimeOptions = $(this).closest('.add-slot').find('select[name^="endTimes"] option')
        if (selectedIndex >= endSelectedIndex) {
            endTimeOptions.eq(selectedIndex + 1).prop('selected', true).
                trigger('change')
        }
        endTimeOptions.each(function (index) {
            if (index <= selectedIndex) {
                $(this).attr('disabled', true)
            } else {
                $(this).attr('disabled', false)
            }
        })
    })

    // Cross-block time restrictions removed: doctors can now add
    // inner / overlapping slots on the same day (e.g. a 1-hour block
    // inside a 9 AM–6 PM block) as long as slot durations differ.
    // Backend validateSlotTiming() handles overlap rules.

    // DP-04: The day-toggle fix (unchecking a day must not block save via
    // its empty `required` slot-duration select) lives as an inline
    // script in resources/views/doctor_sessions/fields.blade.php. It is
    // kept there because this compiled bundle (public/js/pages.js) can't
    // currently be rebuilt on the server, and the blade deploys via a
    // plain git pull. See DP-04 there.
}

listenChange('#selGap', function () {
    $('.startTimeSlot').prop('disabled', false)
    $('.endTimeSlot').prop('disabled', false)
})

listenClick('.add-session-time', function () {
    let doctorSessionIsEdit = $('#doctorSessionIsEdit').val();
    if (!doctorSessionIsEdit == true) {
        if ($('#selGap').val() == '') {
            return false
        }
    }

    let day = $(this).closest('.weekly-content').attr('data-day')
    let $ele = $(this)
    let weeklyEle = $(this).closest('.weekly-content')
    let gap = $('#selGap').val()
    let getSlotByGapUrl = $('#getSlotByGapUrl').val();
    $.ajax({
        url: getSlotByGapUrl,
        data: { gap: gap, day: day },
        success: function (data) {
            weeklyEle.find('.unavailable-time').html('')
            weeklyEle.find('input[name="checked_week_days[]"').
                prop('checked', true).prop('disabled', false)
            $ele.closest('.weekly-content').
                find('.session-times').
                append(data.data)
            weeklyEle.find('select[data-control="select2"]').select2()

            // New block's start time is NOT restricted to be after the
            // previous block's end time — doctors can now create an
            // inner / overlapping block (with a different slot duration)
            // on the same day. Backend enforces the overlap-with-same-
            // duration rule.
        },
    })
})

listenClick('.copy-btn', function () {
    $(this).closest('.copy-card').removeClass('show')
    $('.copy-dropdown').removeClass('show');
    let selectEle = $(this).
        closest('.weekly-content').
        find('.session-times').
        find('select')
    // check for slot is empty
    if (selectEle.length == 0) {
        $(this).
            closest('.menu-content').
            find('.copy-label .form-check-input:checked').
            each(function () {
                let weekEle = $(`.weekly-content[data-day="${$(this).val()}"]`)
                $(weekEle).find('.session-times').html('')
                weekEle.find('.weekly-row').find('.unavailable-time').remove()
                weekEle.find('.weekly-row').
                    append('<div class="unavailable-time">'+ Lang.get('js.unavailable') +'</div>')
                let dayChk = $(weekEle).
                    find('.weekly-row').
                    find('input[name="checked_week_days[]"')
                dayChk.prop('checked', false).prop('disabled', true)
            })
    } else {
        selectEle.each(function () {
            $(this).select2('destroy')
        })
        let selects = $(this).
            closest('.weekly-content').
            find('.session-times').
            find('select')
        let $cloneEle = $(this).
            closest('.weekly-content').
            find('.session-times').
            clone()
        $(this).
            closest('.menu-content').
            find('.copy-label .form-check-input:checked').
            each(function () {
                let $cloneEle2 = $cloneEle
                let currentDay = $(this).val()
                let weekEle = `.weekly-content[data-day="${currentDay}"]`
                $cloneEle2.find('select[name^="startTimes"]').
                    attr('name', `startTimes[${currentDay}][]`)
                $cloneEle2.find('select[name^="endTimes"]').
                    attr('name', `endTimes[${currentDay}][]`)
                $cloneEle2.find('select[name^="slotDurations"]').
                    attr('name', `slotDurations[${currentDay}][]`)
                $(weekEle).find('.unavailable-time').html('')
                $cloneEle2.find('.error-msg').html('')
                $(weekEle).find('.session-times').html($cloneEle2.html())
                $(weekEle).find('.session-times select').select2()
                $(weekEle).
                    find('input[name="checked_week_days[]"').
                    prop('disabled', false).prop('checked', true)
                $(selects).each(function (i) {
                    let select = this
                    $(weekEle).
                        find('.session-times').
                        find('select').
                        eq(i).
                        val($(select).val()).
                        trigger('change')
                })
            })

        $(this).
            closest('.weekly-content').
            find('.session-times').
            find('select').
            each(function () {
                $(this).select2()
            })
        $('.copy-check-input').prop('checked', false)
    }
})

listenClick('.deleteBtn', function () {
    if ($(this).
        closest('.weekly-row').
        find('.session-times').
        find('select').length <= 3) {
        let dayChk = $(this).
            closest('.weekly-row').
            find('input[name="checked_week_days[]"')
        dayChk.prop('checked', false).prop('disabled', true)
        $(this).
            closest('.weekly-row').
            append('<div class="unavailable-time">'+ Lang.get('js.unavailable') +'</div>')
    }

    // No cross-block start-time re-enabling needed; all start-time
    // options stay available so other blocks can overlap freely.

    $(this).parent().siblings('.error-msg').remove()
    $(this).parent().closest('.timeSlot').remove()
    $(this).parent().remove()
})

listenSubmit('#saveFormDoctor', function (e) {
    e.preventDefault()
    let checkedDayLength = $(
        'input[name="checked_week_days[]"]:checked').length
    if (!checkedDayLength) {
        displayErrorMessage('Please select any one day')
        return false
    }
    $(`.weekly-content`).find('.error-msg').text('')
    $.ajax({
        url: $(this).attr('action'),
        type: 'POST',
        data: new FormData($(this)[0]),
        processData: false,
        contentType: false,
        success: function (result) {
            if (result.success) {
                displaySuccessMessage(result.message)
                setTimeout(function () {
                    location.href = $('#btnBack').attr('href');
                }, 2000);
            }
        },
        error: function (result) {
            let { day, key, reason } = result.responseJSON.message || {}
            if (day === undefined) return
            // DP-02 V9: distinct messages for missing-duration vs overlap.
            let msg = reason === 'slot_duration_required'
                ? 'Please select a slot duration for this block. Each block must have an explicit slot size so it can be offered to patients booking a matching-duration service.'
                : 'Slot timing overlaps with another block of the same duration. Overlapping blocks are only allowed when their durations differ.'
            $(`.weekly-content[data-day="${day}"]`).find('.error-msg').text('')
            $(`.weekly-content[data-day="${day}"]`).
                find('.error-msg').
                eq(key).
                text(msg)
        },
        complete: function () {
        },
    })
})

listenChange('select[name^="startTimes"]', function (e) {
    let selectedIndex = $(this)[0].selectedIndex
    let endTimeOptions = $(this).
        closest('.add-slot').
        find('select[name^="endTimes"] option')
    let endSelectedIndex = $(this).
        closest('.add-slot').
        find('select[name^="endTimes"] option:selected')[0].index
    if (selectedIndex >= endSelectedIndex) {
        endTimeOptions.eq(selectedIndex + 1).
            prop('selected', true).
            trigger('change')
    }
    endTimeOptions.each(function (index) {
        if (index <= selectedIndex) {
            $(this).attr('disabled', true)
        } else {
            $(this).attr('disabled', false)
        }
    })
})

// End-time change no longer restricts the next block's start time.
// Doctors may place a later block that overlaps an earlier one as long
// as slot durations differ (enforced server-side).

listenClick('#addHolidayBtn', function () {
    let doctorSessionIsEdit = $('#doctorSessionIsEdit').val();
});

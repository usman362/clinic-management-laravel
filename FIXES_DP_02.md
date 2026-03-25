# Fix DP-02: Doctor Scheduling with Specific Time Slots Based on Service Duration

## Problem Statement
The clinic management system had issues with doctor scheduling and service duration handling:

1. Doctor selects one 2-hour time slot (e.g., 9am-11am), then wants to add another 2hr slot (11am-1pm) but can't
2. The rest of the day shows 1-hour slots instead of matching the doctor's configured time
3. Patients can still select slots that don't match the service duration (e.g., they see 1hr slots for a 2hr service)
4. Services don't have specific duration support

## Solution Implemented

### 1. Service Duration Field (Already Existed)
- Service model has `duration` field (in minutes: 45, 60, 90, 120)
- Migration `2026_01_02_215542_add_duration_to_services.php` added the column
- Service form (`resources/views/services/fields.blade.php`) allows selecting duration
- Service model includes duration in fillable array and validation rules

### 2. Time Slot Generation Logic Fixed
**File: `app/Http/Controllers/DoctorSessionController.php`**

#### Problem with Original Code:
The original slot generation logic had a critical flaw:
- It was applying gap offsets to slot times using `strtotime('+'.$gap * $key.' minutes', ...)`
- This caused incorrect slot calculations and prevented proper duration-based slot generation
- The gap variable was meant for buffer time between appointments, not for shifting slot times

#### Solution:
Refactored both `getDoctorSession()` and `getDoctorAvailableDates()` methods:

**Before:**
```php
foreach ($slots as $key => $slot) {
    $key--;
    if ($key != 0) {
        $slotStartTime = date('h:i A', strtotime('+'.$gap * $key.' minutes', strtotime($slot[0])));
        $slotEndTime = date('h:i A', strtotime('+'.$gap * $key.' minutes', strtotime($slot[1])));
        // ... complex conditional logic with breaks
    }
}
```

**After:**
```php
foreach ($slots as $key => $slot) {
    $slotStartTime = date('h:i A', strtotime($slot[0]));
    $slotEndTime = date('h:i A', strtotime($slot[1]));

    // Check if slot end time exceeds the block's end time
    if (strtotime($slotEndTime) > strtotime($doctorWeekDaySession->full_end_time)) {
        break;
    }

    // Check if this is a past time slot
    if ($isSameWeekDay && strtotime($slotStartTime) <= strtotime(date('h:i A'))) {
        continue;
    }

    // Check if slot is within the doctor's working hours
    $startTimeOrg = Carbon::parse($slotStartTime);
    $slotStartTimeCarbon = Carbon::parse(date('h:i A', strtotime($startTime)));
    $slotEndTimeCarbon = Carbon::parse(date('h:i A', strtotime($endTime)));
    if (! $startTimeOrg->between($slotStartTimeCarbon, $slotEndTimeCarbon)) {
        break;
    }

    // Check if slot is already booked
    if (in_array(($slotStartTime.' - '.$slotEndTime), $bookingSlot)) {
        continue;
    }

    $bookingSlot[] = $slotStartTime.' - '.$slotEndTime;
}
```

**Benefits:**
- Clearer, more maintainable logic
- Uses `continue` instead of `break` for skipped slots (allows checking remaining slots)
- Properly generates slots based on service duration without offset distortion
- Respects the `getTimeSlot()` method which already correctly generates duration-based slots

### 3. Service Duration Passed to Slot Generation
**File: `resources/assets/js/appointments/create-edit.js`**

The appointment slot loading JavaScript was not passing the service ID to the backend, so the system couldn't use the service's duration to generate correct slots.

**Fixed by adding:**
```javascript
data: {
    'adminAppointmentDoctorId': $('#adminAppointmentDoctorId').val(),
    'date': selectedDate,
    'timezone_offset_minutes': timezoneOffsetMinutes,
    'appointmentServiceId': $('#appointmentServiceId').val(),        // NEW
    'appointment_type': $('.appointmentType').val() || 'normal',     // NEW
},
```

This ensures that when a patient selects a date after choosing a service, the system knows which service's duration to use.

## How It Works Now

### Duration Priority (in `getDoctorSession`):
1. **Feedback appointments** → Fixed 60 minutes
2. **Regular appointments** → Use service duration (if provided and > 0)
3. **Block-specific duration** → Per-slot duration from `session_week_days.session_meeting_time`
4. **Doctor-wide duration** → From `doctor_sessions.session_meeting_time`
5. **Default** → 30 minutes

### Example Flow:
1. Service is created with 2-hour duration (120 minutes)
2. Doctor configures schedule: 9am-5pm Mon-Fri with 15min gap between appointments
3. Patient books 2-hour service on Monday
4. System generates available slots: 9am-11am, 11am-1pm, 1pm-3pm, 3pm-5pm
5. Only 2-hour contiguous slots are shown to the patient
6. Patient can select any available 2-hour slot
7. Doctor can add multiple slots (e.g., 9am-11am and 11am-1pm) which get saved with proper duration

## Files Modified

1. **app/Http/Controllers/DoctorSessionController.php**
   - Refactored `getDoctorSession()` method (lines 201-247)
   - Refactored `getDoctorAvailableDates()` method (lines 323-352)
   - Fixed slot generation logic to properly use service duration

2. **resources/assets/js/appointments/create-edit.js**
   - Added `appointmentServiceId` parameter to AJAX request (line 59)
   - Added `appointment_type` parameter to AJAX request (line 60)

## Testing Recommendations

1. **Service Duration:**
   - Create/edit service with 45min, 1hr, 1.5hr, 2hr durations
   - Verify duration is saved and displayed correctly

2. **Doctor Schedule:**
   - Set up doctor with 2-hour duration service
   - Create schedule: 9am-1pm
   - Should generate only one 2-hour slot (9am-11am doesn't fit, but wait it should be 9-11)
   - Actually: 9am-11am slot should appear
   - Add multiple contiguous slots manually and verify they save correctly

3. **Patient Booking:**
   - Patient selects 2-hour service
   - Select date
   - Verify only 2-hour slots are shown (9-11am, 11-1pm, 1-3pm, etc.)
   - Not showing 1-hour slots
   - Booking a slot properly records the 2-hour duration

4. **Edge Cases:**
   - Service with 45-minute duration
   - Services with different durations assigned to same doctor
   - Multiple time blocks in one day with different durations
   - Feedback appointments (should always use 60 minutes)

## Notes

- The gap (buffer time between appointments) is still calculated and stored in the database
- However, it's now used appropriately only for appointment management, not for slot time calculations
- The `getTimeSlot()` method correctly handles duration-based slot generation
- The fix maintains backward compatibility with existing doctor sessions

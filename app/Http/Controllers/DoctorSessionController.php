<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDoctorSessionRequest;
use App\Http\Requests\UpdateDoctorSessionRequest;
use App\Models\Appointment;
use App\Models\ClinicSchedule;
use App\Models\DoctorHoliday;
use App\Models\DoctorSession;
use App\Models\Service;
use App\Models\WeekDay;
use App\Repositories\DoctorSessionRepository;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Laracasts\Flash\Flash;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class DoctorSessionController extends AppBaseController
{
    /** @var DoctorSessionRepository */
    private $doctorSessionRepository;

    public function __construct(DoctorSessionRepository $doctorSessionRepo)
    {
        $this->doctorSessionRepository = $doctorSessionRepo;
    }

    /**
     * Display a listing of the DoctorSession.
     *
     * @return Application|Factory|View
     */
    public function index(): \Illuminate\View\View
    {
        return view('doctor_sessions.index');
    }

    /**
     * Show the form for creating a new DoctorSession.
     *
     * @return Application|Factory|View
     */
    public function create(): \Illuminate\View\View
    {
        $doctorsList = $this->doctorSessionRepository->getSyncList();

        return view('doctor_sessions.create', compact('doctorsList'));
    }

    /**
     * Store a newly created DoctorSession in storage.
     */
    public function store(CreateDoctorSessionRequest $request)
    {
        $input = $request->all();

        $result = $this->doctorSessionRepository->store($input);

        if (! $result['success']) {
            return $this->sendError($result);
        }

        return $this->sendSuccess(__('messages.flash.schedule_crete'));
    }

    /**
     * Display the specified DoctorSession.
     *
     * @return Application|Factory|View
     */
    public function show(DoctorSession $doctorSession)
    {
        if (empty($doctorSession)) {
            Flash::error(__('messages.flash.doctor_session_not_found'));

            return redirect(getDoctorSessionURL());
        }

        return view('doctor_sessions.show', compact('doctorSession'));
    }

    /**
     * Show the form for editing the specified DoctorSession.
     *
     * @return Application|Factory|View
     */
    public function edit($id)
    {
        if (getLogInUser()->hasRole('doctor')) {
            $doctorSession = DoctorSession::with('doctor.user')->whereDoctorId(getLogInUser()->doctor->id)->first();
        } else {
            $doctorSession = DoctorSession::with('doctor.user')->findOrFail($id);
        }
        $doctorsList = $this->doctorSessionRepository->getSyncList();

        if (empty($doctorSession)) {
            Flash::error(__('messages.flash.schedule_not_found'));

            return redirect(route('doctor-sessions.index'));
        }

        $sessionWeekDays = $doctorSession->sessionWeekDays;

        return view('doctor_sessions.edit', compact('doctorSession', 'doctorsList', 'sessionWeekDays'));
    }

    /**
     * Update the specified DoctorSession in storage.
     */
    public function update(UpdateDoctorSessionRequest $request, DoctorSession $doctorSession): JsonResponse
    {
        if (empty($doctorSession)) {
            return $this->sendError(__('messages.flash.doctor_session_not_found'));
        }

        $result = $this->doctorSessionRepository->updateDoctorSession($request->all(), $doctorSession);

        if (! $result['success']) {
            return $this->sendError($result);
        }

        return $this->sendSuccess(__('messages.flash.schedule_update'));
    }

    /**
     * Remove the specified DoctorSession from storage.
     */
    public function destroy(DoctorSession $doctorSession): JsonResponse
    {
        try {
            DB::beginTransaction();

            $doctorSession->delete();

            $doctorSession->sessionWeekDays()->delete();

            DB::commit();

            return $this->sendSuccess(__('messages.flash.schedule_delete'));
        } catch (Exception $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function getDoctorSession(Request $request): JsonResponse
    {
        $holidaydate = $request->get('date');
        $doctorId = $request->get('adminAppointmentDoctorId');
        $timezone_offset_minutes = $request->get('timezone_offset_minutes');
        $doctor_holiday = DoctorHoliday::where('doctor_id', $doctorId)->where('date', $holidaydate)->get();
        $serviceId = $request->get('appointmentServiceId') ?? $request->appointmentServiceId;
        $service = Service::find($serviceId);
        // Feedback appointments always have a fixed 60-minute duration
        if ($request->get('appointment_type') === 'feedback') {
            $duration = 60;
        } else {
            $duration = ($service && $service->duration > 0) ? $service->duration : 0;
        }
        // DP-02 diagnostic: trace slot duration calculation
        \Log::debug('DP-02 slot calc', [
            'serviceId' => $serviceId,
            'service_name' => $service->name ?? 'NOT FOUND',
            'service_duration' => $service->duration ?? 'N/A',
            'resolved_duration' => $duration,
            'doctor_id' => $doctorId,
            'date' => $holidaydate,
        ]);

        if (! $doctor_holiday->count() == 0) {
            return $this->sendError(__('messages.flash.doctor_not_available'));
        }
        // Convert minutes to seconds
        $timezone_name = timezone_name_from_abbr('', $timezone_offset_minutes * 60, false);
        $date = Carbon::createFromFormat('Y-m-d', $request->date);
        $doctorWeekDaySessions = WeekDay::whereDayOfWeek($date->dayOfWeek)->whereDoctorId($doctorId)->with('doctorSession')->get();
        if ($doctorWeekDaySessions->count() == 0) {
            if (! empty(getLogInUser()->language)) {
                App::setLocale(getLogInUser()->language);
            } else {
                App::setLocale($request->session()->get('languageName'));
            }

            return $this->sendError(__('messages.flash.no_available_slots'));
        }
        // dd($doctorWeekDaySessions);

        // Include BOOKING_PENDING (status=5) so two patients booking concurrently
        // can't grab the same slot before payment confirmation.
        $appointments = Appointment::whereDoctorId($doctorId)->whereIn('status',
            [Appointment::BOOKED, Appointment::CHECK_IN, Appointment::CHECK_OUT, Appointment::BOOKING_PENDING])->get();
        $bookedSlot = [];
        $bookingSlot = [];
        foreach ($appointments as $appointment) {
            if ($appointment->date == $request->date) {
                $bookedSlot[] = $appointment->from_time.' '.$appointment->from_time_type.' - '.$appointment->to_time.' '.$appointment->to_time_type;
            }
        }

        foreach ($doctorWeekDaySessions as $index => $doctorWeekDaySession) {
            date_default_timezone_set($timezone_name);

            $doctorSession = $doctorWeekDaySession->doctorSession;
            // convert 12 hours to 24 hours
            $startTime = date('H:i', strtotime($doctorWeekDaySession->full_start_time));
            $endTime = date('H:i', strtotime($doctorWeekDaySession->full_end_time));

            // DP-02 V9: Block's session_meeting_time IS the slot size and is
            // the ONLY source of truth when a patient books a specific-duration
            // service. The previous fallback to the session's global
            // session_meeting_time leaked 1-hour blocks into 2-hour services
            // (and vice-versa) because stale global values matched any request.
            //
            // Strict rule now:
            //   - Service duration specified (patient booking) →
            //     block MUST have explicit session_meeting_time equal to the
            //     service duration. Blocks with NULL/0 duration are treated
            //     as ambiguous and silently skipped.
            //   - No service duration (admin/calendar check without service)
            //     → fall back to session-level / default so we still generate
            //     *some* slot preview for the admin UI.
            $rawBlockMeeting = $doctorWeekDaySession->session_meeting_time;
            $blockDuration   = null;
            if ($rawBlockMeeting !== null && $rawBlockMeeting !== '' && (int) $rawBlockMeeting > 0) {
                $blockDuration = (int) $rawBlockMeeting;
            }

            if ((int) $duration > 0) {
                // Patient booking flow — strict match only.
                if ($blockDuration === null || $blockDuration !== (int) $duration) {
                    \Log::debug('DP-02 V9 skip block (duration mismatch)', [
                        'doctor_id'      => $doctorId,
                        'block_id'       => $doctorWeekDaySession->id,
                        'block_start'    => $startTime,
                        'block_end'      => $endTime,
                        'block_duration' => $blockDuration,
                        'service_duration' => (int) $duration,
                    ]);
                    continue;
                }
                $slotDuration = $blockDuration;
            } else {
                // Admin / no-service flow — permissive fallback
                if ($blockDuration === null) {
                    $blockDuration = (int) ($doctorSession->session_meeting_time ?? 0);
                }
                if ($blockDuration <= 0) {
                    $blockDuration = 30;
                }
                $slotDuration = $blockDuration;
            }

            \Log::debug('DP-02 V9 use block', [
                'doctor_id'      => $doctorId,
                'block_id'       => $doctorWeekDaySession->id,
                'block_start'    => $startTime,
                'block_end'      => $endTime,
                'block_duration' => $blockDuration,
                'service_duration' => (int) $duration,
            ]);

            $slots = $this->getTimeSlot($slotDuration, $startTime, $endTime);
            $gap = $doctorSession->session_gap;
            $isSameWeekDay = (Carbon::now()->dayOfWeek == $date->dayOfWeek) && (Carbon::now()->isSameDay($date));
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
        }

        $slots = [
            'bookedSlot' => ! empty($bookedSlot) ? $bookedSlot : null,
            // CP-23: Defensive de-dup. The per-iteration in_array() check
            // above misses cases where two `WeekDay` records exist for the
            // same weekday (admin error in the schedule editor) or where a
            // schedule block crosses midnight and produces overlapping
            // wrapped slots. array_unique catches all of them in one go.
            'slots'      => array_values(array_unique($bookingSlot)),
        ];

        return $this->sendResponse($slots, __('messages.flash.retrieve'));
    }

    /**
     * Return list of dates in a range that have at least one available slot for the doctor.
     * Used by the frontend to enable only those dates in the calendar and highlight them.
     *
     * @return JsonResponse
     */
    public function getDoctorAvailableDates(Request $request): JsonResponse
    {
        $doctorId = $request->get('adminAppointmentDoctorId');
        $serviceId = $request->get('appointmentServiceId');
        $startDate = $request->get('start_date', now()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->addDays(60)->format('Y-m-d'));
        $timezone_offset_minutes = $request->get('timezone_offset_minutes', 0);

        if (! $doctorId) {
            return $this->sendError(__('messages.flash.doctor_not_available'));
        }

        $service = $serviceId ? Service::find($serviceId) : null;
        $duration = $service ? $service->duration : 0;

        $timezone_name = timezone_name_from_abbr('', (int) $timezone_offset_minutes * 60, false);
        $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

        $availableDates = [];
        $date = $start->copy();

        while ($date->lte($end)) {
            $dateStr = $date->format('Y-m-d');
            $doctor_holiday = DoctorHoliday::where('doctor_id', $doctorId)->where('date', $dateStr)->exists();
            if ($doctor_holiday) {
                $date->addDay();
                continue;
            }

            $doctorWeekDaySessions = WeekDay::whereDayOfWeek($date->dayOfWeek)->whereDoctorId($doctorId)->with('doctorSession')->get();
            if ($doctorWeekDaySessions->count() === 0) {
                $date->addDay();
                continue;
            }

            $appointments = Appointment::whereDoctorId($doctorId)
                ->where('date', $dateStr)
                ->whereIn('status', [Appointment::BOOKED, Appointment::CHECK_IN, Appointment::CHECK_OUT, Appointment::BOOKING_PENDING])
                ->get();
            $bookedSlot = $appointments->map(function ($a) {
                return $a->from_time.' '.$a->from_time_type.' - '.$a->to_time.' '.$a->to_time_type;
            })->all();

            $bookingSlot = [];
            foreach ($doctorWeekDaySessions as $doctorWeekDaySession) {
                date_default_timezone_set($timezone_name);
                $doctorSession = $doctorWeekDaySession->doctorSession;
                $startTime = date('H:i', strtotime($doctorWeekDaySession->full_start_time));
                $endTime = date('H:i', strtotime($doctorWeekDaySession->full_end_time));

                // DP-02 V9: Strict per-block duration match — see detailed
                // comment in getDoctorSession(). Ambiguous (null/0) blocks
                // are skipped for specific-service queries to prevent mis-
                // matched slots from leaking into the availability list.
                $rawBlockMeeting = $doctorWeekDaySession->session_meeting_time;
                $blockDuration   = null;
                if ($rawBlockMeeting !== null && $rawBlockMeeting !== '' && (int) $rawBlockMeeting > 0) {
                    $blockDuration = (int) $rawBlockMeeting;
                }
                if ((int) $duration > 0) {
                    if ($blockDuration === null || $blockDuration !== (int) $duration) {
                        continue;
                    }
                    $slotDuration = $blockDuration;
                } else {
                    if ($blockDuration === null) {
                        $blockDuration = (int) ($doctorSession->session_meeting_time ?? 0);
                    }
                    if ($blockDuration <= 0) {
                        $blockDuration = 30;
                    }
                    $slotDuration = $blockDuration;
                }

                $slots = $this->getTimeSlot($slotDuration, $startTime, $endTime);
                $gap = $doctorSession->session_gap;
                $isSameWeekDay = (Carbon::now()->dayOfWeek == $date->dayOfWeek) && (Carbon::now()->isSameDay($date));

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
                    $slotStr = $slotStartTime.' - '.$slotEndTime;
                    if (in_array($slotStr, $bookingSlot)) {
                        continue;
                    }

                    $bookingSlot[] = $slotStr;
                }
            }

            $hasAvailableSlot = false;
            foreach ($bookingSlot as $slot) {
                if (! in_array($slot, $bookedSlot)) {
                    $hasAvailableSlot = true;
                    break;
                }
            }
            if ($hasAvailableSlot) {
                $availableDates[] = $dateStr;
            }
            $date->addDay();
        }

        return $this->sendResponse(['dates' => $availableDates], __('messages.flash.retrieve'));
    }

    /**
     * @throws Exception
     */
    public function getTimeSlot($interval, $start_time, $end_time)
    {
        $start = new DateTime($start_time);
        $end = new DateTime($end_time);
        $carbonStart = Carbon::createFromFormat('H:i', $start_time);
        $carbonEnd = Carbon::createFromFormat('H:i', $end_time);
        $startTime = $start->format('H:i');
        $endTime = $originalEndTime = $end->format('H:i');
        $i = 0;
        $time = [];
        while (strtotime($startTime) <= strtotime($endTime)) {
            $start = $startTime;
            $end = date('H:i', strtotime('+'.$interval.' minutes', strtotime($startTime)));
            $startTime = date('H:i', strtotime('+'.$interval.' minutes', strtotime($startTime)));
            if (! Carbon::createFromFormat('H:i', $start)->isBetween($carbonStart,
                $carbonEnd) || ! Carbon::createFromFormat('H:i', $end)->isBetween($carbonStart, $carbonEnd)) {
                break;
            }
            $i++;
            if (strtotime($startTime) <= strtotime($endTime)) {
                $time[$i][] = $start;
                $time[$i][] = $end;
            }
            if (strtotime($startTime) >= strtotime($originalEndTime)) {
                break;
            }
            if (strtotime($start) >= strtotime($end)) {
                break;
            }
        }

        return $time;
    }

    /**
     * @return mixed
     */
    public function getSlotByGap(Request $request)
    {
        $gap = $request->get('gap');
        $day = $request->get('day');
        $clinicSchedule = ClinicSchedule::whereDayOfWeek($day)->first();
        $slots = getSlotByGap($clinicSchedule->start_time, $clinicSchedule->end_time);
        $html = view('doctor_sessions.slot', ['slots' => $slots, 'day' => $day])->render();

        return $this->sendResponse($html, __('messages.flash.retrieve'));
    }

    /**
     * @return Application|Factory|View|RedirectResponse|Redirector
     */
    public function doctorScheduleEdit()
    {
        $doctorSession = DoctorSession::whereDoctorId(getLogInUser()->doctor->id)->first();
        if (empty($doctorSession)) {
            Flash::error(__('messages.flash.schedule_not_found'));

            return redirect(route('doctor-sessions.index'));
        }

        $doctorsList = $this->doctorSessionRepository->getSyncList();

        $sessionWeekDays = $doctorSession->sessionWeekDays;

        return view('doctor_sessions.edit', compact('doctorSession', 'doctorsList', 'sessionWeekDays'));
    }
}

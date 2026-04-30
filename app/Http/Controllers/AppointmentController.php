<?php

namespace App\Http\Controllers;

use App\Events\DeleteAppointmentFromGoogleCalendar;
use App\Http\Requests\CreateAppointmentRequest;
use App\Http\Requests\CreateFrontAppointmentRequest;
use App\Models\Appointment;
use App\Models\AppointmentDraft;
use App\Models\Doctor;
use App\Models\Document;
use App\Models\Notification;
use App\Models\Package;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserGoogleAppointment;
use App\Repositories\AppointmentRepository;
use App\Repositories\GoogleCalendarRepository;
use \PDF;
use Carbon\Carbon;
use Exception;
use Flash;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\HigherOrderBuilderProxy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AppointmentController extends AppBaseController
{
    /** @var AppointmentRepository */
    private $appointmentRepository;

    public function __construct(AppointmentRepository $appointmentRepo)
    {
        $this->appointmentRepository = $appointmentRepo;
    }

    /**
     * @return Application|Factory|View
     */
    public function index(): \Illuminate\View\View
    {
        $allPaymentStatus = getAllPaymentStatus();
        $paymentStatus = Arr::except($allPaymentStatus, [Appointment::MANUALLY]);
        $paymentGateway = getPaymentGateway();
        return view('appointments.index', compact('allPaymentStatus', 'paymentGateway', 'paymentStatus'));
    }

    /**
     * Show the form for creating a new Appointment.
     *
     * @return Application|Factory|View
     */
    public function create(): \Illuminate\View\View
    {
        $data = $this->appointmentRepository->getData();
        $data['doctorsWithJotform'] = Doctor::with('user')
            ->whereHas('user', fn ($q) => $q->where('status', User::ACTIVE))
            ->whereNotNull('jotform_link')
            ->where('jotform_link', '!=', '')
            ->get();
        return view('appointments.create', compact('data'));
    }

    /**
     * Store consent form PDF for the logged-in patient (e.g. after Jotform redirect or upload).
     * Saves to the client's documents in public/uploads/documents/user_{user_id}.
     * Use this URL as Jotform redirect target or as form action for consent PDF upload.
     */
    public function storeConsentDocument(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240', // 10MB for PDF
            'title' => 'nullable|string|max:255',
            'doctor_id' => 'nullable|exists:doctors,id',
            'appointment_id' => 'nullable|exists:appointments,id',
        ]);

        $user = getLogInUser();
        if (! $user->hasRole('patient') || ! $user->patient) {
            return $this->sendError(__('messages.common.unauthorized'), 403);
        }

        $file = $request->file('file');
        $userId = $user->id;
        $path = $file->store('documents/user_' . $userId, 'public');

        $title = $request->input('title', 'Consent Form');
        if ($request->doctor_id) {
            $doctor = Doctor::with('user')->find($request->doctor_id);
            if ($doctor) {
                $title = 'Consent Form - ' . $doctor->user->full_name;
            }
        }

        Document::create([
            'user_id' => $userId,
            'uploaded_by' => $userId,
            'title' => $title,
            'type' => 'consent',
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => (int) round($file->getSize() / 1024),
            'doctor_id' => $request->input('doctor_id'),
            'appointment_id' => $request->input('appointment_id'),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return $this->sendResponse(['path' => $path], __('Document uploaded successfully.'));
        }

        return redirect()->back()->with('success', __('Document uploaded successfully.'));
    }

    /**
     * @throws ApiErrorException
     */
    public function store(Request $request)
    {
        // dd($request->all());
        // if (getLogInUser()->hasRole('patient')) {
        //     $request->validate([
        //         'date' => 'required',
        //         'payable_amount' => 'required',
        //         'from_time' => 'required',
        //         'to_time' => 'required',
        //         'payment_type' => 'required',
        //     ]);
        // } else {
        //     $request->validate([
        //         'doctor_id' => 'required',
        //         'patient_id' => 'required',
        //         'service_id' => 'required',
        //         'payable_amount' => 'required',
        //     ]);
        // }

        $input = $request->all();
        $appointment = $this->appointmentRepository->store($input);

        $url = route('appointments.index');

        if (getLogInUser()->hasRole('patient')) {
            $url = route('patients.patient-appointments-index');
        }
        $data = [
            'url' => $url,
        ];

        return $this->sendResponse($data, __('messages.flash.appointment_create'));
    }

    /**
     * Display the specified Appointment.
     *
     * @return Application|RedirectResponse|Redirector
     */
    public function show(Appointment $appointment)
    {
        $allPaymentStatus = getAllPaymentStatus();
        if (getLogInUser()->hasRole('doctor')) {
            $doctor = Appointment::whereId($appointment->id)->whereDoctorId(getLogInUser()->doctor->id);
            if (! $doctor->exists()) {
                return redirect()->back();
            }
        } elseif (getLogInUser()->hasRole('patient')) {
            $patient = Appointment::whereId($appointment->id)->wherePatientId(getLogInUser()->patient->id);
            if (! $patient->exists()) {
                return redirect()->back();
            }
        }

        $appointment = $this->appointmentRepository->showAppointment($appointment);

        if (empty($appointment)) {
            Flash::error(__('messages.flash.appointment_not_found'));

            if (getLogInUser()->hasRole('patient')) {
                return redirect(route('patients.patient-appointments-index'));
            } else {
                return redirect(route('admin.appointments.index'));
            }
        }

        if (getLogInUser()->hasRole('patient')) {
            return view('patient_appointments.show')->with('appointment', $appointment);
        } else {
            return view('appointments.show')->with('appointment', $appointment)
                ->with('allPaymentStatus', $allPaymentStatus)
                ->with([
                    'paid' => Appointment::PAID,
                    'pending' => Appointment::PENDING,
                ])
                ->with([
                    'all' => Appointment::ALL,
                    'book' => Appointment::BOOKED,
                    'pending' => Appointment::BOOKING_PENDING,
                    'checkIn' => Appointment::CHECK_IN,
                    'checkOut' => Appointment::CHECK_OUT,
                    'cancel' => Appointment::CANCELLED,
                ]);
        }
    }

    public function packageDetails($id)
    {
        $allPaymentStatus = getAllPaymentStatus();
        $appointment = Appointment::find($id);
        if (getLogInUser()->hasRole('doctor')) {
            $doctor = Appointment::whereId($appointment->id)->whereDoctorId(getLogInUser()->doctor->id);
            if (! $doctor->exists()) {
                return redirect()->back();
            }
        } elseif (getLogInUser()->hasRole('patient')) {
            $patient = Appointment::whereId($appointment->id)->wherePatientId(getLogInUser()->patient->id);
            if (! $patient->exists()) {
                return redirect()->back();
            }
        }

        $appointment = $this->appointmentRepository->showAppointment($appointment);

        if (empty($appointment)) {
            Flash::error(__('messages.flash.appointment_not_found'));

            if (getLogInUser()->hasRole('patient')) {
                return redirect(route('patients.patient-appointments-index'));
            } else {
                return redirect(route('admin.appointments.index'));
            }
        }

        if (getLogInUser()->hasRole('patient')) {
            return view('patient_appointments.show')->with('appointment', $appointment);
        } else {
            return view('appointments.show_package')->with('appointment', $appointment)
                ->with('allPaymentStatus', $allPaymentStatus)
                ->with([
                    'paid' => Appointment::PAID,
                    'pending' => Appointment::PENDING,
                ])
                ->with([
                    'all' => Appointment::ALL,
                    'book' => Appointment::BOOKED,
                    'pending' => Appointment::BOOKING_PENDING,
                    'checkIn' => Appointment::CHECK_IN,
                    'checkOut' => Appointment::CHECK_OUT,
                    'cancel' => Appointment::CANCELLED,
                ]);
        }
    }

    /**
     * Patient booking via token URL (appointment_unique_id).
     * Resolves the token to the actual appointment and delegates to edit().
     */
    public function bookByToken(string $token): \Illuminate\View\View
    {
        $appointment = Appointment::where('appointment_unique_id', $token)->firstOrFail();

        // AP-11: Block rebooking of appointments that were cancelled because
        // the clinic removed the service from the package. The UI hides the
        // rebook icon for these, but we defend the server side too in case
        // someone pastes an old token URL directly.
        if ($appointment->cancel_reason === 'clinic_removed') {
            abort(403, 'This appointment was removed from the package by the clinic and cannot be rebooked. Please contact the clinic to book a new appointment.');
        }

        return $this->edit($appointment->id);
    }

    public function edit($id): \Illuminate\View\View
    {
        // CP-08: Eager-load patient.user + address so blade data-profile-* attributes
        // are populated correctly (prevents null address causing empty profile data).
        $appointment = Appointment::with(['patient.user.address', 'patient.address', 'doctor.user', 'services'])->findOrFail($id);

        if (getLogInUser()->hasRole('patient')) {
            if ($appointment->patient_id !== getLogInUser()->patient->id) {
                abort(403, __('messages.common.not_allow__assess_record'));
            }
        }

        // Feedback appointments use their own edit view
        if ($appointment->appointment_type === 'feedback') {
            $data = $this->appointmentRepository->getData();
            // Get doctor IDs only from appointments in this feedback package (same relation_id)
            $packageDoctorIds = Appointment::where('relation_id', $appointment->relation_id)
                ->whereNotNull('doctor_id')
                ->pluck('doctor_id')
                ->unique();
            $doctors = Doctor::whereIn('id', $packageDoctorIds)->with('user')->get()->where('user.status', User::ACTIVE)->pluck('user.full_name', 'id');
            $doctorsWithJotform = Doctor::whereIn('id', $packageDoctorIds)->with('user')
                ->whereHas('user', fn ($q) => $q->where('status', User::ACTIVE))
                ->whereNotNull('jotform_link')
                ->where('jotform_link', '!=', '')
                ->get();
            return view('feedback_appointments.edit', compact('data', 'appointment', 'doctors', 'doctorsWithJotform'));
        }

        $data = $this->appointmentRepository->getData();

        // Collect all doctor IDs from every appointment in this package (relation_id group)
        $packageDoctorIds = Appointment::where('relation_id', $appointment->relation_id)
            ->whereNotNull('doctor_id')
            ->pluck('doctor_id')
            ->unique();
        $docServices = DB::table('service_doctor')->where('service_id', $appointment->service_id)->pluck('doctor_id');
        $doctors = Doctor::whereIn('id', $docServices)->with('user')->get()->where('user.status', User::ACTIVE)->pluck('user.full_name', 'id');
        // Include all package doctors for consent forms (only those with a jotform_link)
        $fullDoctors = Doctor::whereIn('id', $packageDoctorIds)
            ->whereNotNull('jotform_link')
            ->where('jotform_link', '!=', '')
            ->with('user')
            ->get()
            ->unique('id');

        // Determine booking mode for patient (normal edit vs rebook of a cancelled appointment)
        $bookingMode = 'edit';
        if (getLogInUser()->hasRole('patient') && $appointment->status === Appointment::CANCELLED) {
            $bookingMode = 'rebook';
        }

        return view('appointments.edit', compact('data', 'appointment', 'doctors', 'fullDoctors', 'bookingMode'));
    }

    public function update(Request $request, $id)
    {
        // dd($request->all());
        // if (getLogInUser()->hasRole('patient')) {
        //     $request->validate([
        //         'date' => 'required',
        //         'payable_amount' => 'required',
        //         'from_time' => 'required',
        //         'to_time' => 'required',
        //         'payment_type' => 'required',
        //     ]);
        // } else {
        //     $request->validate([
        //         'doctor_id' => 'required',
        //         'patient_id' => 'required',
        //         'service_id' => 'required',
        //         'payable_amount' => 'required',
        //     ]);
        // }

        $input = $request->all();
        $appointment = $this->appointmentRepository->update($input, $id);

        if (getLogInUser()->hasRole('patient')) {
            AppointmentDraft::where('user_id', getLogInUser()->id)
                ->where('appointment_id', $id)
                ->delete();
        }

        $url = route('appointments.index');

        if (getLogInUser()->hasRole('patient')) {
            // CP-27: Patient post-booking redirects standardised to the
            // redesigned `patients.patient-appointments-index` list. The
            // old `patients.booking.detail` (assessment) and
            // `patients.patient-bookings-feedback` (feedback) pages were
            // the legacy minimal layout — the unified Appointments page
            // shows date/time, status, doctor with the new design and is
            // the single place patients now manage all their bookings.
            $url = route('patients.patient-appointments-index');
        }
        $data = [
            'url' => $url,
            'appointmentId' => $appointment->id,
        ];

        return $this->sendResponse($data, __('messages.flash.appointment_create'));
    }

    /**
     * Get saved draft for patient appointment edit (AJAX).
     */
    public function getDraft($id): JsonResponse
    {
        $user = getLogInUser();
        if (! $user->hasRole('patient') || ! $user->patient) {
            \Log::debug('CP-08 getDraft: unauthorized', ['user_id' => $user?->id]);
            return $this->sendError(__('messages.common.unauthorized'), 403);
        }
        $appointment = Appointment::where('id', $id)->where('patient_id', $user->patient->id)->firstOrFail();
        $draft = AppointmentDraft::where('user_id', $user->id)->where('appointment_id', $id)->first();
        \Log::debug('CP-08 getDraft', ['appointment_id' => $id, 'has_draft' => (bool) $draft]);
        return $this->sendResponse($draft ? $draft->form_data : null, __('messages.flash.retrieve'));
    }

    /**
     * Save draft for patient appointment edit (AJAX). Step-wise data is merged.
     */
    public function saveDraft(Request $request, $id): JsonResponse
    {
        $user = getLogInUser();
        if (! $user->hasRole('patient') || ! $user->patient) {
            return $this->sendError(__('messages.common.unauthorized'), 403);
        }
        $appointment = Appointment::where('id', $id)->where('patient_id', $user->patient->id)->firstOrFail();
        $payload = $request->validate(['form_data' => 'required|array']);
        $formData = $payload['form_data'];
        AppointmentDraft::updateOrCreate(
            ['user_id' => $user->id, 'appointment_id' => $id],
            ['form_data' => $formData]
        );
        return $this->sendResponse(null, __('Draft saved.'));
    }

    /**
     * Remove the specified Appointment from storage.
     *
     * AP-16: Soft-delete cascade. The trash button now stamps a shared
     * `delete_batch_id` (ULID) on the Package + every appointment with the
     * same relation_id + every child feedback Package + their appointments,
     * then soft-deletes those rows (deleted_at). Transactions, consent
     * documents, Google calendar links are NOT touched — they stay linked
     * so a Restore puts the package back exactly as it was.
     *
     * The original V8 hard-cascade is preserved as `hardDeleteCascade()`
     * and is invoked by `PackageTrashController::forceDestroy()` when the
     * admin permanently deletes a trashed package.
     */
    public function destroy(Appointment $appointment): JsonResponse
    {
        if (getLogInUser()->hasRole('patient')) {
            if ($appointment->patient_id !== getLogInUser()->patient->id) {
                return $this->sendError('Seems, you are not allowed to access this record.');
            }
        }

        $relationId = $appointment->relation_id;
        // Capture patient user id BEFORE the transaction. We need it for
        // the notification fired after commit, and the appointment row may
        // be soft-deleted before then (relations on a trashed model still
        // resolve, but it's clearer to read it once up front).
        $patientUserId = optional(optional($appointment->patient)->user)->id
            ?? Patient::where('id', $appointment->patient_id)->value('user_id');
        $deletedCount  = 0;
        $batchId       = (string) Str::ulid();

        DB::transaction(function () use ($appointment, $relationId, $batchId, &$deletedCount) {
            if ($relationId) {
                $deletedCount = $this->softDeletePackageCascade($relationId, $batchId);
            } else {
                // Orphan appointment without a package — cancel in place
                // (same pattern as packaged appointments). Hard-delete
                // path is reserved for the trash's "Permanent Delete"
                // action which only operates on Package rows.
                if ((int) $appointment->status !== Appointment::CANCELLED) {
                    $appointment->pre_cancel_status = (int) $appointment->status;
                    $appointment->status            = Appointment::CANCELLED;
                    $appointment->cancel_reason     = 'clinic_removed';
                }
                $appointment->delete_batch_id = $batchId;
                $appointment->save();
                $deletedCount = 1;
            }
        });

        // AP-03: Notify the patient that the package + all appointments were
        // cancelled. Only when an admin/doctor triggered the delete — if the
        // patient themselves deleted it there is no value notifying them.
        if ($patientUserId && ! getLogInUser()->hasRole('patient')) {
            try {
                Notification::create([
                    'title'   => $relationId
                        ? 'Your booking package and ' . $deletedCount . ' appointment' . ($deletedCount === 1 ? '' : 's') . ' have been cancelled by the clinic.'
                        : 'Your appointment has been cancelled by the clinic.',
                    'type'    => Notification::CANCELED,
                    'user_id' => $patientUserId,
                ]);
            } catch (\Throwable $e) {
                \Log::warning('AP-16: Failed to create cancellation notification', [
                    'user_id' => $patientUserId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return $this->sendSuccess(__('messages.flash.appointment_delete'));
    }

    /**
     * AP-16: Soft-delete the Package row(s) identified by $relationId
     * (plus any child feedback packages) and CANCEL their appointments
     * in place — the appointments stay visible to the patient as
     * "Cancelled by clinic" with the rebook button suppressed via the
     * existing `cancel_reason = 'clinic_removed'` view guards. Each
     * affected row (Package + Appointment) is stamped with the shared
     * $batchId so Restore can reverse exactly this set later.
     *
     * Appointments that were ALREADY cancelled at the time of the trash
     * have their status / cancel_reason left intact (preserves any prior
     * cancellation history) — only the batch id is stamped on them so
     * Restore can still find them.
     *
     * Returns the total number of appointments touched (used in the
     * patient notification message).
     */
    private function softDeletePackageCascade(string $relationId, string $batchId): int
    {
        $count = 0;

        $count += $this->cancelAppointmentsForBatch($relationId, $batchId);

        // The Package row itself — soft-delete + stamp batch id.
        $primaryPkg = Package::where('relation_id', $relationId)->first();
        if ($primaryPkg) {
            $primaryPkg->delete_batch_id = $batchId;
            $primaryPkg->save();
            $primaryPkg->delete();

            // Cascade to child feedback packages (only assessment packages
            // have children; this is a no-op for feedback packages).
            $feedbackPkgs = Package::where('parent_package_id', $primaryPkg->id)->get();
            foreach ($feedbackPkgs as $fbPkg) {
                $count += $this->cancelAppointmentsForBatch($fbPkg->relation_id, $batchId);
                $fbPkg->delete_batch_id = $batchId;
                $fbPkg->save();
                $fbPkg->delete();
            }
        }

        return $count;
    }

    /**
     * AP-16 helper: cancel every appointment under a given relation_id and
     * stamp them with the shared $batchId. Already-cancelled rows are
     * stamped only (preserve original status + cancel_reason).
     */
    private function cancelAppointmentsForBatch(string $relationId, string $batchId): int
    {
        $appts = Appointment::where('relation_id', $relationId)->get();
        foreach ($appts as $appt) {
            if ((int) $appt->status === Appointment::CANCELLED) {
                // Preserve prior cancellation; just link to this batch.
                $appt->delete_batch_id = $batchId;
                $appt->save();
                continue;
            }
            $appt->pre_cancel_status = (int) $appt->status;
            $appt->status            = Appointment::CANCELLED;
            $appt->cancel_reason     = 'clinic_removed';
            $appt->delete_batch_id   = $batchId;
            $appt->save();
        }
        return $appts->count();
    }

    /**
     * AP-16 / AP-03 V8: Permanent (hard) delete of a package and everything
     * it owns. Mirrors the original V8 destroy logic. Invoked by
     * PackageTrashController::forceDestroy() against an already
     * soft-deleted Package row.
     *
     * Note: appointments are NOT soft-deleted (only marked CANCELLED);
     * Package IS soft-deleted, so Package queries below use
     * `Package::withTrashed()` while Appointment queries are direct.
     *
     * Returns total appointments hard-deleted.
     */
    public function hardDeleteCascade(string $relationId): int
    {
        $count = 0;

        DB::transaction(function () use ($relationId, &$count) {
            $packageAppointments = Appointment::where('relation_id', $relationId)->get();
            $apptIds       = $packageAppointments->pluck('id')->all();
            $apptUniqueIds = $packageAppointments->pluck('appointment_unique_id')->filter()->all();
            $count         = count($apptIds);

            if (! empty($apptUniqueIds)) {
                Transaction::whereIn('appointment_id', $apptUniqueIds)->delete();
            }
            if (! empty($apptIds)) {
                Document::whereIn('appointment_id', $apptIds)
                    ->where('type', 'consent')
                    ->delete();
                if (class_exists(UserGoogleAppointment::class)) {
                    UserGoogleAppointment::whereIn('appointment_id', $apptIds)->delete();
                }
            }

            // Cascade: child feedback packages
            $parentPkg = Package::withTrashed()->where('relation_id', $relationId)->first();
            if ($parentPkg) {
                $feedbackPkgs = Package::withTrashed()
                    ->where('parent_package_id', $parentPkg->id)
                    ->get();
                foreach ($feedbackPkgs as $fbPkg) {
                    $fbAppts = Appointment::where('relation_id', $fbPkg->relation_id)->get();
                    $fbApptIds  = $fbAppts->pluck('id')->all();
                    $fbApptUids = $fbAppts->pluck('appointment_unique_id')->filter()->all();
                    if (! empty($fbApptUids)) {
                        Transaction::whereIn('appointment_id', $fbApptUids)->delete();
                    }
                    if (! empty($fbApptIds)) {
                        Document::whereIn('appointment_id', $fbApptIds)
                            ->where('type', 'consent')
                            ->delete();
                        if (class_exists(UserGoogleAppointment::class)) {
                            UserGoogleAppointment::whereIn('appointment_id', $fbApptIds)->delete();
                        }
                    }
                    Appointment::where('relation_id', $fbPkg->relation_id)->delete();
                    $fbPkg->forceDelete();
                    $count += count($fbApptIds);
                }
            }

            Package::withTrashed()->where('relation_id', $relationId)->forceDelete();
            Appointment::where('relation_id', $relationId)->delete();
        });

        return $count;
    }

    /**
     * @return Application|Factory|View
     *
     * @throws Exception
     */
    public function doctorAppointment(Request $request): \Illuminate\View\View
    {
        $appointmentStatus = Appointment::ALL_STATUS;
        $paymentStatus = getAllPaymentStatus();

        return view('doctor_appointment.index', compact('appointmentStatus', 'paymentStatus'));
    }

    public function feedbackDoctorAppointment(Request $request): \Illuminate\View\View
    {
        $appointmentStatus = Appointment::ALL_STATUS;
        $paymentStatus = getAllPaymentStatus();

        return view('doctor_feedback_appointment.index', compact('appointmentStatus', 'paymentStatus'));
    }

    /**
     * @return Application|Factory|View|JsonResponse
     */
    public function doctorAppointmentCalendar(Request $request)
    {
        if ($request->ajax()) {
            $input = $request->all();
            $data = $this->appointmentRepository->getAppointmentsData();

            return $this->sendResponse($data, __('messages.flash.doctor_appointment'));
        }

        return view('doctor_appointment.calendar');
    }

    public function doctorFeedbackAppointmentCalendar(Request $request)
    {
        if ($request->ajax()) {
            $input = $request->all();
            $data = $this->appointmentRepository->getFeedbackAppointmentsData();

            return $this->sendResponse($data, __('messages.flash.doctor_appointment'));
        }

        return view('doctor_feedback_appointment.calendar');
    }

    /**
     * @return Application|Factory|View
     */
    public function patientAppointmentCalendar(Request $request)
    {
        if ($request->ajax()) {
            $input = $request->all();
            $data = $this->appointmentRepository->getPatientAppointmentsCalendar();

            return $this->sendResponse($data, __('messages.flash.patient_appointment'));
        }

        return view('appointments.patient-calendar');
    }

    /**
     * @return Application|Factory|View|JsonResponse
     */
    public function patientFeedbackAppointmentCalendar(Request $request)
    {
        if ($request->ajax()) {
            $input = $request->all();
            $data = $this->appointmentRepository->getPatientFeedbackAppointmentsCalendar();

            return $this->sendResponse($data, __('messages.flash.patient_appointment'));
        }

        return view('appointments.patient-feedback-calendar');
    }

    /**
     * @return Application|Factory|View|JsonResponse
     */
    public function appointmentCalendar(Request $request)
    {
        if ($request->ajax()) {
            $input = $request->all();
            $data = $this->appointmentRepository->getCalendar();

            return $this->sendResponse($data, __('messages.flash.appointment_retrieve'));
        }

        return view('appointments.calendar');
    }

    /**
     * @return Application|Factory|View
     */
    public function appointmentDetail(Appointment $appointment): \Illuminate\View\View
    {
        //not complate query optimize
        $appointment = $this->appointmentRepository->showDoctorAppointment($appointment);

        return view('doctor_appointment.show', compact('appointment'));
    }

    /**
     * @return mixed
     */
    public function changeStatus(Request $request)
    {
        $input = $request->all();

        if (getLogInUser()->hasRole('doctor')) {
            $doctor = Appointment::whereId($input['appointmentId'])->whereDoctorId(getLogInUser()->doctor->id);
            if (! $doctor->exists()) {
                return $this->sendError(__('messages.common.not_allow__assess_record'));
            }
        }

        $appointment = Appointment::findOrFail($input['appointmentId']);
        $appointment->update([
            'status' => $input['appointmentStatus'],
        ]);

        // CP-28: Roll up Package.status after any appointment state change
        // so listings don't show "Pending" while badges say Completed.
        Package::refreshForRelation($appointment->relation_id);

        $fullTime = $appointment->from_time . '' . $appointment->from_time_type . ' - ' . $appointment->to_time . '' . $appointment->to_time_type . ' ' . ' ' . Carbon::parse($appointment->date)->format('jS M, Y');
        // $patient = Patient::whereId($appointment->patient_id)->with('user')->first();
        $patient = Patient::whereId($appointment->patient_id)->with('user')->first();
        $doctor = Doctor::whereId($appointment->doctor_id)->with('user')->first();
        if ($input['appointmentStatus'] == Appointment::CHECK_OUT) {

            Notification::create([
                'title' => Notification::APPOINTMENT_CHECKOUT_PATIENT_MSG . ' ' . getLogInUser()->full_name,
                'type' => Notification::CHECKOUT,
                'user_id' => $patient->user_id,
            ]);
            Notification::create([
                'title' => $patient->user->full_name . '\'s appointment check out by ' . getLogInUser()->full_name . ' at ' . $fullTime,
                'type' => Notification::CHECKOUT,
                'user_id' => $doctor->user_id,
            ]);
        } elseif ($input['appointmentStatus'] == Appointment::CANCELLED) {
            $events = UserGoogleAppointment::with(['user'])->where('appointment_id', $appointment->id)->get();

            /** @var GoogleCalendarRepository $repo */
            $repo = App::make(GoogleCalendarRepository::class);

            $repo->destroy($events);

            Notification::create([
                'title' => Notification::APPOINTMENT_CANCEL_PATIENT_MSG . ' ' . getLogInUser()->full_name,
                'type' => Notification::CANCELED,
                'user_id' => $patient->user_id,
            ]);
            Notification::create([
                'title' => $patient->user->full_name . '\'s appointment cancelled by' . getLogInUser()->full_name . ' at ' . $fullTime,
                'type' => Notification::CANCELED,
                'user_id' => $doctor->user_id,
            ]);
        }

        return $this->sendSuccess(__('messages.flash.status_update'));
    }

    /**
     * @return mixed
     */
    public function cancelStatus(Request $request)
    {

        $appointment = Appointment::findOrFail($request['appointmentId']);
        if ($appointment->patient_id !== getLogInUser()->patient->id) {
            return $this->sendError(__('messages.common.not_allow__assess_record'));
        }
        // CP-21: Always clear cancel_reason on a patient-initiated cancel.
        // The same column is reused by AP-11 / V8 to mark
        // `clinic_removed` (NOT rebookable). If a row had previously been
        // marked clinic-removed and somehow ended up here, an explicit
        // `null` keeps the rebook button visible — patient cancellations
        // are always rebookable while the package is active.
        $appointment->update([
            'status'        => Appointment::CANCELLED,
            'cancel_reason' => null,
        ]);

        // CP-28: Roll up Package.status so the listing reflects the cancel.
        Package::refreshForRelation($appointment->relation_id);

        $events = UserGoogleAppointment::with('user')
            ->where('appointment_id', $appointment->id)
            ->get()
            ->groupBy('user_id');

        foreach ($events as $userID => $event) {
            $user = $event[0]->user;
            DeleteAppointmentFromGoogleCalendar::dispatch($event, $user);
        }

        $fullTime = $appointment->from_time . '' . $appointment->from_time_type . ' - ' . $appointment->to_time . '' . $appointment->to_time_type . ' ' . ' ' . Carbon::parse($appointment->date)->format('jS M, Y');
        $patient = Patient::whereId($appointment->patient_id)->with('user')->first();

        $doctor = Doctor::whereId($appointment->doctor_id)->with('user')->first();
        Notification::create([
            'title' => $patient->user->full_name . ' ' . Notification::APPOINTMENT_CANCEL_DOCTOR_MSG . ' ' . $fullTime,
            'type' => Notification::CANCELED,
            'user_id' => $doctor->user_id,
        ]);

        return $this->sendSuccess(__('messages.flash.appointment_cancel'));
    }

    /**
     * CP-21: Single-appointment cancel from the doctor / admin panel.
     *
     * The doctor's trash icon used to hit `appointments.destroy`, which
     * (since the AP-16 soft-delete cascade) wipes the ENTIRE package out
     * of view — making one click look like "all my appointments
     * disappeared". This method cancels JUST the one appointment, keeping
     * the row visible (status = CANCELLED, cancel_reason = NULL so it
     * stays rebookable per AP-11), and notifies the patient.
     */
    public function cancelStatusByActor(Request $request): JsonResponse
    {
        $appointment = Appointment::findOrFail($request['appointmentId']);

        // Doctor can only cancel their own appointments; admin can cancel any.
        if (getLogInUser()->hasRole('doctor')) {
            $doctor = getLogInUser()->doctor;
            if (! $doctor || $appointment->doctor_id !== $doctor->id) {
                return $this->sendError(__('messages.common.not_allow__assess_record'));
            }
        }

        $appointment->update([
            'status'        => Appointment::CANCELLED,
            'cancel_reason' => null,
        ]);

        // CP-28: Roll up Package.status after doctor/admin single-row cancel.
        Package::refreshForRelation($appointment->relation_id);

        // Detach Google Calendar events for this appointment, mirroring
        // the patient cancel path so neither side sees a stale slot.
        try {
            $events = UserGoogleAppointment::with('user')
                ->where('appointment_id', $appointment->id)
                ->get()
                ->groupBy('user_id');
            foreach ($events as $userID => $event) {
                $user = $event[0]->user;
                DeleteAppointmentFromGoogleCalendar::dispatch($event, $user);
            }
        } catch (\Throwable $e) {
            \Log::warning('CP-21: google calendar detach failed', ['error' => $e->getMessage()]);
        }

        // Notify the patient that this specific appointment was cancelled.
        try {
            $patient = Patient::with('user')->find($appointment->patient_id);
            if ($patient && $patient->user) {
                $when = trim(
                    ($appointment->from_time ? $appointment->from_time . ' ' . $appointment->from_time_type : '')
                    . ' - '
                    . ($appointment->to_time ? $appointment->to_time . ' ' . $appointment->to_time_type : '')
                );
                $title = 'Your appointment'
                    . ($appointment->date ? ' on ' . Carbon::parse($appointment->date)->format('jS M, Y') : '')
                    . ($when !== '-' ? ' (' . $when . ')' : '')
                    . ' has been cancelled. You can rebook it from your appointments list.';
                Notification::create([
                    'title'   => $title,
                    'type'    => Notification::CANCELED,
                    'user_id' => $patient->user->id,
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('CP-21: cancel notification failed', ['error' => $e->getMessage()]);
        }

        return $this->sendSuccess(__('messages.flash.appointment_cancel'));
    }

    /**
     * @throws ApiErrorException
     */
    public function frontAppointmentBook(CreateFrontAppointmentRequest $request): JsonResponse
    {
        app()->setLocale(checkLanguageSession());
        $input = $request->all();
        $appointment = $this->appointmentRepository->frontSideStore($input);
        if ($input['payment_type'] == Appointment::STRIPE) {
            $result = $this->appointmentRepository->createSession($appointment);

            return $this->sendResponse([
                'payment_type' => $input['payment_type'],
                $result,
            ], 'Stripe ' . __('messages.appointment.session_created_successfully'));
        }

        if ($input['payment_type'] == Appointment::PAYPAL) {
            if ($request->isXmlHttpRequest()) {
                return $this->sendResponse([
                    'redirect_url' => route('paypal.index', ['appointmentData' => $appointment]),
                    'payment_type' => $input['payment_type'],
                    'appointmentId' => $appointment->id,
                ], 'Paypal ' . __('messages.appointment.session_created_successfully'));
            }
        }

        if ($input['payment_type'] == Appointment::PAYSTACK) {
            if ($request->isXmlHttpRequest()) {
                return $this->sendResponse([
                    'redirect_url' => route('paystack.init', ['appointmentData' => $appointment]),
                    'payment_type' => $input['payment_type'],
                ], 'Paystck ' . __('messages.appointment.session_created_successfully'));
            }

            return redirect(route('paystack.init'));
        }

        if ($input['payment_type'] == Appointment::RAZORPAY) {
            return $this->sendResponse([
                'payment_type' => $input['payment_type'],
                'appointmentId' => $appointment->id,
            ], 'Razorpay ' . __('messages.appointment.session_created_successfully'));
        }

        if ($input['payment_type'] == Appointment::PAYTM) {
            return $this->sendResponse([
                'payment_type' => $input['payment_type'],
                'appointmentId' => $appointment->id,
            ], 'Paytm ' . __('messages.appointment.session_created_successfully'));
        }

        if ($input['payment_type'] == Appointment::AUTHORIZE) {
            return $this->sendResponse([
                'payment_type' => $input['payment_type'],
                'appointmentId' => $appointment->id,
            ], __('messages.appointment.authorize_session_created_successfully'));
        }

        $data['payment_type'] = $input['payment_type'];
        $data['appointmentId'] = $appointment->id;

        return $this->sendResponse($data, __('messages.flash.appointment_booked'));
    }

    public function frontHomeAppointmentBook(Request $request): RedirectResponse
    {
        $data = $request->all();

        return redirect()->route('medicalAppointment')->with(['data' => $data]);
    }

    /**
     * @return HigherOrderBuilderProxy|mixed|string
     *
     * @throws Exception
     */
    public function getPatientName(Request $request)
    {
        $checkRecord = User::whereEmail($request->email)->whereType(User::PATIENT)->first();

        if ($checkRecord != '') {
            return $this->sendResponse($checkRecord->full_name, __('messages.appointment.patient_name_retrieved'));
        }

        return false;
    }

    /**
     * @return Application|RedirectResponse|Redirector
     *
     * @throws ApiErrorException
     */
    public function paymentSuccess(Request $request): RedirectResponse
    {
        $sessionId = $request->get('session_id');
        if (empty($sessionId)) {
            throw new UnprocessableEntityHttpException(__('messages.appointment.session_id_required'));
        }
        setStripeApiKey();

        $sessionData = \Stripe\Checkout\Session::retrieve($sessionId);
        $appointment = Appointment::whereAppointmentUniqueId($sessionData->client_reference_id)->first();
        $patientId = User::whereEmail($sessionData->customer_details->email)->pluck('id')->first();
        $transaction = [
            'user_id' => $patientId,
            'transaction_id' => $sessionData->id,
            'appointment_id' => $sessionData->client_reference_id,
            'amount' => intval($sessionData->amount_total / 100),
            'type' => Appointment::STRIPE,
            'meta' => $sessionData,
        ];

        Transaction::create($transaction);

        $appointment->update([
            'payment_method' => Appointment::STRIPE,
            'payment_type' => Appointment::PAID,
        ]);

        Flash::success(__('messages.flash.appointment_created_payment_complete'));

        $patient = Patient::whereUserId($patientId)->with('user')->first();
        Notification::create([
            'title' => Notification::APPOINTMENT_PAYMENT_DONE_PATIENT_MSG,
            'type' => Notification::PAYMENT_DONE,
            'user_id' => $patient->user_id,
        ]);

        if (parse_url(url()->previous(), PHP_URL_PATH) == '/medical-appointment') {
            return redirect(route('medicalAppointment'));
        }

        if (! getLogInUser()) {
            return redirect(route('medical'));
        }

        if (getLogInUser()->hasRole('patient')) {
            return redirect(route('patients.patient-appointments-index'));
        }

        return redirect(route('appointments.index'));
    }

    /**
     * @return Application|RedirectResponse|Redirector
     */
    public function handleFailedPayment(): RedirectResponse
    {
        setStripeApiKey();

        Flash::error(__('messages.flash.appointment_created_payment_not_complete'));

        if (! getLogInUser()) {
            return redirect(route('medicalAppointment'));
        }

        if (getLogInUser()->hasRole('patient')) {
            return redirect(route('patients.patient-appointments-index'));
        }

        return redirect(route('appointments.index'));
    }

    /**
     * @return mixed
     *
     * @throws ApiErrorException
     */
    public function appointmentPayment(Request $request)
    {
        $appointmentId = $request['appointmentId'];
        $appointment = Appointment::whereId($appointmentId)->first();

        $result = $this->appointmentRepository->createSession($appointment);

        return $this->sendResponse($result, __('messages.appointment.session_created_successfully'));
    }

    /**
     * @return mixed
     */
    public function changePaymentStatus(Request $request)
    {
        $input = $request->all();
        if (getLogInUser()->hasRole('doctor')) {
            $doctor = Appointment::whereId($input['appointmentId'])->whereDoctorId(getLogInUser()->doctor->id);
            if (! $doctor->exists()) {
                return $this->sendError(__('messages.common.not_allow__assess_record'));
            }
        }

        $appointment = Appointment::with('patient')->findOrFail($input['appointmentId']);
        $transactionExist = Transaction::whereAppointmentId($appointment['appointment_unique_id'])->first();

        $appointment->update([
            'payment_type' => $input['paymentStatus'],
            'payment_method' => $input['paymentMethod'],
        ]);

        if (empty($transactionExist)) {
            $transaction = [
                'user_id' => $appointment->patient->user_id,
                'transaction_id' => Str::random(10),
                'appointment_id' => $appointment->appointment_unique_id,
                'amount' => $appointment->payable_amount,
                'type' => Appointment::MANUALLY,
                'status' => Transaction::SUCCESS,
                'accepted_by' => $input['loginUserId'],
            ];

            Transaction::create($transaction);
        } else {
            $transactionExist->update([
                'status' => Transaction::SUCCESS,
                'accepted_by' => $input['loginUserId'],
            ]);
        }

        $appointmentNotification = Transaction::with('acceptedPaymentUser')->whereAppointmentId($appointment['appointment_unique_id'])->first();

        $fullTime = $appointment->from_time . '' . $appointment->from_time_type . ' - ' . $appointment->to_time . '' . $appointment->to_time_type . ' ' . ' ' . Carbon::parse($appointment->date)->format('jS M, Y');
        $patient = Patient::whereId($appointment->patient_id)->with('user')->first();
        Notification::create([
            'title' => $appointmentNotification->acceptedPaymentUser->full_name . ' changed the payment status ' . Appointment::PAYMENT_TYPE[Appointment::PENDING] . ' to ' . Appointment::PAYMENT_TYPE[$appointment->payment_type] . ' for appointment ' . $fullTime,
            'type' => Notification::PAYMENT_DONE,
            'user_id' => $patient->user_id,
        ]);

        return $this->sendSuccess(__('messages.flash.payment_status_updated'));
    }

    public function cancelAppointment($patient_id, $appointment_unique_id): RedirectResponse
    {
        //not complate  query
        $uniqueId = Crypt::decryptString($appointment_unique_id);
        $appointment = Appointment::whereAppointmentUniqueId($uniqueId)->wherePatientId($patient_id)->first();

        $appointment->update(['status' => Appointment::CANCELLED]);

        return redirect(route('medical'));
    }

    public function doctorBookAppointment(Doctor $doctor): RedirectResponse
    {
        $data = [];
        $data['doctor_id'] = $doctor['id'];

        return redirect()->route('medicalAppointment')->with(['data' => $data]);
    }

    public function serviceBookAppointment(Service $service): RedirectResponse
    {
        $data = [];
        $data['service'] = Service::whereStatus(Service::ACTIVE)->get()->pluck('name', 'id');
        $data['service_id'] = $service['id'];

        return redirect()->route('medicalAppointment')->with(['data' => $data]);
    }

    /**
     * @return bool|JsonResponse
     */
    public function createGoogleEventForDoctor(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            return $this->sendSuccess(__('messages.flash.operation_performed_success'));
        }

        return true;
    }

    /**
     * @return bool|JsonResponse
     */
    public function createGoogleEventForPatient(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            return $this->sendSuccess(__('messages.flash.operation_performed_success'));
        }

        return true;
    }

    public function manuallyPayment(Request $request): RedirectResponse
    {
        $input = $request->all();
        $appointment = Appointment::findOrFail($input['appointmentId'])->load('patient');
        $transaction = [
            'user_id' => $appointment->patient->user_id,
            'transaction_id' => Str::random(10),
            'appointment_id' => $appointment->appointment_unique_id,
            'amount' => $appointment->payable_amount,
            'type' => Appointment::MANUALLY,
            'status' => Transaction::PENDING,
        ];

        Transaction::create($transaction);

        if (parse_url(url()->previous(), PHP_URL_PATH) == '/medical-appointment') {
            return redirect(route('medicalAppointment'));
        }

        if (! getLogInUser()) {
            return redirect(route('medical'));
        }

        if (getLogInUser()->hasRole('patient')) {
            return redirect(route('patients.patient-appointments-index'));
        }

        if (getLogInUser()->hasRole('doctor')) {

            return redirect(route('doctors.appointments'));
        }

        return redirect(route('appointments.index'));
    }

    public function appointmentPdf($id)
    {
        // $datas = Appointment::with(['patient.user', 'doctor.user', 'services'])->findOrFail($id);
        $datas = Appointment::with(['patient.user', 'doctor.user', 'services'])->findOrFail($id);
        $pdf = Pdf::loadView('appointment_pdf.invoice', ['datas' => $datas]);

        return $pdf->download('invoice.pdf');
    }

    /**
     * Public webhook endpoint for JotForm to call after a consent form is signed.
     *
     * JotForm should POST to: POST {APP_URL}/api/consent-webhook
     *
     * Accepted parameters (query string or form fields):
     *   - appointment_id : the appointment's ID
     *   - doctor_id      : the doctor's ID
     *
     * Optional:
     *   - file : a PDF file (multipart upload)
     *   - rawRequest / formData : JotForm's raw submission JSON
     *
     * If no PDF file is attached, a simple placeholder document record is created
     * so the system can track that consent was given.
     */
    public function consentWebhook(Request $request)
    {
        // CP-12 DIAGNOSTIC: Log every webhook entry so we can verify Jotform
        // is actually reaching us. Grep `tail -f storage/logs/laravel.log`
        // for "[CP-12] consentWebhook ENTRY" while submitting a form.
        \Log::info('[CP-12] consentWebhook ENTRY', [
            'method'        => $request->method(),
            'has_session'   => $request->hasSession(),
            'authed'        => \Auth::check(),
            'user_id'       => optional(\Auth::user())->id,
            'query'         => $request->query(),
            'post_keys'     => array_keys($request->all()),
            'referer'       => $request->header('Referer'),
            'user_agent'    => $request->header('User-Agent'),
            'submission_id' => $request->input('submission_id'),
            'form_id'       => $request->input('formID') ?? $request->input('form_id'),
            'ip'            => $request->ip(),
        ]);

        // Try to get appointment_id and doctor_id from multiple sources:
        // 1. Query parameters (preferred when Jotform redirect includes them)
        // 2. POST form fields / hidden fields in Jotform
        // 3. Jotform's rawRequest field (if present)
        // 4. Jotform's formID + search all POST data for embedded fields

        $appointmentId = $request->query('appointment_id')
                      ?? $request->input('appointment_id')
                      ?? null;
        $doctorId = $request->query('doctor_id')
                 ?? $request->input('doctor_id')
                 ?? null;

        // Jotform may send a rawRequest JSON field containing submitted data
        if ((! $appointmentId || ! $doctorId) && $request->has('rawRequest')) {
            $raw = $request->input('rawRequest');
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
            if (is_array($raw)) {
                $appointmentId = $appointmentId ?: ($raw['appointment_id'] ?? null);
                $doctorId = $doctorId ?: ($raw['doctor_id'] ?? null);
            }
        }

        // Jotform POST redirect: scan ALL input fields for appointment_id / doctor_id
        // Jotform names hidden fields as q1_appointmentId, q2_doctorId etc.
        if (! $appointmentId || ! $doctorId) {
            foreach ($request->all() as $key => $value) {
                if (is_string($value)) {
                    if (! $appointmentId && (stripos($key, 'appointment') !== false || $key === 'appointment_id')) {
                        $appointmentId = $value;
                    }
                    if (! $doctorId && (stripos($key, 'doctor') !== false || $key === 'doctor_id')) {
                        $doctorId = $value;
                    }
                }
                // Jotform sends fields as arrays sometimes: q3[field] => value
                if (is_array($value)) {
                    foreach ($value as $subKey => $subVal) {
                        if (is_string($subVal)) {
                            if (! $appointmentId && stripos($subKey, 'appointment') !== false) {
                                $appointmentId = $subVal;
                            }
                            if (! $doctorId && stripos($subKey, 'doctor') !== false) {
                                $doctorId = $subVal;
                            }
                        }
                    }
                }
            }
        }

        // Last resort: try to extract from the HTTP Referer header
        // The Jotform iframe URL contains ?appointment_id=X&doctor_id=Y
        if (! $appointmentId || ! $doctorId) {
            $referer = $request->header('Referer', '');
            if ($referer) {
                $parsed = parse_url($referer);
                if (isset($parsed['query'])) {
                    parse_str($parsed['query'], $refParams);
                    $appointmentId = $appointmentId ?: ($refParams['appointment_id'] ?? null);
                    $doctorId = $doctorId ?: ($refParams['doctor_id'] ?? null);
                }
            }
        }

        $isAjax = $request->ajax() || $request->wantsJson();

        // If still missing parameters, show a friendly page that auto-closes
        // (the JS postMessage listener on the booking page will handle it instead)
        if (! $appointmentId || ! $doctorId) {
            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required parameters: appointment_id and doctor_id.',
                ], 422);
            }
            // Show a success-like page that tells the user to go back to the booking
            // The JS postMessage listener will have already recorded the consent
            return response()->view('errors.consent-success-fallback', [
                'message' => 'Your consent form has been submitted. Please close this window and return to the booking page to continue.',
            ]);
        }

        // Look up the appointment
        $appointment = Appointment::with('patient.user')
            ->where('id', $appointmentId)
            ->where('doctor_id', $doctorId)
            ->first();

        if (! $appointment || ! $appointment->patient) {
            // Try looking up by just appointment_id (doctor_id may not match for packages)
            $appointment = Appointment::with('patient.user')
                ->where('id', $appointmentId)
                ->first();

            if ($appointment) {
                // Use the actual doctor_id from the appointment
                $doctorId = $appointment->doctor_id;
            }
        }

        if (! $appointment || ! $appointment->patient) {
            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment not found.',
                ], 404);
            }
            return response()->view('errors.consent-error', [
                'message' => 'Appointment not found. Please return to the booking page and try again.',
            ], 404);
        }

        // Authorization: only the appointment's patient, the appointment's doctor,
        // or a clinic_admin/staff can record consent. Prevents arbitrary forgery.
        $currentUser = getLogInUser();
        if ($currentUser) {
            $authorized = false;
            if ($currentUser->hasRole('clinic_admin') || $currentUser->hasRole('staff')) {
                $authorized = true;
            } elseif ($currentUser->hasRole('patient') && $currentUser->patient
                      && $currentUser->patient->id === $appointment->patient_id) {
                $authorized = true;
            } elseif ($currentUser->hasRole('doctor') && $currentUser->doctor
                      && $currentUser->doctor->id === $appointment->doctor_id) {
                $authorized = true;
            }

            if (! $authorized) {
                \Log::warning('Consent webhook unauthorized', [
                    'user_id' => $currentUser->id,
                    'appointment_id' => $appointment->id,
                    'doctor_id' => $doctorId,
                ]);
                if ($isAjax) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
                }
                return response()->view('errors.consent-error', ['message' => 'Unauthorized.'], 403);
            }
        }

        $userId = $appointment->patient->user_id;
        $doctor = Doctor::with('user')->find($doctorId);
        $title = 'Consent Form';
        if ($doctor && $doctor->user) {
            $title = 'Consent Form - ' . $doctor->user->full_name;
        }

        // CP-12 Requirement 3: "If the user already filled the consent form for
        // the doctor he is no longer required to sign / fill in the consent
        // form again." Per-doctor lookup (NOT per-appointment) so a second
        // appointment with the same doctor reuses the existing signed PDF
        // and skips the iframe in the wizard.
        // Link the consent to this appointment id as well (so the blade's
        // per-appointment reference still works) but only when the patient
        // hasn't already signed for this doctor.
        $existingConsent = Document::where('user_id', $userId)
            ->where('type', 'consent')
            ->where('doctor_id', $doctorId)
            ->orderByDesc('id')
            ->first();

        // If an older consent exists for this doctor but wasn't bound to
        // this appointment, reuse it — just stamp the current appointment_id
        // so the Documents tab lists it under this booking too.
        if ($existingConsent && (int) $existingConsent->appointment_id !== (int) $appointment->id) {
            $existingConsent->update(['appointment_id' => $appointment->id]);
            \Log::info('CP-12: Reusing existing consent for doctor', [
                'user_id' => $userId,
                'doctor_id' => $doctorId,
                'appointment_id' => $appointment->id,
                'document_id' => $existingConsent->id,
            ]);
        }

        if (! $existingConsent) {
            // CP-30: Jotform's own webhook POST uses camelCase `submissionID`
            // while booking.js sends `submission_id` (snake_case). Previously
            // we only read the snake_case key — so when Jotform POSTed
            // directly (the most reliable path) the id was lost and the
            // PDF fetch downgraded to dompdf. Check both, plus rawRequest.
            $submissionId = (string) (
                $request->input('submission_id')
                ?? $request->input('submissionID')
                ?? $request->query('submissionID')
                ?? $request->query('submission_id')
                ?? ''
            );
            if (! $submissionId) {
                $raw = $request->input('rawRequest');
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        foreach (['submissionID', 'submission_id', 'sid', 'id'] as $k) {
                            if (!empty($decoded[$k])) { $submissionId = (string) $decoded[$k]; break; }
                        }
                    }
                }
            }

            // Handle file upload if a PDF was attached (legacy path, kept for backward-compat)
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $path = $file->store('documents/user_' . $userId, 'public');

                Document::create([
                    'user_id'        => $userId,
                    'uploaded_by'    => $userId,
                    'title'          => $title,
                    'type'           => 'consent',
                    'path'           => $path,
                    'mime_type'      => $file->getClientMimeType(),
                    'size'           => (int) round($file->getSize() / 1024),
                    'doctor_id'      => $doctorId,
                    'appointment_id' => $appointment->id,
                ]);
            } else {
                // AP-02 / CP-12: Save the OFFICIAL Jotform-rendered PDF of the
                // submission into the patient's documents folder.
                //
                // Strategy:
                //   1. Try to download the real PDF from Jotform using the API
                //      (requires JOTFORM_API_KEY in .env). This gives us the
                //      branded/signed PDF as it appears on jotform.com.
                //   2. Fall back to a dompdf-generated summary of the answers
                //      if the API call fails or the API key isn't configured,
                //      so we never silently lose the consent record.
                $consentTitle = $title . ' (signed)';

                // Jotform webhook also posts formID in addition to submission_id
                $formId = $request->input('formID')
                    ?? $request->input('form_id')
                    ?? $request->query('formID')
                    ?? '';

                // Collect submission answers for the fallback PDF
                $submittedAnswers = [];
                $raw = $request->input('rawRequest');
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        // rawRequest sometimes wraps formID under "slug" or similar keys
                        if (empty($formId) && isset($decoded['formID'])) {
                            $formId = $decoded['formID'];
                        }
                        foreach ($decoded as $k => $v) {
                            if (is_string($v) && trim($v) !== '') {
                                $submittedAnswers[$k] = $v;
                            } elseif (is_array($v)) {
                                $flat = implode(', ', array_filter(array_map(fn($x) => is_scalar($x) ? (string)$x : null, $v)));
                                if ($flat !== '') {
                                    $submittedAnswers[$k] = $flat;
                                }
                            }
                        }
                    }
                }

                // CP-31: Derive formId from the doctor's `jotform_link` when
                // the iframe / postMessage path didn't pass it. The doctor
                // record holds e.g. https://form.jotform.com/250000000000
                // — the trailing numeric segment IS the form id.
                if (empty($formId) && $doctor && !empty($doctor->jotform_link)) {
                    if (preg_match('~(?:form\.jotform\.com|jotform\.com/form|form/)/?(\d{6,})~', $doctor->jotform_link, $m)) {
                        $formId = $m[1];
                    } elseif (preg_match('~(\d{6,})~', $doctor->jotform_link, $m)) {
                        $formId = $m[1];
                    }
                    if ($formId) {
                        \Log::info('CP-31: derived formId from doctor.jotform_link', [
                            'doctor_id' => $doctorId,
                            'form_id'   => $formId,
                        ]);
                    }
                }

                // CP-31: If submissionId is still missing AND we have an API
                // key + formId, query Jotform for this form's MOST RECENT
                // submission (within the last 10 minutes — anything older
                // is unrelated to this booking) and use that. This makes
                // the signed-PDF fetch resilient to the iframe-redirect
                // path where submissionID never reaches us.
                $jotformApiKeyEarly = null;
                try { $jotformApiKeyEarly = trim((string) getSettingValue('jotform_api_key')); } catch (\Throwable $ignored) {}
                if (empty($jotformApiKeyEarly)) {
                    $jotformApiKeyEarly = config('services.jotform.api_key');
                }
                if (empty($submissionId) && $jotformApiKeyEarly && $formId) {
                    try {
                        $listUrl = $this->jotformBaseUrl()
                            . '/form/' . urlencode($formId) . '/submissions?limit=1&orderBy=created_at&apiKey=' . urlencode($jotformApiKeyEarly);
                        $resp = \Illuminate\Support\Facades\Http::timeout(20)
                            ->withHeaders(['APIKEY' => $jotformApiKeyEarly])
                            ->get($listUrl);
                        if ($resp->successful()) {
                            $json = $resp->json();
                            $latest = $json['content'][0] ?? null;
                            if ($latest && !empty($latest['id'])) {
                                // CP-36: Jotform's `created_at` is rendered in
                                // the form-owner's account timezone (NOT UTC),
                                // which makes age math via that string
                                // unreliable across server/account TZ combos.
                                // The API also returns a Unix `timestamp`
                                // field — trust that when present.
                                $createdTs = 0;
                                if (!empty($latest['timestamp']) && is_numeric($latest['timestamp'])) {
                                    $createdTs = (int) $latest['timestamp'];
                                } else {
                                    try {
                                        $dt = new \DateTime(($latest['created_at'] ?? 'now'));
                                        $createdTs = $dt->getTimestamp();
                                    } catch (\Throwable $ignored) {
                                        $createdTs = strtotime((string) ($latest['created_at'] ?? 'now'));
                                    }
                                }
                                $ageSec = time() - $createdTs;
                                // 30 min window so patient can finish the
                                // wizard after signing.
                                if ($ageSec < 1800 && $ageSec > -300) {
                                    $submissionId = (string) $latest['id'];
                                    \Log::info('CP-36: pulled latest submission id from Jotform', [
                                        'form_id'       => $formId,
                                        'submission_id' => $submissionId,
                                        'age_seconds'   => $ageSec,
                                    ]);
                                } else {
                                    \Log::warning('CP-36: latest submission outside window', [
                                        'form_id'     => $formId,
                                        'age_seconds' => $ageSec,
                                        'created_at'  => $latest['created_at'] ?? null,
                                        'timestamp'   => $latest['timestamp'] ?? null,
                                    ]);
                                }
                            }
                        } else {
                            \Log::warning('CP-31: Jotform list-submissions non-2xx', [
                                'status' => $resp->status(),
                            ]);
                        }
                    } catch (\Throwable $e) {
                        \Log::warning('CP-31: list-submissions lookup failed', ['error' => $e->getMessage()]);
                    }
                }

                $folder   = 'documents/user_' . $userId;
                $filename = 'consent_dr_' . $doctorId . '_appt_' . $appointment->id . '_' . time() . '.pdf';
                $relPath  = $folder . '/' . $filename;

                $disk = \Illuminate\Support\Facades\Storage::disk('public');
                $disk->makeDirectory($folder);

                $pdfBytes  = null;
                $pdfSource = 'none';

                // ── 1) Try official Jotform PDF via API ────────────────────
                // Prefer the DB setting (Admin → Settings → SMTP) so the
                // admin can rotate the key from the UI. Fall back to .env.
                $apiKey = null;
                try {
                    $apiKey = trim((string) getSettingValue('jotform_api_key'));
                } catch (\Throwable $ignored) {
                    // setting row might not exist yet (migration not run)
                }
                if (empty($apiKey)) {
                    $apiKey = config('services.jotform.api_key');
                }
                $baseUrl = $this->jotformBaseUrl();

                if ($apiKey && $submissionId) {
                    // CP-29: Try the full set of Jotform PDF endpoints. Historically
                    // this code tried only 3 URLs with inconsistent param casing
                    // (`formid` vs `formID`, `submissionid` vs `submissionID`,
                    // `apiKey` vs `apikey`) — Jotform's endpoints are strict about
                    // case on some instances, so the fetch silently fell through
                    // to the dompdf fallback and the admin ended up with a
                    // generated summary PDF instead of the official signed one
                    // the client wanted. Cover every known variant.
                    $attempts = [];

                    // (1) Submission metadata → follow the canonical `pdf_url`.
                    //     Most reliable because Jotform itself returns the
                    //     fully-rendered signed PDF path.
                    $attempts[] = [
                        'url'     => $baseUrl . '/submission/' . urlencode($submissionId) . '?apiKey=' . urlencode($apiKey),
                        'kind'    => 'metadata',
                    ];

                    // (2) /generatePDF — legacy but widely supported.
                    if ($formId) {
                        $attempts[] = [
                            'url'  => $baseUrl . '/generatePDF?formID=' . urlencode($formId)
                                        . '&submissionID=' . urlencode($submissionId)
                                        . '&apikey=' . urlencode($apiKey),
                            'kind' => 'pdf',
                        ];
                        $attempts[] = [
                            'url'  => $baseUrl . '/generatePDF?formid=' . urlencode($formId)
                                        . '&submissionid=' . urlencode($submissionId)
                                        . '&apiKey=' . urlencode($apiKey),
                            'kind' => 'pdf',
                        ];

                        // (3) PDF converter (Smart PDF / Signed PDF endpoint).
                        $attempts[] = [
                            'url'  => $baseUrl . '/pdf-converter/' . urlencode($formId)
                                        . '/fill-pdf?download=1'
                                        . '&submissionID=' . urlencode($submissionId)
                                        . '&apikey=' . urlencode($apiKey),
                            'kind' => 'pdf',
                        ];
                    }

                    // (4) Legacy server.php fallback.
                    $attempts[] = [
                        'url'  => 'https://www.jotform.com/server.php?action=getSubmissionPDF'
                                    . '&sid=' . urlencode($submissionId)
                                    . ($formId ? '&formID=' . urlencode($formId) : '')
                                    . '&apiKey=' . urlencode($apiKey),
                        'kind' => 'pdf',
                    ];

                    foreach ($attempts as $attempt) {
                        $url  = $attempt['url'];
                        $kind = $attempt['kind'];
                        try {
                            $response = \Illuminate\Support\Facades\Http::timeout(30)
                                ->withHeaders(['APIKEY' => $apiKey])
                                ->get($url);

                            if (! $response->successful()) {
                                \Log::debug('CP-29: Jotform attempt non-2xx', [
                                    'url'    => preg_replace('/apikey=[^&]+/i', 'apikey=***', $url),
                                    'status' => $response->status(),
                                ]);
                                continue;
                            }

                            $body = $response->body();

                            if ($kind === 'metadata') {
                                // JSON response — extract pdf_url if present.
                                $json = json_decode($body, true);
                                $pdfUrl = null;
                                if (is_array($json) && isset($json['content'])) {
                                    $c = $json['content'];
                                    // Jotform exposes the PDF under various keys depending
                                    // on instance; try the common ones.
                                    foreach (['pdf_url', 'pdfUrl', 'pdf', 'download_url', 'downloadUrl'] as $key) {
                                        if (!empty($c[$key]) && is_string($c[$key])) {
                                            $pdfUrl = $c[$key];
                                            break;
                                        }
                                    }
                                }
                                if ($pdfUrl) {
                                    $pdfResp = \Illuminate\Support\Facades\Http::timeout(30)
                                        ->withHeaders(['APIKEY' => $apiKey])
                                        ->get($pdfUrl);
                                    if ($pdfResp->successful()) {
                                        $pdfBody = $pdfResp->body();
                                        if (is_string($pdfBody) && strncmp($pdfBody, '%PDF-', 5) === 0) {
                                            $pdfBytes  = $pdfBody;
                                            $pdfSource = 'jotform_api';
                                            \Log::info('CP-29: signed PDF fetched via metadata→pdf_url', [
                                                'submission_id' => $submissionId,
                                                'bytes'         => strlen($pdfBody),
                                            ]);
                                            break;
                                        }
                                    }
                                }
                                continue;
                            }

                            // Direct PDF endpoints — body should be %PDF-...
                            if (is_string($body) && strncmp($body, '%PDF-', 5) === 0) {
                                $pdfBytes  = $body;
                                $pdfSource = 'jotform_api';
                                \Log::info('CP-29: signed PDF fetched directly', [
                                    'endpoint'      => preg_replace('/apikey=[^&]+/i', 'apikey=***', $url),
                                    'submission_id' => $submissionId,
                                    'bytes'         => strlen($body),
                                ]);
                                break;
                            }

                            \Log::debug('CP-29: Jotform attempt returned non-PDF', [
                                'url'         => preg_replace('/apikey=[^&]+/i', 'apikey=***', $url),
                                'body_preview'=> substr((string) $body, 0, 120),
                            ]);
                        } catch (\Throwable $e) {
                            \Log::warning('CP-29: Jotform PDF fetch attempt failed', [
                                'url'            => preg_replace('/apikey=[^&]+/i', 'apikey=***', $url),
                                'error'          => $e->getMessage(),
                                'appointment_id' => $appointment->id,
                            ]);
                        }
                    }

                    if ($pdfSource !== 'jotform_api') {
                        \Log::warning('CP-29: Jotform API did not return a valid PDF — falling back to dompdf', [
                            'appointment_id' => $appointment->id,
                            'submission_id'  => $submissionId,
                            'form_id'        => $formId,
                            'attempts_tried' => count($attempts),
                        ]);
                    }
                }

                // ── 2) Fallback: generate our own summary PDF with dompdf ──
                if ($pdfBytes === null) {
                    try {
                        $pdf = \PDF::loadView('documents.consent_pdf', [
                            'patientName'       => optional($appointment->patient->user)->full_name ?? 'Patient',
                            'patientEmail'      => optional($appointment->patient->user)->email ?? '',
                            'doctorName'        => $doctor && $doctor->user ? $doctor->user->full_name : 'Doctor',
                            'appointmentUid'    => $appointment->appointment_unique_id ?? '',
                            'signedAt'          => now()->format('d M Y, h:i A'),
                            'submissionId'      => $submissionId,
                            'submittedAnswers'  => $submittedAnswers,
                        ]);
                        $pdfBytes  = $pdf->output();
                        $pdfSource = 'dompdf_fallback';
                    } catch (\Throwable $ex) {
                        \Log::error('Consent PDF generation failed', [
                            'error' => $ex->getMessage(),
                            'appointment_id' => $appointment->id,
                        ]);
                    }
                }

                // ── 3) Persist the PDF + document record ───────────────────
                try {
                    if ($pdfBytes !== null) {
                        $disk->put($relPath, $pdfBytes);
                        $sizeKB = (int) round(strlen($pdfBytes) / 1024);
                    } else {
                        // Last-resort: record consent without a file
                        $relPath = '';
                        $sizeKB  = 0;
                    }

                    if ($submissionId) {
                        $consentTitle .= ' [JotForm #' . $submissionId . ']';
                    }
                    if ($pdfSource === 'dompdf_fallback' && $apiKey) {
                        // Mark when we had to fall back so admin can re-sync later
                        $consentTitle .= ' (summary)';
                    }

                    Document::create([
                        'user_id'        => $userId,
                        'uploaded_by'    => $userId,
                        'title'          => $consentTitle,
                        'type'           => 'consent',
                        'path'           => $relPath,
                        'mime_type'      => 'application/pdf',
                        'size'           => $sizeKB,
                        'doctor_id'      => $doctorId,
                        'appointment_id' => $appointment->id,
                    ]);

                    \Log::info('Consent PDF stored', [
                        'appointment_id' => $appointment->id,
                        'submission_id'  => $submissionId,
                        'source'         => $pdfSource,
                        'size_kb'        => $sizeKB,
                    ]);
                } catch (\Throwable $ex) {
                    \Log::error('Failed to store consent document', [
                        'error' => $ex->getMessage(),
                        'appointment_id' => $appointment->id,
                    ]);
                }
            }
        }

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Consent document recorded successfully.',
            ]);
        }

        // Browser redirect from Jotform — show a friendly success page.
        // CP-30: Pass the submission id forward so the fallback view can
        // postMessage it to the parent booking wizard. If a subsequent
        // AJAX record attempt follows, it will carry the id and the
        // backend can then fetch the real Jotform PDF.
        return response()->view('errors.consent-success-fallback', [
            'message'       => 'Consent form signed successfully! You can close this window and continue with your booking.',
            'appointment'   => $appointment,
            'submissionId'  => $submissionId ?? '',
            'doctorId'      => $doctorId,
        ]);
    }

    /**
     * CP-33: Smart consent download — lazy refresh from Jotform.
     *
     * The consent PDF stored in `documents` may be a dompdf_fallback if
     * Jotform's submission ID never reached the backend at booking time
     * (cross-origin postMessage / Thank-You URL flows). Rather than make
     * the admin re-trigger a sign-up, every download attempt:
     *
     *   1. Loads the Document row + linked appointment + doctor + patient
     *   2. Pulls latest submissions from Jotform for the doctor's form
     *   3. Picks the one that matches the patient's email/name
     *   4. Fetches the official signed PDF (CP-29 endpoint chain)
     *   5. Overwrites the stored file and re-streams it
     *
     * On any failure, falls back to whatever file is on disk so the admin
     * still gets *something*.
     */
    public function downloadConsentDocument($id)
    {
        $doc = Document::with(['user', 'doctor.user', 'appointment.patient.user'])->findOrFail($id);

        $disk    = \Illuminate\Support\Facades\Storage::disk('public');
        $path    = $doc->path;
        $isPdf   = $doc->mime_type === 'application/pdf' || str_ends_with(strtolower((string) $path), '.pdf');
        $title   = $doc->title ?: 'document';
        $isConsent = $doc->type === 'consent';

        // Try a Jotform refresh only for consent PDFs that look like our
        // dompdf summary (title contains "(summary)") OR have no jotform
        // submission marker (no "JotForm #" tag in title). Admin can also
        // force a refresh with ?refresh=1.
        $forceRefresh = request()->boolean('refresh');
        $needsRefresh = $isConsent && $isPdf && (
            $forceRefresh
            || str_contains((string) $title, '(summary)')
            || ! str_contains((string) $title, 'JotForm #')
        );

        if ($needsRefresh) {
            try {
                $apiKey = trim((string) getSettingValue('jotform_api_key'));
            } catch (\Throwable $ignored) {
                $apiKey = null;
            }
            if (empty($apiKey)) {
                $apiKey = config('services.jotform.api_key');
            }

            $doctor   = $doc->doctor;
            $jotLink  = $doctor ? trim((string) $doctor->jotform_link) : '';
            $formId   = null;
            if ($jotLink && preg_match('~jotform\.com/(?:form/)?(\d{6,})~i', $jotLink, $m)) {
                $formId = $m[1];
            }

            $patientUser = optional(optional($doc->appointment)->patient)->user
                ?? optional($doc->user);
            $patientEmail = strtolower(trim((string) optional($patientUser)->email));
            $patientName  = strtolower(trim((string) optional($patientUser)->full_name));

            if ($apiKey && $formId) {
                try {
                    $baseUrl = $this->jotformBaseUrl();
                    $listUrl = $baseUrl . '/form/' . urlencode($formId)
                                . '/submissions?limit=100&orderBy=created_at&apiKey=' . urlencode($apiKey);
                    $resp = \Illuminate\Support\Facades\Http::timeout(20)
                        ->withHeaders(['APIKEY' => $apiKey])
                        ->get($listUrl);

                    $matchedId       = null;
                    $latestId        = null;
                    $latestAgeSec    = null;
                    if ($resp->successful()) {
                        $items = $resp->json('content') ?? [];
                        // Items come ordered by created_at desc.
                        foreach ($items as $idx => $sub) {
                            // Capture the most recent submission so we can
                            // use it as a fallback if email/name matching
                            // misses (common in testing where the signer's
                            // identity differs from the booking patient).
                            if ($idx === 0 && !empty($sub['id'])) {
                                $latestId = (string) $sub['id'];
                                // CP-35: Jotform's `created_at` is in the
                                // form-owner's account TZ (not UTC), so we
                                // can't trust it for age math. The API
                                // also returns a Unix `timestamp` field —
                                // use that when present.
                                if (!empty($sub['timestamp']) && is_numeric($sub['timestamp'])) {
                                    $latestAgeSec = time() - (int) $sub['timestamp'];
                                } else {
                                    try {
                                        $dt = new \DateTime(($sub['created_at'] ?? 'now'));
                                        $latestAgeSec = time() - $dt->getTimestamp();
                                    } catch (\Throwable $ignored) {
                                        $latestAgeSec = null;
                                    }
                                }
                            }

                            $answers = $sub['answers'] ?? [];
                            $hayEmail = '';
                            $hayName  = '';
                            foreach ($answers as $ans) {
                                $val = $ans['answer'] ?? null;
                                if (is_array($val)) {
                                    $val = trim(implode(' ', array_filter(array_map('strval', $val))));
                                }
                                $val = strtolower(trim((string) $val));
                                if ($val === '') continue;
                                $type = strtolower((string) ($ans['type'] ?? ''));
                                $name = strtolower((string) ($ans['name'] ?? ''));
                                if ($type === 'control_email' || str_contains($name, 'email')) {
                                    $hayEmail = $val;
                                } elseif ($type === 'control_fullname' || str_contains($name, 'name')) {
                                    $hayName = $val;
                                }
                            }
                            if ($patientEmail && $hayEmail === $patientEmail) {
                                $matchedId = $sub['id'] ?? null;
                                break;
                            }
                            if (!$matchedId && $patientName && $hayName && str_contains($hayName, $patientName)) {
                                $matchedId = $sub['id'] ?? null;
                                // keep looking for a better email match
                            }
                        }

                        // CP-34/35: If email/name matching missed but a
                        // recent submission exists, use it. Real patients
                        // sign right before finishing the booking, so the
                        // most recent submission for the doctor's form is
                        // overwhelmingly the right one. With ?refresh=1
                        // we skip the age check entirely (admin override).
                        if (! $matchedId && $latestId !== null) {
                            $useIt = $forceRefresh
                                || $latestAgeSec === null
                                || $latestAgeSec < 86400; // 24h default
                            if ($useIt) {
                                $matchedId = $latestId;
                                \Log::info('CP-34: no email/name match — using most recent submission', [
                                    'document_id'   => $doc->id,
                                    'submission_id' => $matchedId,
                                    'age_seconds'   => $latestAgeSec,
                                    'force'         => $forceRefresh,
                                ]);
                            }
                        }
                    } else {
                        // CP-41: Make this failure obvious. Most common
                        // cause is API key + form ID belonging to
                        // DIFFERENT Jotform accounts — the API returns
                        // 401/404 and we silently fell back to dompdf,
                        // making it look like the code was broken when
                        // the real problem is account ownership.
                        $body = (string) $resp->body();
                        $hint = '';
                        if ($resp->status() === 401) {
                            $hint = 'INVALID API KEY — key not recognised by Jotform.';
                        } elseif ($resp->status() === 404) {
                            $hint = 'FORM NOT FOUND for this API key — the doctor\'s jotform_link points to a form on a DIFFERENT Jotform account than the one the API key belongs to. Either move the form to the same account, or use that account\'s API key.';
                        } elseif ($resp->status() === 403) {
                            $hint = 'FORBIDDEN — API key lacks Full Access permission, or form belongs to another account.';
                        }
                        \Log::warning('CP-41: Jotform list submissions failed', [
                            'status'      => $resp->status(),
                            'form_id'     => $formId,
                            'hint'        => $hint,
                            'body_excerpt'=> mb_substr($body, 0, 300),
                            'document_id' => $doc->id,
                        ]);
                    }

                    if ($matchedId) {
                        // CP-45: Determine strategy by region.
                        //
                        // - US accounts almost always serve a fully-filled,
                        //   signed PDF via /generatePDF. Trust the hosted
                        //   PDF first; only fall back to answers-render if
                        //   it's missing or suspiciously small.
                        //
                        // - EU / HIPAA accounts persistently return the
                        //   BLANK FORM TEMPLATE (header + logo + title,
                        //   no answers, no signature) from every hosted
                        //   PDF endpoint. Skip the hosted attempt and go
                        //   straight to the answers-render path, which
                        //   reliably embeds signature + every field.
                        $region = null;
                        try {
                            $region = strtolower(trim((string) getSettingValue('jotform_region')));
                        } catch (\Throwable $ignored) {}

                        $pdfBytes = null;
                        $tryHosted = ! in_array($region, ['eu', 'hipaa'], true);

                        if ($tryHosted) {
                            $pdfBytes = $this->fetchJotformSignedPdf($baseUrl, $apiKey, $formId, (string) $matchedId);
                            // Heuristic: hosted filled PDFs typically run
                            // 12KB+; sub-8KB is almost certainly the blank
                            // template, drop it.
                            if ($pdfBytes !== null && strlen($pdfBytes) < 8000) {
                                \Log::info('CP-45: hosted PDF too small — likely template, falling back to answers render', [
                                    'submission_id' => $matchedId,
                                    'bytes'         => strlen($pdfBytes),
                                ]);
                                $pdfBytes = null;
                            }
                        } else {
                            \Log::info('CP-45: region is non-US — skipping hosted PDF, rendering from answers', [
                                'region'        => $region,
                                'submission_id' => $matchedId,
                            ]);
                        }

                        // Render from answers (universal fallback).
                        if ($pdfBytes === null) {
                            $pdfBytes = $this->renderJotformAnswersPdf($baseUrl, $apiKey, $formId, (string) $matchedId);
                            if ($pdfBytes !== null) {
                                \Log::info('CP-45: rendered PDF from Jotform answers', [
                                    'submission_id' => $matchedId,
                                    'bytes'         => strlen($pdfBytes),
                                    'region'        => $region,
                                ]);
                            }
                        }

                        if ($pdfBytes !== null) {
                            // Overwrite stored file with the official signed PDF.
                            $newRel = $path;
                            if (! $newRel) {
                                $folder = 'documents/user_' . $doc->user_id;
                                $disk->makeDirectory($folder);
                                $newRel = $folder . '/consent_dr_' . ($doc->doctor_id ?: 0)
                                        . '_appt_' . ($doc->appointment_id ?: 0)
                                        . '_' . time() . '.pdf';
                            }
                            $disk->put($newRel, $pdfBytes);

                            $newTitle = preg_replace('/\s*\(summary\)\s*/', '', (string) $doc->title);
                            if (! str_contains($newTitle, 'JotForm #')) {
                                $newTitle = trim($newTitle . ' [JotForm #' . $matchedId . ']');
                            }

                            $doc->fill([
                                'path'      => $newRel,
                                'mime_type' => 'application/pdf',
                                'size'      => round(strlen($pdfBytes) / 1024, 2),
                                'title'     => $newTitle,
                            ])->save();

                            $path = $newRel;
                            \Log::info('CP-33: refreshed consent PDF from Jotform', [
                                'document_id'   => $doc->id,
                                'submission_id' => $matchedId,
                                'bytes'         => strlen($pdfBytes),
                            ]);
                        } else {
                            \Log::warning('CP-33: matched submission but PDF fetch failed', [
                                'document_id'   => $doc->id,
                                'submission_id' => $matchedId,
                            ]);
                        }
                    } else {
                        \Log::info('CP-33: no Jotform submission matched patient', [
                            'document_id' => $doc->id,
                            'form_id'     => $formId,
                            'email'       => $patientEmail,
                        ]);
                    }
                } catch (\Throwable $e) {
                    \Log::warning('CP-33: refresh from Jotform threw', [
                        'document_id' => $doc->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        }

        if (! $path || ! $disk->exists($path)) {
            abort(404, 'Document file not found.');
        }

        $filename = basename($path);
        return response()->download($disk->path($path), $filename, [
            'Content-Type'  => $doc->mime_type ?: 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * CP-33: Run the CP-29 endpoint chain for a known submissionId.
     * Returns raw PDF bytes on success, null on failure.
     */
    /**
     * CP-44: Build a filled consent PDF from a Jotform submission's
     * `answers` payload. Used when none of the hosted PDF endpoints
     * return a usable filled-and-signed copy (most commonly on EU /
     * HIPAA accounts where /pdf-converter returns the blank template).
     *
     * Returns raw PDF bytes on success, null on failure.
     */
    private function renderJotformAnswersPdf(string $baseUrl, string $apiKey, ?string $formId, string $submissionId): ?string
    {
        try {
            // 1) Submission metadata (answers + created_at + form_id)
            $url = $baseUrl . '/submission/' . urlencode($submissionId) . '?apiKey=' . urlencode($apiKey);
            $resp = \Illuminate\Support\Facades\Http::timeout(20)
                ->withHeaders(['APIKEY' => $apiKey])
                ->get($url);
            if (! $resp->successful()) {
                \Log::warning('CP-44: submission metadata fetch failed', ['status' => $resp->status()]);
                return null;
            }
            $content = $resp->json('content') ?? [];
            $answers = $content['answers'] ?? [];
            if (empty($answers)) {
                \Log::warning('CP-44: submission has no answers');
                return null;
            }

            // 2) Form metadata (title + description) — best-effort.
            $formTitle = 'Consent Form';
            $formDescription = '';
            $formIdEffective = $formId ?: ($content['form_id'] ?? null);
            if ($formIdEffective) {
                try {
                    $fResp = \Illuminate\Support\Facades\Http::timeout(15)
                        ->withHeaders(['APIKEY' => $apiKey])
                        ->get($baseUrl . '/form/' . urlencode($formIdEffective) . '?apiKey=' . urlencode($apiKey));
                    if ($fResp->successful()) {
                        $f = $fResp->json('content') ?? [];
                        if (! empty($f['title'])) {
                            $formTitle = (string) $f['title'];
                        }
                    }
                } catch (\Throwable $ignored) {
                    // optional
                }
            }

            // 3) Normalise answers into ordered field rows. Skip control
            //    types that don't carry data (headers, dividers, page
            //    breaks, captcha, etc.).
            $skipTypes = [
                'control_head', 'control_text', 'control_pagebreak',
                'control_divider', 'control_collapse', 'control_button',
                'control_captcha', 'control_widget',
            ];
            $rows = [];
            // sort by 'order' if present
            uasort($answers, function ($a, $b) {
                $oa = isset($a['order']) ? (int) $a['order'] : 0;
                $ob = isset($b['order']) ? (int) $b['order'] : 0;
                return $oa <=> $ob;
            });
            foreach ($answers as $ans) {
                $type  = strtolower((string) ($ans['type'] ?? ''));
                if (in_array($type, $skipTypes, true)) {
                    continue;
                }
                $label = trim((string) ($ans['text'] ?? $ans['name'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $val = $ans['answer'] ?? null;

                $sigDataUri = null;
                if ($type === 'control_signature' && is_string($val) && $val !== '') {
                    // Jotform may return either a full data: URI or a
                    // hosted PNG URL (e.g. https://www.jotform.com/...).
                    if (str_starts_with($val, 'data:image')) {
                        $sigDataUri = $val;
                    } elseif (filter_var($val, FILTER_VALIDATE_URL)) {
                        try {
                            $imgResp = \Illuminate\Support\Facades\Http::timeout(15)
                                ->withHeaders(['APIKEY' => $apiKey])
                                ->get($val);
                            if ($imgResp->successful()) {
                                $bin  = $imgResp->body();
                                $mime = $imgResp->header('Content-Type') ?: 'image/png';
                                $sigDataUri = 'data:' . $mime . ';base64,' . base64_encode($bin);
                            }
                        } catch (\Throwable $ignored) {
                            // signature embed best-effort
                        }
                    }
                    $val = ''; // hide raw URL/data string from the value column
                }

                if (is_array($val)) {
                    // Compose name objects {first, last, middle, ...}
                    if (isset($val['first']) || isset($val['last'])) {
                        $val = trim(($val['first'] ?? '') . ' ' . ($val['middle'] ?? '') . ' ' . ($val['last'] ?? ''));
                    } else {
                        $val = trim(implode(', ', array_filter(array_map('strval', $val))));
                    }
                }
                $val = is_scalar($val) ? (string) $val : '';
                if ($val === '' && $sigDataUri === null) {
                    continue;
                }

                $rows[] = [
                    'label'              => $label,
                    'value'              => $val,
                    'signature_data_uri' => $sigDataUri,
                ];
            }

            if (empty($rows)) {
                \Log::warning('CP-44: no rendered rows from answers');
                return null;
            }

            $submittedAt = $content['created_at'] ?? '';
            try {
                if (!empty($content['timestamp']) && is_numeric($content['timestamp'])) {
                    $submittedAt = date('l, F j, Y', (int) $content['timestamp']);
                } elseif ($submittedAt) {
                    $submittedAt = date('l, F j, Y', strtotime($submittedAt));
                }
            } catch (\Throwable $ignored) {
                // keep raw
            }

            $pdf = \PDF::loadView('documents.consent_pdf_jotform', [
                'formTitle'       => $formTitle,
                'formDescription' => $formDescription,
                'submittedAt'     => $submittedAt,
                'submissionId'    => $submissionId,
                'fields'          => $rows,
            ]);
            return $pdf->output();
        } catch (\Throwable $e) {
            \Log::warning('CP-44: render failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * CP-42: Resolve the correct Jotform API host based on the saved
     * `jotform_region` setting. EU and HIPAA accounts live on different
     * hosts than the default US one; mismatched region + key produces
     * silent 401/404 and a fallback PDF. Falls back to .env config or
     * api.jotform.com if the setting isn't present.
     */
    private function jotformBaseUrl(): string
    {
        $region = null;
        try {
            $region = strtolower(trim((string) getSettingValue('jotform_region')));
        } catch (\Throwable $ignored) {
            // setting row may not exist yet
        }

        switch ($region) {
            case 'eu':    return 'https://eu-api.jotform.com';
            case 'hipaa': return 'https://hipaa-api.jotform.com';
            case 'us':    return 'https://api.jotform.com';
        }

        // Fallback: explicit .env override → US default.
        return rtrim(config('services.jotform.base_url', 'https://api.jotform.com'), '/');
    }

    private function fetchJotformSignedPdf(string $baseUrl, string $apiKey, ?string $formId, string $submissionId): ?string
    {
        // CP-37: Jotform's signed-PDF render is asynchronous — right after
        // submit, /generatePDF can return HTML "still generating" pages or
        // 404 for a few seconds. Retry the whole chain twice with a short
        // backoff so the very-first download click after sign-up doesn't
        // permanently lock in a bad PDF.
        for ($try = 1; $try <= 3; $try++) {
            $bytes = $this->fetchJotformSignedPdfOnce($baseUrl, $apiKey, $formId, $submissionId);
            if ($bytes !== null) {
                return $bytes;
            }
            if ($try < 3) {
                usleep(1500000); // 1.5s
            }
        }
        return null;
    }

    private function fetchJotformSignedPdfOnce(string $baseUrl, string $apiKey, ?string $formId, string $submissionId): ?string
    {
        // CP-43: ORDER MATTERS — Jotform's `submission/{id}` metadata
        // endpoint returns a `pdf_url` that, on EU/HIPAA hosts and on
        // some US accounts, points to the BLANK form template (just
        // logo + title + description, no answers, no signature). If we
        // try metadata first and accept that PDF, the admin sees an
        // empty form with no submission data. Try the endpoints that
        // actually fill the answers FIRST, and use metadata pdf_url as
        // a last resort.
        $attempts = [];
        if ($formId) {
            // Smart PDF / signed PDF — most reliable for signature widgets.
            $attempts[] = ['url' => $baseUrl . '/pdf-converter/' . urlencode($formId) . '/fill-pdf?download=1&submissionID=' . urlencode($submissionId) . '&apikey=' . urlencode($apiKey), 'kind' => 'pdf'];
            // generatePDF — classic filled-PDF endpoint, two casings.
            $attempts[] = ['url' => $baseUrl . '/generatePDF?formID=' . urlencode($formId) . '&submissionID=' . urlencode($submissionId) . '&apikey=' . urlencode($apiKey), 'kind' => 'pdf'];
            $attempts[] = ['url' => $baseUrl . '/generatePDF?formid=' . urlencode($formId) . '&submissionid=' . urlencode($submissionId) . '&apiKey=' . urlencode($apiKey), 'kind' => 'pdf'];
        }
        $attempts[] = ['url' => 'https://www.jotform.com/server.php?action=getSubmissionPDF&sid=' . urlencode($submissionId) . ($formId ? '&formID=' . urlencode($formId) : '') . '&apiKey=' . urlencode($apiKey), 'kind' => 'pdf'];
        // Metadata pdf_url — last resort, often template-only.
        $attempts[] = ['url' => $baseUrl . '/submission/' . urlencode($submissionId) . '?apiKey=' . urlencode($apiKey), 'kind' => 'metadata'];

        foreach ($attempts as $a) {
            try {
                $resp = \Illuminate\Support\Facades\Http::timeout(30)
                    ->withHeaders(['APIKEY' => $apiKey])
                    ->get($a['url']);
                if (! $resp->successful()) {
                    \Log::debug('CP-43: attempt non-2xx', [
                        'url'    => preg_replace('/apikey=[^&]+/i', 'apikey=***', $a['url']),
                        'status' => $resp->status(),
                    ]);
                    continue;
                }
                $body = $resp->body();

                if ($a['kind'] === 'metadata') {
                    $json = json_decode($body, true);
                    $pdfUrl = null;
                    if (is_array($json) && isset($json['content'])) {
                        foreach (['pdf_url', 'pdfUrl', 'pdf', 'download_url', 'downloadUrl'] as $k) {
                            if (!empty($json['content'][$k]) && is_string($json['content'][$k])) {
                                $pdfUrl = $json['content'][$k];
                                break;
                            }
                        }
                    }
                    if ($pdfUrl) {
                        $pdfResp = \Illuminate\Support\Facades\Http::timeout(30)
                            ->withHeaders(['APIKEY' => $apiKey])
                            ->get($pdfUrl);
                        if ($pdfResp->successful()) {
                            $pdfBody = $pdfResp->body();
                            if (is_string($pdfBody) && strncmp($pdfBody, '%PDF-', 5) === 0) {
                                \Log::info('CP-43: returning metadata pdf_url body (last-resort)', [
                                    'bytes' => strlen($pdfBody),
                                ]);
                                return $pdfBody;
                            }
                        }
                    }
                    continue;
                }

                if (is_string($body) && strncmp($body, '%PDF-', 5) === 0) {
                    \Log::info('CP-43: signed PDF fetched', [
                        'endpoint' => preg_replace('/apikey=[^&]+/i', 'apikey=***', $a['url']),
                        'bytes'    => strlen($body),
                    ]);
                    return $body;
                }
            } catch (\Throwable $ignored) {
                // try next
            }
        }
        return null;
    }
}

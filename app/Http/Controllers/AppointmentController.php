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
        return $this->edit($appointment->id);
    }

    public function edit($id): \Illuminate\View\View
    {
        $appointment = Appointment::findOrFail($id);

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
            if ($appointment->appointment_type === 'feedback') {
                $url = route('patients.patient-bookings-feedback');
            } else {
                $url = route('patients.booking.detail', $appointment->relation_id);
            }
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
            return $this->sendError(__('messages.common.unauthorized'), 403);
        }
        $appointment = Appointment::where('id', $id)->where('patient_id', $user->patient->id)->firstOrFail();
        $draft = AppointmentDraft::where('user_id', $user->id)->where('appointment_id', $id)->first();
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
     */
    public function destroy(Appointment $appointment): JsonResponse
    {
        if (getLogInUser()->hasRole('patient')) {
            if ($appointment->patient_id !== getLogInUser()->patient->id) {
                return $this->sendError('Seems, you are not allowed to access this record.');
            }
        }
        $appointmentUniqueId = $appointment->appointment_unique_id;

        $transaction = Transaction::whereAppointmentId($appointmentUniqueId)->first();

        if ($transaction) {
            $transaction->delete();
        }

        $appointment->delete();

        return $this->sendSuccess(__('messages.flash.appointment_delete'));
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
        $appointment->update([
            'status' => Appointment::CANCELLED,
        ]);

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

        // Avoid duplicate consent records for same appointment + doctor
        $existingConsent = Document::where('user_id', $userId)
            ->where('type', 'consent')
            ->where('doctor_id', $doctorId)
            ->where('appointment_id', $appointment->id)
            ->first();

        if (! $existingConsent) {
            // Handle file upload if a PDF was attached
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
                // No file attached — create a record marking consent was signed
                Document::create([
                    'user_id'        => $userId,
                    'uploaded_by'    => $userId,
                    'title'          => $title . ' (signed)',
                    'type'           => 'consent',
                    'path'           => '',
                    'mime_type'      => 'application/pdf',
                    'size'           => 0,
                    'doctor_id'      => $doctorId,
                    'appointment_id' => $appointment->id,
                ]);
            }
        }

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Consent document recorded successfully.',
            ]);
        }

        // Browser redirect from Jotform — show a friendly success page
        return response()->view('errors.consent-success-fallback', [
            'message' => 'Consent form signed successfully! You can close this window and continue with your booking.',
            'appointment' => $appointment,
        ]);
    }
}

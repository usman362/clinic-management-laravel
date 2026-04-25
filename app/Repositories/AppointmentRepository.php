<?php

namespace App\Repositories;

use App\Events\CreateGoogleAppointment;
use App\Http\Controllers\GoogleCalendarController;
use App\Mail\AppointmentBookedMail;
use App\Mail\DoctorAppointmentBookMail;
use App\Mail\PatientAppointmentBookMail;
use App\Models\AdminEmail;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Notification;
use App\Models\Package;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Class AppointmentRepository
 *
 * @version August 3, 2021, 10:37 am UTC
 */
class AppointmentRepository extends BaseRepository
{
    /**
     * @var GoogleCalendarController
     */
    public function __construct(GoogleCalendarController $googleCalendarController)
    {
        $this->googleCalendarController = $googleCalendarController;
    }

    /**
     * @var array
     */
    protected $fieldSearchable = [];

    /**
     * Return searchable fields
     */
    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    /**
     * Configure the Model
     **/
    public function model()
    {
        return Appointment::class;
    }

    /**
     * @return mixed
     */
    public function store($input)
    {
        $createdAppointmentIds = [];
        $emailJobs = [];

        try {
            DB::beginTransaction();
            $relation_id = uniqid('appt_');

            // Create the first-class Package record for this booking group.
            // AP-17: When the standalone "Create Feedback Package" wizard
            // submits, `appointment_type=feedback` and `parent_package_id`
            // arrive on the request — both must propagate to the Package
            // row so the new package shows up in the Feedback Packages
            // listing and is correctly linked to its parent assessment.
            Package::create([
                'relation_id'       => $relation_id,
                'patient_id'        => $input['patient_id'],
                'created_by'        => Auth::id(),
                'appointment_type'  => $input['appointment_type'] ?? 'assessment',
                'parent_package_id' => ! empty($input['parent_package_id']) ? (int) $input['parent_package_id'] : null,
                'description'       => $input['description'] ?? null,
                'payable_amount'    => $input['payable_amount'] ?? null,
                'payment_type'      => $input['payment_type'] ?? null,
                'payment_method'    => $input['payment_method'] ?? null,
            ]);

            foreach ($input['appointments'] as $key => $appt) {
                // AP-19: Defensively skip incomplete rows. PHP 8.1+ throws
                // "Undefined array key" on direct subscript, and saving a
                // row with NULL service_id / doctor_id would produce a
                // ghost appointment with broken FKs that breaks every
                // downstream view. Client-side validation already gates
                // submission, but the wizard has many entry points + a
                // legacy clone handler — be safe.
                if (empty($appt['service_id']) || empty($appt['doctor_id'])) {
                    \Log::warning('AP-19: store skipping incomplete appointment row', [
                        'key'        => $key,
                        'service_id' => $appt['service_id'] ?? null,
                        'doctor_id'  => $appt['doctor_id']  ?? null,
                    ]);
                    continue;
                }

                $input['appointment_unique_id'] = strtoupper(Appointment::generateAppointmentUniqueId());
                $fromTime = explode(' ', ($appt['from_time'] ?? ''));
                $toTime = explode(' ', ($appt['to_time'] ?? ''));
                $input['from_time'] = $fromTime[0] ?? '';
                $input['from_time_type'] = $fromTime[1] ?? '';
                $input['to_time'] = $toTime[0] ?? '';
                $input['to_time_type'] = $toTime[1] ?? '';
                $appointment = new Appointment();
                $appointment->doctor_id = $appt['doctor_id'];
                $appointment->patient_id = $input['patient_id'];
                $appointment->date = $appt['date'] ?? '';
                $appointment->description = $input['description'];
                $appointment->status = 5;
                $appointment->relation_id = $relation_id;
                $appointment->service_id = $appt['service_id'];
                // AP-17: Mirror the package's appointment_type onto each
                // appointment row. Without this the column relied on the DB
                // default ('assessment'), so feedback wizard submissions
                // produced feedback Package rows with assessment-typed
                // appointments — breaking every list/filter that scopes by
                // appointment_type.
                $appointment->appointment_type = $input['appointment_type'] ?? 'assessment';
                $appointment->appointment_unique_id = $input['appointment_unique_id'];
                $appointment->from_time = $input['from_time'];
                $appointment->from_time_type = $input['from_time_type'];
                $appointment->to_time = $input['to_time'];
                $appointment->to_time_type = $input['to_time_type'];
                $appointment->save();
                $createdAppointmentIds[] = $appointment->id;

                $patient = Patient::whereId($input['patient_id'])->with('user')->first();
                $input['patient_name'] = $patient->user->full_name;

                $input['booking_link'] = env('APP_URL') . 'patients/appointments/' . $relation_id . '/edit';
                if ($patient->user->email_notification) {
                    // Defer the actual mail send until after commit (collect now, send below)
                    $template = AdminEmail::where('type', 'patient_email')->first();
                    $emailJobs[] = [
                        'email' => $patient->user->email,
                        'data'  => [
                            'booking_link' => env('APP_URL') . 'patients/appointments/' . $relation_id . '/edit',
                            'template'     => $template,
                        ],
                    ];
                }

                $doctor = Doctor::whereId($appt['doctor_id'])->with('user')->first();
                $input['doctor_name'] = $doctor->user->full_name;
                // Doctor mail block was already commented out — kept that way.
            }

            // Single atomic commit for all appointments + the package.
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw new UnprocessableEntityHttpException($e->getMessage());
        }

        // Side effects (dispatch jobs, send mails) AFTER commit so they can't
        // poison the transaction or run with uncommitted IDs.
        foreach ($createdAppointmentIds as $appointmentId) {
            try {
                CreateGoogleAppointment::dispatch(true, $appointmentId);
                CreateGoogleAppointment::dispatch(false, $appointmentId);
            } catch (Exception $exception) {
                Log::error($exception->getMessage());
            }
        }

        foreach ($emailJobs as $job) {
            try {
                Mail::to($job['email'])->send(new PatientAppointmentBookMail($job['data']));
            } catch (Exception $exception) {
                Log::error('Patient booking mail failed: ' . $exception->getMessage());
            }
        }

        return Appointment::where('relation_id', $relation_id)->first();
    }

    public function update($input, $id)
    {
        try {
            DB::beginTransaction();
            // $relation_id = uniqid('appt_');

            // AP-07: When an admin edits a package and REMOVES one or more
            // appointment rows (via the "remove-appointment" trash button in
            // Step 2 Services), the form only submits the rows that remain.
            // The rest must be explicitly cancelled (not silently orphaned).
            // We compare submitted IDs vs. the package's full appointment
            // set BEFORE the update loop runs.
            $submittedApptIds = [];
            foreach ($input['appointments'] ?? [] as $appt) {
                if (!empty($appt['appointment_id'])) {
                    $submittedApptIds[] = (int) $appt['appointment_id'];
                }
            }
            $editingAppointment = Appointment::find($id);
            if ($editingAppointment && $editingAppointment->relation_id && ! getLogInUser()->hasRole('patient')) {
                // AP-15: Mirror the admin's top-level client/notes changes
                // to the Package row as well. The Package table stores
                // patient_id and description independently of the
                // underlying appointment rows, so leaving it stale would
                // make the package detail / listing views show the old
                // client after an edit.
                $pkg = Package::where('relation_id', $editingAppointment->relation_id)->first();
                if ($pkg) {
                    if (array_key_exists('patient_id', $input) && ! empty($input['patient_id'])) {
                        $pkg->patient_id = $input['patient_id'];
                    }
                    if (array_key_exists('description', $input)) {
                        $pkg->description = $input['description'];
                    }
                    $pkg->save();
                }

                $allPackageAppts = Appointment::where('relation_id', $editingAppointment->relation_id)
                    ->whereNotIn('status', [Appointment::CANCELLED, Appointment::CHECK_OUT])
                    ->pluck('id')
                    ->all();
                $removedIds = array_diff($allPackageAppts, $submittedApptIds);
                if (! empty($removedIds)) {
                    $note = 'Cancelled by clinic: this appointment was removed from the package.';
                    // AP-07 / AP-11: `cancel_reason = clinic_removed` marks
                    // this cancellation as NOT rebookable. Views guarding the
                    // rebook icon check this field so the patient can't
                    // rebook a service that's no longer offered in the package.
                    Appointment::whereIn('id', $removedIds)->update([
                        'status'        => Appointment::CANCELLED,
                        'cancel_reason' => 'clinic_removed',
                        'description'   => $note,
                    ]);
                    // Notify patient about each cancelled appointment
                    foreach ($removedIds as $rid) {
                        try {
                            $removedAppt = Appointment::with('patient.user')->find($rid);
                            if ($removedAppt && optional($removedAppt->patient)->user) {
                                Notification::create([
                                    'title'   => 'An appointment has been cancelled by the clinic because it was removed from your booking package.',
                                    'type'    => Notification::CANCELED,
                                    'user_id' => $removedAppt->patient->user->id,
                                ]);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('AP-07: cancel-notification failed', [
                                'appointment_id' => $rid,
                                'error'          => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            foreach ($input['appointments'] as $key => $appt) {
                // AP-12: Admin "+ Add Appointment" rows have no
                // appointment_id (they're net-new). Previously `Arr::...`
                // + `Appointment::find($appt['appointment_id'])` crashed
                // with "Undefined array key 'appointment_id'".
                // - If ID present → update existing row.
                // - If ID missing (new row, admin/doctor flow only) →
                //   create a fresh BOOKING_PENDING appointment linked to
                //   the same package (relation_id + patient_id from the
                //   appointment being edited).
                $apptId = $appt['appointment_id'] ?? null;
                if (! empty($apptId)) {
                    $appointment = Appointment::find($apptId);
                } else {
                    // New appointment added via "+ Add Appointment"
                    if (getLogInUser()->hasRole('patient')) {
                        // Patients can't add new appointments mid-edit.
                        continue;
                    }
                    $parent = $editingAppointment ?? Appointment::find($id);
                    $appointment = new Appointment();
                    $appointment->patient_id            = $parent->patient_id;
                    $appointment->relation_id           = $parent->relation_id;
                    $appointment->appointment_type      = $parent->appointment_type ?? 'assessment';
                    $appointment->appointment_unique_id = strtoupper(Appointment::generateAppointmentUniqueId());
                    $appointment->status                = Appointment::BOOKING_PENDING;
                    $appointment->date                  = '';
                    $appointment->from_time             = '';
                    $appointment->from_time_type        = '';
                    $appointment->to_time               = '';
                    $appointment->to_time_type          = '';
                    $appointment->payable_amount        = 0;
                    $appointment->payment_type          = Appointment::PENDING;
                    $appointment->payment_method        = Appointment::MANUALLY;
                }

                // If the find() returned null (stale/bad ID), skip this row
                // rather than crash on the next property access.
                if (! $appointment) {
                    \Log::warning('AP-12: appointment lookup failed, skipping row', [
                        'submitted_id' => $apptId,
                        'index'        => $key,
                    ]);
                    continue;
                }

                $fromTime = explode(' ', ($appt['from_time'] ?? ''));
                $toTime = explode(' ', ($appt['to_time'] ?? ''));
                $input['from_time'] = $fromTime[0] ?? '';
                $input['from_time_type'] = $fromTime[1] ?? '';
                $input['to_time'] = $toTime[0] ?? '';
                $input['to_time_type'] = $toTime[1] ?? '';
                if (getLogInUser()->hasRole('patient')) {
                    $appointment->date = $appt['date'] ?? '';
                    $appointment->status = 1;
                    // Only overwrite time fields if new values are present; otherwise keep existing DB values
                    if (!empty($input['from_time'])) {
                        $appointment->from_time = $input['from_time'];
                        $appointment->from_time_type = $input['from_time_type'];
                    } else {
                        $input['from_time'] = $appointment->from_time;
                        $input['from_time_type'] = $appointment->from_time_type;
                    }
                    if (!empty($input['to_time'])) {
                        $appointment->to_time = $input['to_time'];
                        $appointment->to_time_type = $input['to_time_type'];
                    } else {
                        $input['to_time'] = $appointment->to_time;
                        $input['to_time_type'] = $appointment->to_time_type;
                    }
                    // Map 'address' form field to 'address1' column
                    if (isset($input['address']) && !isset($input['address1'])) {
                        $input['address1'] = $input['address'];
                    }
                    $addressInputArray = Arr::only(
                        $input,
                        ['address1', 'address2', 'city_id', 'state_id', 'country_id', 'postal_code', 'tax_code', 'school_name', 'school_grade']
                    );

                    // Get the patient's user record from the appointment
                    $patient = Patient::whereId($appointment->patient_id)->with(['user', 'address'])->first();
                    $patientUser = $patient->user;

                    // CP-08 CRITICAL FIX: Address is polymorphic. Patient and User have
                    // SEPARATE morphOne(Address, 'owner') relationships pointing to DIFFERENT
                    // rows (different owner_type). Previously we wrote to User->address but
                    // the blade reads from Patient->address, so tax_code/school_name/school_grade
                    // never persisted to where the form could read them.
                    //
                    // Align with PatientRepository/UserRepository/PatientController which all
                    // write to $patient->address(). Also mirror to user->address for backward-compat
                    // with any legacy reads.
                    if ($patient->address) {
                        $patient->address()->update($addressInputArray);
                    } else {
                        $patient->address()->create($addressInputArray);
                    }
                    // Also mirror to user->address so legacy reads still work
                    if ($patientUser->address) {
                        $patientUser->address()->update($addressInputArray);
                    } else {
                        $patientUser->address()->create($addressInputArray);
                    }

                    // CP-09 fix: Only overwrite child details if the form actually sent
                    // non-empty values. In rebook mode, step 0 is skipped so these fields
                    // arrive as empty strings — we must NOT overwrite with empty.
                    if (!empty($input['first_name'])) {
                        $patientUser->first_name = $input['first_name'];
                    }
                    if (!empty($input['last_name'])) {
                        $patientUser->last_name = $input['last_name'];
                    }
                    if (!empty($input['dob'])) {
                        $patientUser->dob = $input['dob'];
                    }
                    $patientUser->save();
                } else {
                    // AP-07: Admin/Doctor editing a package — only update
                    // which service/doctor are assigned, keep the existing
                    // date/time/status untouched (admin wizard has no slot
                    // picker). Previously the post-save block below tried to
                    // read $appt['date'] which this form never sends,
                    // triggering "Undefined array key 'date'".

                    // AP-15: Client (patient_id) and description are
                    // top-level fields on the wizard — they apply to EVERY
                    // appointment in the package. Prior to this fix the
                    // admin branch never copied them, so changing the
                    // client or notes in the edit form was a silent no-op
                    // (the form submitted, the request succeeded, nothing
                    // persisted). Apply them on every row here.
                    if (array_key_exists('patient_id', $input) && ! empty($input['patient_id'])) {
                        $appointment->patient_id = $input['patient_id'];
                    }
                    if (array_key_exists('description', $input)) {
                        $appointment->description = $input['description'];
                    }

                    // AP-15: Service/doctor are per-row fields. The
                    // feedback-package edit form hides + disables them
                    // because feedback appointments inherit those from
                    // the parent assessment. Previously the repo treated
                    // their absence as "row invalid" and skipped the
                    // entire row — which also dropped the client/notes
                    // changes above. Only skip the service/doctor write
                    // when the values are missing; still save the other
                    // top-level fields that did come in.
                    $doctorId  = $appt['doctor_id']  ?? null;
                    $serviceId = $appt['service_id'] ?? null;
                    if (! empty($doctorId) && ! empty($serviceId)) {
                        $appointment->doctor_id  = $doctorId;
                        $appointment->service_id = $serviceId;
                    } elseif (! empty($doctorId) xor ! empty($serviceId)) {
                        // One present, the other missing — ambiguous,
                        // skip both to avoid orphaning a FK. Still save
                        // the other top-level changes collected above.
                        \Log::warning('AP-15: admin package row has partial service/doctor; keeping existing FKs', [
                            'appointment_id' => $apptId,
                            'doctor_id'      => $doctorId,
                            'service_id'     => $serviceId,
                        ]);
                    }
                    $appointment->save();
                    continue; // skip the patient-only booking-confirmation block
                }
                $appointment->save();
                // $appointment->patient_id = $input['patient_id'];
                // $appointment->charge = $appt['charge'];
                // $appointment->relation_id = $relation_id;
                // $appointment->payable_amount = $input['payable_amount'];
                // $appointment->appointment_unique_id = $input['appointment_unique_id'];
                //
                // AP-07: Everything below is the PATIENT booking-confirmation
                // path — sends doctor email + dispatches Google Calendar
                // events. Runs ONLY when the caller is a patient (else branch
                // above already `continue`d). Reads $appt['date'] safely.
                $input['patient_name'] = $patient->user->full_name;
                $input['original_from_time'] = $input['from_time'] . ' ' . $input['from_time_type'];
                $input['original_to_time'] = $input['to_time'] . ' ' . $input['to_time_type'];
                $service = Service::whereId($appointment->service_id)->first();
                $input['service'] = $service->name;
                $input['date'] = $appt['date'] ?? $appointment->date ?? '';

                // if ($patient->user->email_notification) {
                //     Mail::to($patient->user->email)->send(new PatientAppointmentBookMail($input));
                // }

                if (! empty($input['date'])) {
                    $input['full_time'] = $input['original_from_time'] . '-' . $input['original_to_time'] . ' ' . Carbon::parse($input['date'])->format('jS M, Y');
                } else {
                    $input['full_time'] = $input['original_from_time'] . '-' . $input['original_to_time'];
                }
                // if (! getLogInUser()->hasRole('patient')) {
                //     $patientNotification = Notification::create([
                //         'title' => Notification::APPOINTMENT_CREATE_PATIENT_MSG . ' ' . $input['full_time'],
                //         'type' => Notification::BOOKED,
                //         'user_id' => $patient->user->id,
                //     ]);
                // }

                $doctor = Doctor::whereId($appointment->doctor_id)->with('user')->first();
                $input['doctor_name'] = $doctor->user->full_name;
                if ($doctor->user->email_notification) {
                    Mail::to($doctor->user->email)->send(new DoctorAppointmentBookMail($input));
                }

                // $doctorNotification = Notification::create([
                //     'title' => $patient->user->full_name . ' ' . Notification::APPOINTMENT_CREATE_DOCTOR_MSG . ' ' . $input['full_time'],
                //     'type' => Notification::BOOKED,
                //     'user_id' => $doctor->user->id,
                // ]);
                try {
                    CreateGoogleAppointment::dispatch(true, $appointment->id);
                    CreateGoogleAppointment::dispatch(false, $appointment->id);
                } catch (Exception $exception) {
                    Log::error($exception->getMessage());
                }
            }


            DB::commit();

            $appointment = Appointment::find($id);
            return $appointment;
        } catch (Exception $e) {
            DB::rollBack();
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }

    /**
     * @return mixed
     */
    public function frontSideStore($input)
    {
        try {
            DB::beginTransaction();
            $oldUser = User::whereEmail($input['email'])->first();

            if (isset($input['is_patient_account']) && $input['is_patient_account'] == 1) {
                if (! $oldUser) {
                    throw new UnprocessableEntityHttpException(__('messages.common.email_not_register'));
                }
                $input['patient_id'] = $oldUser->patient->id;
            } else {
                if ($oldUser) {
                    throw new UnprocessableEntityHttpException(__('messages.common.email_already_exist'));
                }
                $input['original_password'] = Str::random(8);
                $input['type'] = User::PATIENT;
                $userFields = ['first_name', 'last_name', 'email', 'password', 'type', 'region_code', 'contact', 'email_verified_at'];
                $input['email_verified_at'] = Carbon::now();
                $input['password'] = Hash::make($input['original_password']);
                /** @var User $user */
                $user = User::create(Arr::only($input, $userFields));
                $patientArray['patient_unique_id'] = strtoupper(Patient::generatePatientUniqueId());

                /** @var Patient $patient */
                $patient = $user->patient()->create($patientArray);
                $user->assignRole('patient');
                $input['patient_id'] = $patient->id;
            }
            $input['appointment_unique_id'] = strtoupper(Appointment::generateAppointmentUniqueId());
            $input['original_from_time'] = $input['from_time'];
            $input['original_to_time'] = $input['to_time'];
            $fromTime = explode(' ', $input['from_time']);
            $toTime = explode(' ', $input['to_time']);
            $input['from_time'] = $fromTime[0] ?? '';
            $input['from_time_type'] = $fromTime[1] ?? '';
            $input['to_time'] = $toTime[0] ?? '';
            $input['to_time_type'] = $toTime[1] ?? '';
            $input['status'] = Appointment::BOOKED;
            $input['payment_type'] = Appointment::MANUALLY;
            $appointment = Appointment::create($input);

            // Mail::to($input['email'])->send(new AppointmentBookedMail($input));
            $patientFullName = (isset($input['is_patient_account']) && $input['is_patient_account'] == 1) ? $oldUser->full_name : $user->full_name;
            $patientId = (isset($input['is_patient_account']) && $input['is_patient_account'] == 1) ? $oldUser->id : $user->id;
            $input['full_time'] = $input['original_from_time'] . '-' . $input['original_to_time'] . ' ' . \Carbon\Carbon::parse($input['date'])->format('jS M, Y');
            if (getLogInUser() && ! getLogInUser()->hasRole('patient')) {
                $patientNotification = Notification::create([
                    'title' => Notification::APPOINTMENT_CREATE_PATIENT_MSG . ' ' . $input['full_time'],
                    'type' => Notification::BOOKED,
                    'user_id' => $patientId,
                ]);
            }

            $doctor = Doctor::whereId($input['doctor_id'])->with('user')->first();
            $input['doctor_name'] = $doctor->user->full_name;
            $input['patient_name'] = $patientFullName;
            $service = Service::whereId($input['service_id'])->first();
            $input['service'] = $service->name;
            // if ($doctor->user->email_notification) {
            //     Mail::to($doctor->user->email)->send(new DoctorAppointmentBookMail($input));
            // }
            $doctorNotification = Notification::create([
                'title' => $patientFullName . ' ' . Notification::APPOINTMENT_CREATE_DOCTOR_MSG . ' ' . $input['full_time'],
                'type' => Notification::BOOKED,
                'user_id' => $doctor->user->id,
            ]);

            DB::commit();

            return $appointment;
        } catch (Exception $e) {
            DB::rollBack();
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }

    public function getData(): array
    {
        $data['doctors'] = Doctor::with('user')->get()->where('user.status', User::ACTIVE)->pluck(
            'user.full_name',
            'id'
        );
        $data['patients'] = Patient::with('user')->get()->pluck('user.full_name', 'id');
        $data['patientStatus'] = Appointment::PATIENT_STATUS;
        $data['services'] = Service::whereStatus(Service::ACTIVE)
            ->select(
                'id',
                DB::raw("CONCAT(name, ' (', duration, ' minutes)') as display_name")
            )
            ->pluck('display_name', 'id');

        return $data;
    }

    public function getDetail($input): array
    {
        $input = Appointment::with(['patient.user', 'patient.address', 'doctor.user', 'services'])->where(
            'id',
            $input->id
        )->first();

        $data['name'] = $input->patient->user->full_name;
        $data['profile'] = $input->patient->profile;
        $data['Id'] = $input->patient->patient_unique_id;
        $data['email'] = $input->patient->user->email;
        $data['address_one'] = $input->patient->address->address1;
        $data['address_two'] = $input->patient->address->address2;
        $data['dob'] = $input->patient->user->dob;
        $data['contact'] = $input->patient->user->contact;
        $data['gender'] = $input->patient->user->gender;
        $data['blood_group'] = $input->patient->user->blood_group;
        $data['from_time'] = $input->from_time;
        $data['to_time'] = $input->to_time;
        $data['description'] = $input->discription;
        $data['doctor'] = $input->doctor->user->full_name;
        $data['service'] = $input->services->name;
        $data['count'] = $input->count();
        $data['date'] = $input->date;

        return $data;
    }

    public function getAppointmentsData(): array
    {
        $doctorId = getLogInUser()->doctor->id;
        /** @var Appointment $appointment */
        $appointments = Appointment::with(['patient.user', 'user', 'user.address', 'services'])
            ->where('appointment_type', '!=', 'feedback')->where('status', '!=', '5')->where('doctor_id', $doctorId)->get();
        $data = [];
        $count = 0;
        $index = 0;
        foreach ($appointments as $appointment) {
            if (empty($appointment->date) || empty($appointment->from_time) || empty($appointment->to_time)) {
                continue;
            }
            $startTime = $appointment->from_time . ' ' . $appointment->from_time_type;
            $endTime = $appointment->to_time . ' ' . $appointment->to_time_type;
            try {
                $start = Carbon::createFromFormat('Y-m-d h:i A', $appointment->date . ' ' . $startTime);
                $end = Carbon::createFromFormat('Y-m-d h:i A', $appointment->date . ' ' . $endTime);
            } catch (\Exception $e) {
                continue;
            }

            // Build location from service address
            $location = optional($appointment->services)->address ?? '';

            // Build instructions
            $instructions = optional($appointment->services)->short_description ?? '';
            if (!empty($appointment->description)) {
                $instructions = $instructions ? $instructions . ' — ' . $appointment->description : $appointment->description;
            }

            $data[$index]['id'] = $appointment->id;
            $data[$index]['title'] = $startTime . '-' . $endTime;
            $data[$index]['patientName'] = optional(optional($appointment->patient)->user)->full_name ?? '';
            $data[$index]['start'] = $start->toDateTimeString();
            $data[$index]['description'] = $appointment->description;
            $data[$index]['status'] = $appointment->status;
            $data[$index]['amount'] = $appointment->payable_amount;
            $data[$index]['uId'] = $appointment->appointment_unique_id ?? '';
            $data[$index]['service'] = optional($appointment->services)->name ?? '';
            $data[$index]['location'] = $location;
            $data[$index]['instructions'] = $instructions;
            $data[$index]['end'] = $end->toDateTimeString();
            $data[$index]['color'] = '#FFF';
            $data[$index]['className'] = [getStatusClassName($appointment->status), 'text-white'];
            $index++;
        }

        return $data;
    }

    public function getFeedbackAppointmentsData(): array
    {
        $doctorId = getLogInUser()->doctor->id;
        /** @var Appointment $appointment */
        $appointments = Appointment::with(['patient.user', 'user', 'user.address', 'services'])
            ->where('appointment_type', 'feedback')->where('status', '!=', '5')->where('doctor_id', $doctorId)->get();
        $data = [];
        $index = 0;
        foreach ($appointments as $appointment) {
            if (empty($appointment->date) || empty($appointment->from_time) || empty($appointment->to_time)) {
                continue;
            }
            $startTime = $appointment->from_time . ' ' . $appointment->from_time_type;
            $endTime = $appointment->to_time . ' ' . $appointment->to_time_type;
            try {
                $start = Carbon::createFromFormat('Y-m-d h:i A', $appointment->date . ' ' . $startTime);
                $end = Carbon::createFromFormat('Y-m-d h:i A', $appointment->date . ' ' . $endTime);
            } catch (\Exception $e) {
                continue;
            }

            // Build location from service address
            $location = optional($appointment->services)->address ?? '';

            // Build instructions
            $instructions = optional($appointment->services)->short_description ?? '';
            if (!empty($appointment->description)) {
                $instructions = $instructions ? $instructions . ' — ' . $appointment->description : $appointment->description;
            }

            $data[$index]['id'] = $appointment->id;
            $data[$index]['title'] = $startTime . '-' . $endTime;
            $data[$index]['patientName'] = optional(optional($appointment->patient)->user)->full_name ?? '';
            $data[$index]['start'] = $start->toDateTimeString();
            $data[$index]['description'] = $appointment->description;
            $data[$index]['status'] = $appointment->status;
            $data[$index]['amount'] = $appointment->payable_amount;
            $data[$index]['uId'] = $appointment->appointment_unique_id ?? '';
            $data[$index]['service'] = optional($appointment->services)->name ?? '';
            $data[$index]['location'] = $location;
            $data[$index]['instructions'] = $instructions;
            $data[$index]['end'] = $end->toDateTimeString();
            $data[$index]['color'] = '#FFF';
            $data[$index]['className'] = [getStatusClassName($appointment->status), 'text-white'];
            $index++;
        }

        return $data;
    }

    public function getPatientAppointmentsCalendar(): array
    {
        $patientId = getLogInUser()->patient->id;
        // CP-18.1: Removed invalid 'user' eager load (Appointment has no user_id column)
        // which could cause Eloquent to silently return partial relationships.
        /** @var Appointment $appointment */
        $appointments = Appointment::with(['doctor.user', 'services'])
            ->where('status', '!=', '5')
            ->where('patient_id', $patientId)
            ->where('appointment_type', 'assessment')
            ->get();
        $data = [];
        $index = 0;
        foreach ($appointments as $appointment) {
            if (empty($appointment->date) || empty($appointment->from_time) || empty($appointment->to_time)) {
                continue;
            }
            $startTime = $appointment->from_time . ' ' . $appointment->from_time_type;
            $endTime = $appointment->to_time . ' ' . $appointment->to_time_type;
            try {
                $start = Carbon::createFromFormat('Y-m-d h:i A', $appointment->date . ' ' . $startTime);
                $end = Carbon::createFromFormat('Y-m-d h:i A', $appointment->date . ' ' . $endTime);
            } catch (\Exception $e) {
                continue;
            }

            // Build location from service address
            $location = optional($appointment->services)->address ?? '';

            // Build instructions from service description + appointment description
            $instructions = optional($appointment->services)->short_description ?? '';
            if (!empty($appointment->description)) {
                $instructions = $instructions ? $instructions . ' — ' . $appointment->description : $appointment->description;
            }

            // CP-18.1: Robust doctor name build with fallbacks (email if name missing).
            $doctorName = '';
            if ($appointment->doctor && $appointment->doctor->user) {
                $u = $appointment->doctor->user;
                $doctorName = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                if ($doctorName === '') {
                    $doctorName = $u->email ?? '';
                }
            }

            $data[$index]['id'] = $appointment->id;
            $data[$index]['title'] = $startTime . '-' . $endTime;
            $data[$index]['doctorName'] = $doctorName;
            $data[$index]['start'] = $start->toDateTimeString();
            $data[$index]['description'] = $appointment->description;
            $data[$index]['status'] = $appointment->status;
            $data[$index]['amount'] = $appointment->payable_amount;
            $data[$index]['uId'] = $appointment->appointment_unique_id ?? '';
            $data[$index]['service'] = optional($appointment->services)->name ?? '';
            $data[$index]['location'] = $location;
            $data[$index]['instructions'] = $instructions;
            $data[$index]['end'] = $end->toDateTimeString();
            $data[$index]['color'] = '#FFF';
            $data[$index]['className'] = [getStatusClassName($appointment->status), 'text-white'];
            $index++;
        }

        return $data;
    }

    public function getPatientFeedbackAppointmentsCalendar(): array
    {
        $patientId = getLogInUser()->patient->id;
        // CP-18.1: Removed invalid 'user' eager load (same fix as getPatientAppointmentsCalendar)
        $appointments = Appointment::with(['doctor.user', 'services'])
            ->where('status', '!=', '5')
            ->where('patient_id', $patientId)
            ->where('appointment_type', 'feedback')
            ->get();
        $data = [];
        $index = 0;
        foreach ($appointments as $appointment) {
            if (empty($appointment->date) || empty($appointment->from_time) || empty($appointment->to_time)) {
                continue;
            }
            $startTime = $appointment->from_time . ' ' . $appointment->from_time_type;
            $endTime = $appointment->to_time . ' ' . $appointment->to_time_type;
            try {
                $start = Carbon::createFromFormat('Y-m-d h:i A', $appointment->date . ' ' . $startTime);
                $end = Carbon::createFromFormat('Y-m-d h:i A', $appointment->date . ' ' . $endTime);
            } catch (\Exception $e) {
                continue;
            }

            // Build location from service address
            $location = optional($appointment->services)->address ?? '';

            // Build instructions from service description + appointment description
            $instructions = optional($appointment->services)->short_description ?? '';
            if (!empty($appointment->description)) {
                $instructions = $instructions ? $instructions . ' — ' . $appointment->description : $appointment->description;
            }

            // CP-18.1: Robust doctor name with fallbacks
            $doctorName = '';
            if ($appointment->doctor && $appointment->doctor->user) {
                $u = $appointment->doctor->user;
                $doctorName = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                if ($doctorName === '') {
                    $doctorName = $u->email ?? '';
                }
            }

            $data[$index]['id'] = $appointment->id;
            $data[$index]['title'] = $startTime . '-' . $endTime;
            $data[$index]['doctorName'] = $doctorName;
            $data[$index]['start'] = $start->toDateTimeString();
            $data[$index]['description'] = $appointment->description;
            $data[$index]['status'] = $appointment->status;
            $data[$index]['amount'] = $appointment->payable_amount;
            $data[$index]['uId'] = $appointment->appointment_unique_id ?? '';
            $data[$index]['service'] = optional($appointment->services)->name ?? '';
            $data[$index]['location'] = $location;
            $data[$index]['instructions'] = $instructions;
            $data[$index]['end'] = $end->toDateTimeString();
            $data[$index]['color'] = '#FFF';
            $data[$index]['className'] = [getStatusClassName($appointment->status), 'text-white'];
            $index++;
        }

        return $data;
    }

    public function getCalendar(): array
    {
        /** @var Appointment $appointment */
        $appointments = Appointment::with(['doctor.user.address', 'user', 'services'])
            ->where('appointment_type', 'assessment')->where('status', '!=', '5')->get();
        $data = [];
        $index = 0;
        foreach ($appointments as $appointment) {
            if (empty($appointment->date) || empty($appointment->from_time) || empty($appointment->to_time)) {
                continue;
            }
            $startTime = $appointment->from_time . ' ' . $appointment->from_time_type;
            $endTime = $appointment->to_time . ' ' . $appointment->to_time_type;
            try {
                $start = Carbon::createFromFormat('Y-m-d h:i A', $appointment->date . ' ' . $startTime);
                $end = Carbon::createFromFormat('Y-m-d h:i A', $appointment->date . ' ' . $endTime);
            } catch (\Exception $e) {
                continue;
            }

            // Build location from service address
            $location = optional($appointment->services)->address ?? '';

            // Build instructions
            $instructions = optional($appointment->services)->short_description ?? '';
            if (!empty($appointment->description)) {
                $instructions = $instructions ? $instructions . ' — ' . $appointment->description : $appointment->description;
            }

            $data[$index]['id'] = $appointment->id;
            $data[$index]['title'] = $startTime . '-' . $endTime;
            $data[$index]['doctorName'] = optional(optional($appointment->doctor)->user)->full_name ?? '';
            $data[$index]['patient'] = optional(optional($appointment->patient)->user)->full_name ?? '';
            $data[$index]['start'] = $start->toDateTimeString();
            $data[$index]['description'] = $appointment->description;
            $data[$index]['status'] = $appointment->status;
            $data[$index]['amount'] = $appointment->payable_amount;
            $data[$index]['uId'] = $appointment->appointment_unique_id ?? '';
            $data[$index]['service'] = optional($appointment->services)->name ?? '';
            $data[$index]['location'] = $location;
            $data[$index]['instructions'] = $instructions;
            $data[$index]['end'] = $end->toDateTimeString();
            $data[$index]['color'] = '#FFF';
            $data[$index]['className'] = [getStatusClassName($appointment->status), 'text-white'];
            $index++;
        }

        return $data;
    }

    public function showAppointment($input): array
    {

        $data['data'] = Appointment::with(['patient.user', 'doctor.user', 'doctor.user.address', 'services'])->findOrFail($input['id']);

        $data['transactionStatus'] = Transaction::whereAppointmentId($data['data']->appointment_unique_id)->exists();

        return $data;
    }

    public function showDoctorAppointment($appointment): array
    {
        $data['data'] = Appointment::with(['patient.user', 'doctor.user', 'doctor.user.address', 'services'])->findOrFail($appointment->id);

        return $data;
    }

    /**
     * @return mixed
     *
     * @throws ApiErrorException
     */
    public function createSession($input)
    {
        $appointmentId = $input['appointment_unique_id'];
        $patientEmail = Patient::with('user')->whereId($input['patient_id'])->first();
        $doctorName = Doctor::with('user')->whereId($input['doctor_id'])->first();
        setStripeApiKey();

        $successUrl = '/medical-payment-success';
        $cancelUrl = '/medical-payment-failed';

        $session = Session::create([
            'payment_method_types' => ['card'],
            'customer_email' => $patientEmail->user->email,
            'line_items' => [
                [
                    'price_data' => [
                        'product_data' => [
                            'name' => 'Payment for appointment booking',
                        ],
                        'unit_amount' => in_array(getCurrencyCode(), zeroDecimalCurrencies()) ? $input['payable_amount'] : $input['payable_amount'] * 100,
                        'currency' => getCurrencyCode(),
                    ],
                    'quantity' => 1,
                    'description' => 'Payment for booking appointment with doctor :
                     ' . $doctorName->user->full_name . ' at ' . Carbon::parse($input->date)->format('d/m/Y') . ' ' . $input->from_time . ' ' . $input->from_time_type . ' to ' . $input->to_time . ' ' . $input->to_time_type,
                ],
            ],
            'client_reference_id' => $appointmentId,
            'mode' => 'payment',
            'success_url' => url($successUrl) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => url($cancelUrl . '?error=payment_cancelled'),
        ]);

        $result = [
            'sessionId' => $session['id'],
        ];

        return $result;
    }
}

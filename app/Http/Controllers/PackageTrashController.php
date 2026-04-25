<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Notification;
use App\Models\Package;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * AP-16: Admin "Deleted Packages" management.
 *
 * - index(): list soft-deleted packages.
 * - restore($id): un-trash the package + all rows that share its
 *   `delete_batch_id` (its appointments, child feedback packages, and
 *   their appointments).
 * - forceDestroy($id): permanent delete via the V8 cascade — wipes
 *   appointments + transactions + consent docs + Google links + the
 *   Package row itself.
 */
class PackageTrashController extends AppBaseController
{
    /** @var AppointmentController */
    private $appointmentController;

    public function __construct(AppointmentController $appointmentController)
    {
        $this->appointmentController = $appointmentController;
    }

    /**
     * Trash listing page (Livewire-backed).
     */
    public function index(): View
    {
        return view('packages.trash.index');
    }

    /**
     * Restore a soft-deleted Package + every row stamped with the same
     * batch id. For appointments this means reversing the cancellation
     * (status rolled back from `pre_cancel_status`, `cancel_reason` and
     * `pre_cancel_status` cleared); for the Package it's a normal
     * SoftDeletes->restore().
     */
    public function restore(int $id): JsonResponse
    {
        $package = Package::onlyTrashed()->findOrFail($id);
        $batchId = $package->delete_batch_id;

        if (! $batchId) {
            // Legacy / defensive path: no batch id. Just un-trash the
            // package; appointments under it stay in whatever state they
            // were left in. Admin can edit individually if needed.
            $package->restore();
            return $this->sendSuccess(__('messages.flash.appointment_restore') ?? 'Package restored.');
        }

        $restoredCount = 0;
        $patientUserId = null;

        DB::transaction(function () use ($batchId, &$restoredCount, &$patientUserId) {
            $appts = Appointment::where('delete_batch_id', $batchId)->get();
            $restoredCount = $appts->count();

            // Capture a patient_id for the notification — every appointment
            // in a single batch belongs to the same patient by construction.
            $aPatientId = $appts->pluck('patient_id')->filter()->first();
            if ($aPatientId) {
                $patientUserId = Patient::where('id', $aPatientId)->value('user_id');
            }

            foreach ($appts as $appt) {
                if ($appt->pre_cancel_status !== null) {
                    // This row was forced to CANCELLED by the trash; roll back.
                    $appt->status            = (int) $appt->pre_cancel_status;
                    $appt->cancel_reason     = null;
                    $appt->pre_cancel_status = null;
                }
                // (else: appointment was ALREADY cancelled at trash time —
                // preserve its prior status + cancel_reason untouched.)
                $appt->delete_batch_id = null;
                $appt->save();
            }

            // Un-trash + unstamp the Package rows.
            $pkgs = Package::onlyTrashed()
                ->where('delete_batch_id', $batchId)
                ->get();
            foreach ($pkgs as $pkg) {
                $pkg->restore();
                $pkg->delete_batch_id = null;
                $pkg->save();
            }
        });

        if ($patientUserId && ! getLogInUser()->hasRole('patient')) {
            try {
                Notification::create([
                    'title'   => 'Your booking package and ' . $restoredCount . ' appointment' . ($restoredCount === 1 ? '' : 's') . ' have been reinstated by the clinic.',
                    'type'    => Notification::CANCELED,
                    'user_id' => $patientUserId,
                ]);
            } catch (\Throwable $e) {
                \Log::warning('AP-16: restore notification failed', [
                    'user_id' => $patientUserId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return $this->sendSuccess(__('messages.flash.appointment_restore') ?? 'Package restored.');
    }

    /**
     * Permanently delete a trashed package — irrecoverable. Reuses the V8
     * hard-cascade in AppointmentController::hardDeleteCascade(). After
     * this completes the package + all its appointments, transactions,
     * consent documents and Google calendar links are physically gone.
     */
    public function forceDestroy(int $id): JsonResponse
    {
        $package = Package::onlyTrashed()->findOrFail($id);
        $relationId = $package->relation_id;

        if (! $relationId) {
            $package->forceDelete();
            return $this->sendSuccess(__('messages.flash.appointment_delete'));
        }

        $this->appointmentController->hardDeleteCascade($relationId);

        return $this->sendSuccess(__('messages.flash.appointment_delete'));
    }
}

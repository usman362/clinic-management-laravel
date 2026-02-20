<?php

namespace App\Http\Controllers;

use App\Mail\PatientAppointmentBookMail;
use Illuminate\Http\Request;
use App\Models\AdminEmail;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Support\Facades\Mail;

class AdminEmailController extends Controller
{
    public function index()
    {
        return view('admin_emails.index');
    }

    public function edit($id)
    {
        $email = AdminEmail::find($id);
        return view('admin_emails.edit', compact('email'));
    }

    public function update(Request $request, $id)
    {
        $email = AdminEmail::find($id);
        $email->subject = $request->subject;
        $email->type = $request->type;
        $email->body = $request->body;
        $email->save();
        return redirect(route('admin.emails.index'));
    }

    public function resendAppointmentEmail(Request $request, $id)
    {
        $appointmet = Appointment::where('relation_id',$id)->first();
        if($appointmet){
            $patient = Patient::whereId($appointmet->patient_id)->with('user')->first();
            if ($patient->user->email_notification) {

                // fetch template from DB
                $template = AdminEmail::where('type', 'patient_email')->first();

                // prepare dynamic data
                $data = [
                    'booking_link' => env('APP_URL') . 'patients/appointments/' . $id . '/edit',
                    'template'     => $template,
                ];

                Mail::to($patient->user->email)
                    ->send(new PatientAppointmentBookMail($data));
            }
        }
        return back()->with('success','Email is Successfully Sent');
    }
}

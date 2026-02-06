<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AdminEmail;

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
}

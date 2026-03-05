<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateUserProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $id = Auth::id();

        return [
            'first_name' => 'required',
            'last_name' => 'required',
            'time_zone' => 'required',
            // Allow "+" and other valid characters in email, consistent with front-facing forms
            'email' => 'required|unique:users,email,'.$id.'|regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix',
            'image' => 'nullable|mimes:jpeg,jpg,png|max:2000',
            // 'contact' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'contact.required' => __('messages.contact_required'),
            'image.max' => __('messages.avatar_size'),
        ];
    }
}

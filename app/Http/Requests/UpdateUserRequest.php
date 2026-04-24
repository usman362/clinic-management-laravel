<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
        // AP-01: Validation rules aligned with the actual doctor edit form
        // (`resources/views/doctors/edit-fields.blade.php`). Fields that were
        // removed from the UI — contact, dob, experience, qualifications,
        // twitter/linkedin/instagram urls — have been dropped here so the
        // validator does not complain about missing optional keys and so
        // the next developer is not misled about what the form collects.
        return [
            'first_name'      => 'required',
            'last_name'       => 'required',
            'email'           => 'required|regex:/^[^@\s]+@[^@\s]+\.[^@\s]+$/i|unique:users,email,'.$this->route('doctor')->user_id,
            'specializations' => 'required',
            'gender'          => 'required',
            'status'          => 'nullable',
            'postal_code'     => 'nullable',
            'profile'         => 'mimes:jpeg,jpg,png|max:2000',
            'jotform_link'    => 'nullable|string',
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'profile.max' => __('messages.profile_size'),
        ];
    }
}

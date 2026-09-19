<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PersonRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (person.create / person.edit) is enforced
     * explicitly in PersonCrudController, matching this app's established convention.
     *
     * @return bool
     */
    public function authorize()
    {
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Identical between store() and update() in the original controller — no
     * unique/id-dependent rule exists on this model's fields.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'entity_type' => 'required|in:individual,legal_entity',
            'salutation' => 'nullable|in:Mr,Mrs,Ms,Dr',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'display_name' => 'nullable|string|max:255',
            'gender' => 'nullable|in:Male,Female,Other,Prefer not to say',
            'dob' => 'nullable|date',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'spouse_name' => 'nullable|string',
            'occupation' => 'nullable|string',
            'aadhaar_no' => 'nullable|string|max:12',
            'pan_no' => 'nullable|string|max:10',
            'tan_no' => 'nullable|string|max:15',
            'gst_no' => 'nullable|string|max:20',
            'extra_data' => 'nullable|json',
        ];
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            //
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            //
        ];
    }
}

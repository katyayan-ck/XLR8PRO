<?php

namespace App\Http\Requests;

use App\Rules\AadhaarNumber;
use App\Rules\Gstin;
use App\Rules\IndianMobileNumber;
use App\Rules\PanNumber;
use App\Rules\TanNumber;
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
     * Minimum viable person is just a name and one primary mobile (POST/create
     * only — mobile is managed via the Contacts card afterwards, not this form,
     * on PUT/update). Everything else is optional and can be filled in later
     * from the integrated person edit screen.
     *
     * @return array
     */
    public function rules()
    {
        $isCreate = $this->isMethod('POST');

        return [
            'display_name' => 'required|string|max:255',
            'mobile' => $isCreate ? ['required', new IndianMobileNumber] : ['sometimes', 'nullable', new IndianMobileNumber],
            'entity_type' => 'nullable|in:individual,legal_entity',
            'salutation' => 'nullable|in:Mr,Mrs,Ms,Dr',
            'first_name' => 'nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'gender' => 'nullable|in:Male,Female,Other,Prefer not to say',
            'dob' => 'nullable|date',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'spouse_name' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',
            'aadhaar_no' => ['nullable', new AadhaarNumber],
            'pan_no' => ['nullable', new PanNumber],
            'tan_no' => ['nullable', new TanNumber],
            'gst_no' => ['nullable', new Gstin],
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
            'display_name' => 'name',
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
            'mobile.required' => 'A primary mobile number is required.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PersonAddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (person.create / person.edit — no
     * dedicated person_address.* permission exists) is enforced explicitly
     * in PersonAddressCrudController.
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
            'person_id' => 'required|exists:xlr8_admin_person,id',
            'type' => 'required|in:residential,official,other',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pincode' => 'nullable|digits:6',
            'country' => 'nullable|string|max:100',
            'is_primary' => 'boolean',
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

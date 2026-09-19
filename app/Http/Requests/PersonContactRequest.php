<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (person.create / person.edit — no
     * dedicated person_contact.* permission exists) is enforced explicitly
     * in PersonContactCrudController.
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
     * Shared between store() and update() — the route's {id} is the
     * contact's numeric id, used to exclude the current row from the
     * per-person/data-type/contact-type uniqueness check on update.
     *
     * @return array
     */
    public function rules()
    {
        $currentId = $this->route('id');

        return [
            'person_code' => 'required|exists:xlr8_admin_person,person_code',

            'data_type' => 'required|in:Mobile,Email,Landline,Fax',

            'contact_type' => [
                'required',
                'in:Primary,Alternate,Office,Home,Emergency',

                Rule::unique('xlr8_admin_person_contacts')
                    ->ignore($currentId)
                    ->where(function ($query) {
                        return $query
                            ->where('person_code', $this->person_code)
                            ->where('data_type', $this->data_type)
                            ->where('contact_type', $this->contact_type)
                            ->whereNull('deleted_at');
                    }),
            ],

            'contact_detail' => 'required|string|max:100',
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
            'contact_type.unique' => 'This contact type already exists for the selected person and data type.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * NOTE: `person_id`, `swift_code`, `is_primary`, and the savings/current/fd/rd/
 * other account_type values here are all preserved verbatim from the original
 * controller's inline validation and don't match the real
 * `xlr8_admin_person_banking_details` schema — see known-bugs-report.md BUG-021.
 */
class PersonBankingDetailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (person.create / person.edit — no
     * dedicated person_banking_detail.* permission exists) is enforced
     * explicitly in PersonBankingDetailCrudController.
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
            'bank_name' => 'required|string|max:255',
            'account_holder_name' => 'required|string|max:255|regex:/^[a-zA-Z\s.]+$/u',
            'account_number' => 'required|numeric|digits_between:8,20',
            'ifsc_code' => 'required|string|size:11|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
            'account_type' => 'required|in:savings,current,fd,rd,other',
            'branch_name' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:50',
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
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

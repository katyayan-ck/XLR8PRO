<?php

namespace App\Http\Requests;

use App\Rules\EmployeeCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (employee.create / employee.edit) is enforced
     * explicitly in EmployeeCrudController, matching this app's established convention.
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
     * Shared between store() and update() — the route's {id} is the employee's
     * numeric id, used to exclude the current row from the unique check on update.
     *
     * @return array
     */
    public function rules()
    {
        $currentId = $this->route('id');

        return [
            'code' => [
                'required',
                'string',
                new EmployeeCode,
                Rule::unique('xlr8_admin_employee', 'code')->ignore($currentId),
            ],
            'person_id' => 'required|exists:xlr8_admin_person,id',
            'designation_id' => 'required|exists:xlr8_admin_designation,id',
            'primary_branch_id' => 'required|exists:xlr8_admin_branch,id',
            'primary_department_id' => 'required|exists:xlr8_admin_department,id',
            'joining_date' => 'required|date',
            'resignation_date' => 'nullable|date|after_or_equal:joining_date',
            'employment_type' => 'required|in:permanent,contract,temporary,probation',
            'is_active' => 'boolean',
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

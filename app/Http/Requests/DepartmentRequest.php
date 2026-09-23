<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (department.create / department.edit) is enforced
     * explicitly in DepartmentCrudController, matching this app's established convention.
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
     * Shared between store() and update() — the route's {id} is the department's
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
                'min:3',
                'max:10',
                Rule::unique('xlr8_admin_department', 'code')->ignore($currentId),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active' => $this->isMethod('PUT') || $this->isMethod('PATCH')
                ? 'nullable|boolean'
                : 'boolean',
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
            'code' => __('org.fields.code'),
            'name' => __('org.fields.name'),
            'description' => __('org.fields.description'),
            'department_image' => __('org.fields.department_image'),
            'is_active' => __('org.fields.is_active'),
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

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModulesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (rbac.manage) is enforced explicitly in
     * ModulesCrudController, matching this app's established convention.
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
     * Shared between store() and update() — the route's {id} is the module's
     * numeric id, used to exclude the current row from the unique check on
     * update.
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
                'max:50',
                Rule::unique('xlr8_iam_module', 'code')->ignore($currentId),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => $this->isMethod('PUT') || $this->isMethod('PATCH')
                ? 'boolean'
                : 'nullable|boolean',
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
            'code' => __('iam.fields.code'),
            'name' => __('iam.fields.name'),
            'description' => __('iam.fields.description'),
            'is_active' => __('iam.fields.is_active'),
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

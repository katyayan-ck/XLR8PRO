<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (rbac.manage) is enforced explicitly in
     * RoleCrudController, matching this app's established convention.
     *
     * No pre-existing dead FormRequest scaffold existed for Role (unlike every
     * other controller in this rollout) — created fresh, following the same
     * shape as the others.
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
     * Shared between store() and update() — the route's {id} is the role's
     * numeric id, used to exclude the current row from the unique check on
     * update.
     *
     * The unique check uses the configured Spatie roles table (the designation
     * table). It used to name `xlr8_iam_roles`, which never existed, so every
     * role save failed (DEC-018).
     *
     * @return array
     */
    public function rules()
    {
        $currentId = $this->route('id');

        return [
            'name' => [
                'required',
                'max:255',
                Rule::unique(config('permission.table_names.roles'), 'name')->ignore($currentId),
            ],
            'guard_name' => 'required|in:web,api',
            'permissions' => 'array',
            'permissions.*' => 'exists:xlr8_iam_permissions,id',
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
            'name' => __('iam.fields.name'),
            'guard_name' => __('iam.fields.guard_name'),
            'permissions' => __('iam.fields.permissions'),
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

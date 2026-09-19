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
     * IMPORTANT: `unique:xlr8_iam_roles,name` is preserved verbatim from the
     * original controller's inline validation, even though `xlr8_iam_roles` is
     * known (from this session's earlier isSuperAdmin() investigation) to not
     * exist as an actual table — Role's real table is `xlr8_admin_designation`.
     * This means role creation/update likely already fails with a DB error
     * today. Not corrected here — see ai-findings for why (consistency with
     * how every other pre-existing bug in this rollout was handled: flagged,
     * not silently fixed, even where the correct fix is already known).
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
                Rule::unique('xlr8_iam_roles', 'name')->ignore($currentId),
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

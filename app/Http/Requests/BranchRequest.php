<?php

namespace App\Http\Requests;

use App\Models\Admin\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (branch.create / branch.edit) is enforced
     * explicitly in BranchCrudController, matching this app's established
     * convention — this stays a plain auth check, not a permission gate.
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
     * Shared between store() and update() — the route's {id} segment (actually
     * the branch code, per BranchCrudController's routing) is used to exclude
     * the current row from the unique check on update.
     *
     * @return array
     */
    public function rules()
    {
        $currentBranchId = null;
        $routeCode = $this->route('id');
        if ($routeCode) {
            $currentBranchId = Branch::where('code', $routeCode)->value('id');
        }

        return [
            'code' => [
                'required',
                'string',
                'min:3',
                'max:10',
                Rule::unique('xlr8_admin_branch', 'code')->ignore($currentBranchId),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'phone' => $this->isMethod('PUT') || $this->isMethod('PATCH')
                ? 'nullable|string'
                : 'nullable|digits:10',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'pincode' => 'nullable|digits:6',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'branch_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_head_office' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
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
            'phone' => __('org.fields.phone'),
            'email' => __('org.fields.email'),
            'address' => __('org.fields.address'),
            'city' => __('org.fields.city'),
            'pincode' => __('org.fields.pincode'),
            'latitude' => __('org.fields.latitude'),
            'longitude' => __('org.fields.longitude'),
            'branch_image' => __('org.fields.branch_image'),
            'is_head_office' => __('org.fields.is_head_office'),
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

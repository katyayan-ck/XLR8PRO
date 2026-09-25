<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DesignationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (designation.create / designation.edit) is enforced
     * explicitly in DesignationCrudController, matching this app's established convention.
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
     * Shared between store() and update() — the route's {id} is the designation's
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
                Rule::unique('xlr8_admin_designation', 'code')->ignore($currentId),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rank' => 'nullable|in:1,2,3,4,5',
            'designation_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_top_mgmt' => 'boolean',
            'parent_desig_code' => 'nullable|string|max:255',
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
            'code' => __('org.fields.code'),
            'name' => __('org.fields.name'),
            'description' => __('org.fields.description'),
            'designation_image' => __('org.fields.designation_image'),
            'is_top_mgmt' => __('org.fields.is_top_mgmt'),
            'parent_desig_code' => __('org.fields.parent_desig_code'),
            'rank' => __('org.fields.rank'),
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

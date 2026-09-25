<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (location.create / location.edit) is enforced
     * explicitly in LocationCrudController, matching this app's established convention.
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
     * Shared between store() and update() — the route's {id} is the location's
     * numeric id, used to exclude the current row from the unique check on update.
     *
     * @return array
     */
    public function rules()
    {
        $currentId = $this->route('id');

        return [
            'branch_code' => 'required|exists:xlr8_admin_branch,code',
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('xlr8_admin_location', 'code')->ignore($currentId),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'nullable|digits:10',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'pincode' => 'nullable|digits:6',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'location_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active' => 'nullable|boolean',
            'is_sales_location' => 'nullable|boolean',
            'is_workshop' => 'nullable|boolean',
            'is_parts_location' => 'nullable|boolean',
            'is_stock_location' => 'nullable|boolean',
            'is_office_only' => 'nullable|boolean',
            'is_mwh' => 'nullable|boolean',
            'is_lmmws' => 'nullable|boolean',
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
            'branch_code' => __('org.fields.branch_code'),
            'phone' => __('org.fields.phone'),
            'email' => __('org.fields.email'),
            'address' => __('org.fields.address'),
            'city' => __('org.fields.city'),
            'pincode' => __('org.fields.pincode'),
            'latitude' => __('org.fields.latitude'),
            'longitude' => __('org.fields.longitude'),
            'location_image' => __('org.fields.location_image'),
            'is_office_only' => __('org.fields.is_office_only'),
            'is_sales_location' => __('org.fields.is_sales_location'),
            'is_stock_location' => __('org.fields.is_stock_location'),
            'is_parts_location' => __('org.fields.is_parts_location'),
            'is_workshop' => __('org.fields.is_workshop'),
            'is_lmmws' => __('org.fields.is_lmmws'),
            'is_mwh' => __('org.fields.is_mwh'),
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

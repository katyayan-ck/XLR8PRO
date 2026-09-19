<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VariantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (variant.create / variant.edit) is enforced
     * explicitly in VariantCrudController, matching this app's established
     * convention.
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
     * Shared between store() and update() — the route's {id} is the variant's
     * numeric id, used to exclude the current row from the unique check on
     * update. Identical field set between the two in the original controller
     * except for the `unique` ignore.
     *
     * @return array
     */
    public function rules()
    {
        $currentId = $this->route('id');

        return [
            'segment_code' => 'required|exists:xlr8_vehicle_segment,code',
            'sub_segment_code' => 'required|exists:xlr8_vehicle_subsegment,code',
            'model_code' => 'required|exists:xlr8_vehicle_model,code',

            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('xlr8_vehicle_variant', 'code')->ignore($currentId),
            ],
            'oem_name' => 'required|string|max:255',
            'custom_name' => 'nullable|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'taxi_price' => 'required|string|max:10',

            'permit_id' => 'nullable|exists:xlr8_utils_keyvalue,id',
            'fuel_type_id' => 'nullable|exists:xlr8_utils_keyvalue,id',
            'body_type_id' => 'nullable|exists:xlr8_utils_keyvalue,id',
            'body_make_id' => 'nullable|exists:xlr8_utils_keyvalue,id',
            'status_id' => 'nullable|exists:xlr8_utils_keyvalue,id',

            'seating_capacity' => $this->isMethod('POST') ? 'nullable|integer|min:1' : 'nullable|integer',
            'wheels' => $this->isMethod('POST') ? 'nullable|integer|min:1' : 'nullable|integer',
            'gvw' => $this->isMethod('POST') ? 'nullable|integer|min:0' : 'nullable|integer',

            'cc_capacity' => 'nullable|string|max:255',
            'transmission' => 'nullable|string|max:255',
            'drivetrain' => 'nullable|string|max:255',

            'is_csd' => 'nullable|boolean',
            'csd_index' => 'nullable|string|max:255',

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

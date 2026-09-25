<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ColorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (color.create / color.edit) is enforced
     * explicitly in ColorCrudController, matching this app's established convention.
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
     * Shared between store() and update() — the route's {id} is the color's
     * numeric id, used to exclude the current row from the unique check on update.
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
            'variant_code' => 'required|exists:xlr8_vehicle_variant,code',
            'code' => [
                'required',
                'max:5',
                Rule::unique('xlr8_vehicle_color', 'code')->ignore($currentId),
            ],
            'name' => 'required|max:255',
            'hex_code' => 'nullable|max:255',
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
            'code' => __('vehicle.fields.code'),
            'name' => __('vehicle.fields.name'),
            'hex_code' => __('vehicle.fields.hex_code'),
            'segment_code' => __('vehicle.fields.segment_code'),
            'sub_segment_code' => __('vehicle.fields.sub_segment_code'),
            'model_code' => __('vehicle.fields.model_code'),
            'variant_code' => __('vehicle.fields.variant_code'),
            'is_active' => __('vehicle.fields.is_active'),
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

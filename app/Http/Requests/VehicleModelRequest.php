<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleModelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (model.create / model.edit) is enforced
     * explicitly in VehicleModelCrudController, matching this app's established
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
     * Shared between store() and update() — the route's {id} is the vehicle
     * model's numeric id, used to exclude the current row from unique checks
     * on update.
     *
     * @return array
     */
    public function rules()
    {
        $currentId = $this->route('id');

        return [
            'segment_code' => 'required|exists:xlr8_vehicle_segment,code',
            'sub_segment_id' => 'nullable|exists:xlr8_vehicle_subsegment,id',
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('xlr8_vehicle_model', 'code')->ignore($currentId),
            ],
            'name' => 'nullable|string|max:255',
            'oem_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('xlr8_vehicle_model', 'oem_name')->ignore($currentId),
            ],
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
            'code' => __('vehicle.fields.code'),
            'name' => __('vehicle.fields.name'),
            'oem_name' => __('vehicle.fields.oem_name'),
            'segment_code' => __('vehicle.fields.segment_code'),
            'sub_segment_id' => __('vehicle.fields.sub_segment_id'),
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

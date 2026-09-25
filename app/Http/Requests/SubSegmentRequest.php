<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubSegmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (segment.edit — no dedicated subsegment.*
     * permission exists) is enforced explicitly in SubSegmentCrudController.
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
     * Only used by update() — SubSegmentCrudController has no store() override.
     *
     * @return array
     */
    public function rules()
    {
        $currentId = $this->route('id');

        return [
            'segment_id' => 'required|exists:xlr8_vehicle_segment,id',
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('xlr8_vehicle_subsegment', 'code')->ignore($currentId),
            ],
            'description' => 'nullable|string',
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
            'description' => __('vehicle.fields.description'),
            'segment_id' => __('vehicle.fields.segment_id'),
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

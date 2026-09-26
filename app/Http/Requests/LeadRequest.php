<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (lead.create / lead.edit) is enforced
     * explicitly in LeadCrudController, matching this app's established
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
     * `status` is only ever set on update (the original controller's store()
     * never accepted it — new leads always start at the model's DB default,
     * 'new') and is required only on PUT/PATCH, preserving that distinction.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'source_code' => 'required|exists:xlr8_crm_lead_sources,code',
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'mobile' => 'required|digits:10',
            'email' => 'nullable|email|max:150',
            'occupation' => 'nullable|string|max:150',
            'segment_code' => 'required|exists:xlr8_vehicle_segment,code',
            'model_code' => 'required|exists:xlr8_vehicle_model,code',
            'variant_code' => 'required|exists:xlr8_vehicle_variant,code',
            'color_code' => 'required|exists:xlr8_vehicle_color,code',
            'expected_delivery_date' => 'nullable|date',
            'priority' => 'required|string',
            'status' => $this->isMethod('PUT') || $this->isMethod('PATCH') ? 'required|string' : 'nullable|string',
            'notes' => 'nullable|string',
            'referral_details' => 'nullable|string|max:255',
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
            'first_name' => __('sales.fields.first_name'),
            'last_name' => __('sales.fields.last_name'),
            'mobile' => __('sales.fields.mobile'),
            'email' => __('sales.fields.email'),
            'occupation' => __('sales.fields.occupation'),
            'source_code' => __('sales.fields.source_code'),
            'segment_code' => __('sales.fields.segment_code'),
            'model_code' => __('sales.fields.model_code'),
            'variant_code' => __('sales.fields.variant_code'),
            'color_code' => __('sales.fields.color_code'),
            'expected_delivery_date' => __('sales.fields.expected_delivery_date'),
            'priority' => __('sales.fields.priority'),
            'status' => __('sales.fields.status'),
            'notes' => __('sales.fields.notes'),
            'referral_details' => __('sales.fields.referral_details'),
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

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (campaign.create / campaign.edit) is
     * enforced explicitly in CampaignCrudController, matching this app's
     * established convention.
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
     * Kept identical to the original controller's inline `$request->validate()`
     * call — plain `required` on the code fields, no `exists` constraints —
     * to avoid introducing new rejection modes the original never had.
     * `forever` is intentionally excluded here — the original controller
     * reads it directly via $request->input('forever', 0) and assigns it as
     * a plain property (bypassing $fillable), preserved exactly in the
     * converted controller.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required',
            'segment_code' => 'required',
            'model_code' => 'required',
            'activity_code' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'branch_code' => 'required',
            'location_code' => 'required',
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
            'name' => __('sales.fields.name'),
            'activity_code' => __('sales.fields.activity_code'),
            'segment_code' => __('sales.fields.segment_code'),
            'model_code' => __('sales.fields.model_code'),
            'branch_code' => __('sales.fields.branch_code'),
            'location_code' => __('sales.fields.location_code'),
            'start_date' => __('sales.fields.start_date'),
            'end_date' => __('sales.fields.end_date'),
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

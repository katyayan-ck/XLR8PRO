<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return backpack_auth()->check();
    }

   
    protected function prepareForValidation()
    {
        $strictFormatFields = ['registrationno', 'refchassisregno', 'pan_no', 'aadhaar_no'];
        $mergeData = [];

        foreach ($strictFormatFields as $field) {
            if ($this->has($field) && !empty($this->$field)) {
                $mergeData[$field] = strtoupper(preg_replace('/\s+/', '', $this->$field));
            }
        }

        if ($this->has('exchange_bonus') && empty($this->exchange_bonus)) {
            $mergeData['exchange_bonus'] = 0;
        }

        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'customer_person_code' => 'required|string|exists:xlr8_admin_person,person_code',
            'branch_code'          => 'required|string|exists:xlr8_admin_branch,code', 
            'variant_code'         => 'required|string|exists:xlr8_vehicle_variant,code',
            'color_code'           => 'required|string|exists:xlr8_vehicle_color,code',
            'booked_by_emp_code'   => 'required|string|exists:xlr8_admin_employee,emp_code',

            'booking_date'           => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:booking_date',
            'b_cat'                  => 'required|string|in:Firm,Individual', 
            'b_mode'                 => 'required|string|in:Online,Offline',

            'registrationno'  => 'nullable|string|max:20',
            'refchassisregno' => 'nullable|string|max:30',
        ];


        if ($this->input('b_cat') === 'Firm') {
            $rules['care_of'] = 'required|in:5'; 
        }

        if ($this->input('b_mode') === 'Online') {
            $rules['online_bk_ref_no'] = 'required|string|max:100';
        }

        if ($this->input('purchase_type') === 'Exchange Buy' || $this->input('purchase_type') === 'Scrappage') {
            $rules['expected_price'] = 'required|numeric|min:0';
            $rules['offered_price']  = 'required|numeric|min:0';
            $rules['exchange_bonus'] = 'nullable|numeric|min:0';
           
        }

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'customer_person_code' => 'Customer',
            'branch_code'          => 'Branch',
            'variant_code'         => 'Vehicle Variant',
            'color_code'           => 'Vehicle Color',
            'b_cat'                => 'Buyer Category',
        ];
    }
}

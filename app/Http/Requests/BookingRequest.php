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
        // Only allow logged-in users to submit bookings
        return backpack_auth()->check();
    }

    /**
     * Prepare the data for validation.
     * This replaces the brittle frontend jQuery logic.
     */
    protected function prepareForValidation()
    {
        // Define fields that must be uppercase with no spaces (e.g., VINs, Reg numbers)
        $strictFormatFields = ['registrationno', 'refchassisregno', 'pan_no', 'aadhaar_no'];
        $mergeData = [];

        foreach ($strictFormatFields as $field) {
            if ($this->has($field) && !empty($this->$field)) {
                // Strip spaces and convert to uppercase natively
                $mergeData[$field] = strtoupper(preg_replace('/\s+/', '', $this->$field));
            }
        }

        // Handle Default Values for Financial Overrides (if left blank by UI)
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
            // ─── 1. Natural Key Foreign Relations (Replaces Integer IDs) ──────────
            'customer_person_code' => 'required|string|exists:xlr8_admin_person,person_code',
            'branch_code'          => 'required|string|exists:xlr8_admin_branch,code', // Verify your branch table name
            'variant_code'         => 'required|string|exists:xlr8_vehicle_variant,code',
            'color_code'           => 'required|string|exists:xlr8_vehicle_color,code',
            'booked_by_emp_code'   => 'required|string|exists:xlr8_admin_employee,emp_code',

            // ─── 2. Core Booking Data ─────────────────────────────────────────────
            'booking_date'           => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:booking_date',
            'b_cat'                  => 'required|string|in:Firm,Individual', // Booking Category
            'b_mode'                 => 'required|string|in:Online,Offline',

            // ─── 3. Strict Formatted Fields (Prepared Above) ──────────────────────
            'registrationno'  => 'nullable|string|max:20',
            'refchassisregno' => 'nullable|string|max:30',
        ];

        // ─── Conditional Business Logic Validation ────────────────────────────────

        // If the booking is "Firm" (Corporate), 'care_of' must be "Owned By"
        if ($this->input('b_cat') === 'Firm') {
            $rules['care_of'] = 'required|in:5'; // Assuming '5' maps to 'Owned By' in your Keyword/Enum system
        }

        // If the booking mode is Online, the reference number is mandatory
        if ($this->input('b_mode') === 'Online') {
            $rules['online_bk_ref_no'] = 'required|string|max:100';
        }

        // Exchange Purchase Logic (Based on your exch-edit.blade.php)
        if ($this->input('purchase_type') === 'Exchange Buy' || $this->input('purchase_type') === 'Scrappage') {
            $rules['expected_price'] = 'required|numeric|min:0';
            $rules['offered_price']  = 'required|numeric|min:0';
            $rules['exchange_bonus'] = 'nullable|numeric|min:0';
            // Note: The "Difference/Price Gap" should NOT be validated here. 
            // It will be calculated purely in the backend PricingService to prevent frontend tampering.
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

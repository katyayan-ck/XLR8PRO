<?php

namespace App\Http\Requests;

use App\Models\Admin\Division;
use App\Models\Admin\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Backs the Person → User (→ Employee) onboarding screen. A user always
 * links to an existing Person (never creates one) — org/vehicle fields are
 * only required when the selected user type is Employee ('emp').
 */
class UserRequest extends FormRequest
{
    public function authorize()
    {
        return backpack_auth()->check();
    }

    public function rules()
    {
        $userId = $this->route('id');
        $isCreate = $this->isMethod('post');
        $isEmployee = strtolower((string) $this->input('user_type_code')) === 'emp';

        $rules = [
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($userId)],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', 'min:8'],
            'user_type_code' => ['required', Rule::exists('xlr8_iam_user_type', 'code')],
            'date_of_joining' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'role_id' => ['nullable', 'exists:xlr8_admin_designation,id'],
            'added_permissions' => ['nullable', 'array'],
            'added_permissions.*' => ['string'],
            'removed_permissions' => ['nullable', 'array'],
            'removed_permissions.*' => ['string'],
        ];

        if ($isCreate) {
            $rules['person_code'] = ['required', 'exists:xlr8_admin_person,person_code'];
        }

        if ($isEmployee) {
            $rules = array_merge($rules, [
                'designation_code' => ['required', 'exists:xlr8_admin_designation,code'],
                'primary_branch_code' => ['required', 'exists:xlr8_admin_branch,code'],
                'addon_branch_codes' => ['nullable', 'array'],
                'addon_branch_codes.*' => ['exists:xlr8_admin_branch,code'],
                'primary_loc_code' => ['required', 'exists:xlr8_admin_location,code'],
                'addon_loc_codes' => ['nullable', 'array'],
                'addon_loc_codes.*' => ['exists:xlr8_admin_location,code'],
                'primary_dept_code' => ['required', 'exists:xlr8_admin_department,code'],
                'addon_dept_codes' => ['nullable', 'array'],
                'addon_dept_codes.*' => ['exists:xlr8_admin_department,code'],
                'primary_div_code' => ['required', 'exists:xlr8_admin_division,code'],
                'addon_div_codes' => ['nullable', 'array'],
                'addon_div_codes.*' => ['exists:xlr8_admin_division,code'],
                'vertical_code' => ['nullable', 'exists:xlr8_admin_vertical,code'],
                'primary_segment_code' => ['nullable', 'exists:xlr8_vehicle_segment,code'],
                'addon_segment_codes' => ['nullable', 'array'],
                'addon_segment_codes.*' => ['exists:xlr8_vehicle_segment,code'],
                'primary_sub_segment_code' => ['nullable', 'exists:xlr8_vehicle_subsegment,code'],
                'addon_sub_segment_codes' => ['nullable', 'array'],
                'addon_sub_segment_codes.*' => ['exists:xlr8_vehicle_subsegment,code'],
            ]);
        }

        if (! $isCreate) {
            $rules = array_merge($rules, [
                'change_reason' => [
                    'nullable',
                    Rule::in(['transfer', 'promotion', 'demotion', 'additional_charge', 'scope_change', 'designation_change', 'permission_change', 'other']),
                ],
                'effective_date' => ['nullable', 'date'],
                'remarks' => ['nullable', 'string', 'max:1000'],
            ]);
        }

        return $rules;
    }

    /** primary_loc_code must be a child of primary_branch_code; primary_div_code must be a child of primary_dept_code. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (strtolower((string) $this->input('user_type_code')) !== 'emp') {
                return;
            }

            $branchCode = $this->input('primary_branch_code');
            $locCode = $this->input('primary_loc_code');
            if ($branchCode && $locCode && ! Location::where('code', $locCode)->where('branch_code', $branchCode)->exists()) {
                $validator->errors()->add('primary_loc_code', 'The primary location must belong to the primary branch.');
            }

            $deptCode = $this->input('primary_dept_code');
            $divCode = $this->input('primary_div_code');
            if ($deptCode && $divCode && ! Division::where('code', $divCode)->where('dept_code', $deptCode)->exists()) {
                $validator->errors()->add('primary_div_code', 'The primary division must belong to the primary department.');
            }
        });
    }

    public function attributes()
    {
        return [
            'role_id' => __('org.fields.role_id'),
            'person_code' => __('org.fields.person_code'),
            'user_type_code' => __('org.fields.user_type_code'),
            'designation_code' => __('org.fields.designation_code'),
            'primary_branch_code' => __('org.fields.primary_branch_code'),
            'primary_loc_code' => __('org.fields.primary_loc_code'),
            'primary_dept_code' => __('org.fields.primary_dept_code'),
            'primary_div_code' => __('org.fields.primary_div_code'),
            'primary_segment_code' => __('org.fields.primary_segment_code'),
            'primary_sub_segment_code' => __('org.fields.primary_sub_segment_code'),
            'vertical_code' => __('org.fields.vertical_code'),
            'username' => __('org.fields.username'),
            'password' => __('org.fields.password'),
            'is_active' => __('org.fields.is_active'),
            'date_of_joining' => __('org.fields.date_of_joining'),
            'effective_date' => __('org.fields.effective_date'),
            'change_reason' => __('org.fields.change_reason'),
            'remarks' => __('org.fields.remarks'),
            'added_permissions' => __('org.fields.added_permissions'),
            'removed_permissions' => __('org.fields.removed_permissions'),
            'addon_branch_codes' => __('org.fields.addon_branch_codes'),
            'addon_dept_codes' => __('org.fields.addon_dept_codes'),
            'addon_div_codes' => __('org.fields.addon_div_codes'),
            'addon_loc_codes' => __('org.fields.addon_loc_codes'),
            'addon_segment_codes' => __('org.fields.addon_segment_codes'),
            'addon_sub_segment_codes' => __('org.fields.addon_sub_segment_codes'),
        ];
    }

    public function messages()
    {
        return [
            'person_code.required' => 'Please search for and select a person.',
            'designation_code.required' => 'Please select a designation for this employee.',
        ];
    }
}

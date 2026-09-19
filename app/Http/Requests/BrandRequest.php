<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (brand.edit) is enforced explicitly in
     * BrandCrudController, matching this app's established convention.
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
     * Only used by update() — BrandCrudController has no store() override, so
     * create/store falls through to Backpack's own default handling.
     *
     * @return array
     */
    public function rules()
    {
        $currentId = $this->route('id');

        return [
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'size:5',
                Rule::unique('xlr8_vehicle_brand', 'code')->ignore($currentId),
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

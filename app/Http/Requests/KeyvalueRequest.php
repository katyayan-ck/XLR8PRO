<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KeyvalueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (settings.manage — no dedicated
     * keyvalue.* permission exists) is enforced explicitly in
     * KeyValueCrudController.
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
     * Shared between store() and update() — the route's {id} is the
     * key-value's numeric id, used to exclude the current row from the
     * unique check on update.
     *
     * @return array
     */
    public function rules()
    {
        $currentId = $this->route('id');

        return [
            'keyword_code' => [
                'required',
                'exists:xlr8_utils_keyword_master,code',
            ],

            'code' => [
                'required',
                'string',
                'max:150',
                Rule::unique('xlr8_utils_keyvalue', 'code')->ignore($currentId),
            ],

            'key' => [
                'nullable',
                'string',
                'max:255',
            ],

            'value' => [
                'required',
                'string',
            ],

            'details' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'parent_id' => [
                'nullable',
                'integer',
            ],

            'level' => [
                'required',
                'integer',
                'min:0',
            ],

            'path' => [
                'nullable',
                'string',
            ],

            'extra_data' => [
                'nullable',
                'array',
            ],

            'status' => [
                'required',
                'integer',
                'in:0,1',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
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

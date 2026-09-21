<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KeywordMasterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Permission-level authorization (settings.manage — no dedicated
     * keyword_master.* permission exists) is enforced explicitly in
     * KeywordMasterCrudController.
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
     * keyword's numeric id, used to exclude the current row from the
     * unique checks on update.
     *
     * @return array
     */
    public function rules()
    {
        $currentId = $this->route('id');

        return [
            'code' => [
                'required',
                'string',
                'min:3',
                'max:50',
                Rule::unique('xlr8_utils_keyword_master', 'code')->ignore($currentId),
            ],

            'keyword' => [
                'required',
                'string',
                'max:255',
                Rule::unique('xlr8_utils_keyword_master', 'keyword')->ignore($currentId),
            ],

            'description' => 'nullable|string|max:1000',

            'details' => 'nullable|string|max:5000',

            'extra_data' => 'nullable|array',

            'status' => [
                'required',
                'integer',
                'in:0,1',
            ],

            'is_recursive' => 'nullable|boolean',

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

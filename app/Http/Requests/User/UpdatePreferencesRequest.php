<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'timezone' => ['sometimes', 'required', 'string', 'max:50'],
            'language' => ['sometimes', 'required', 'string', 'size:2'],
            'preferences' => ['nullable', 'array'],
            'preferences.theme' => ['nullable', 'string', 'in:light,dark,auto'],
            'preferences.date_format' => ['nullable', 'string', 'in:DD/MM/YYYY,MM/DD/YYYY,YYYY-MM-DD'],
            'preferences.number_format' => ['nullable', 'string', 'in:1,000.00,1.000,00,1 000.00'],
            'preferences.start_of_week' => ['nullable', 'integer', 'between:0,6'],
            'preferences.default_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'preferences.default_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'preferences.budget_period' => ['nullable', 'string', 'in:weekly,monthly,quarterly,yearly'],
            'preferences.show_account_balance' => ['nullable', 'boolean'],
            'preferences.show_category_icons' => ['nullable', 'boolean'],
            'preferences.enable_sound' => ['nullable', 'boolean'],
            'preferences.auto_backup' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currency.required' => 'The currency field is required.',
            'currency.size' => 'The currency must be exactly 3 characters.',
            'timezone.required' => 'The timezone field is required.',
            'timezone.max' => 'The timezone must not be greater than 50 characters.',
            'language.required' => 'The language field is required.',
            'language.size' => 'The language must be exactly 2 characters.',
            'preferences.theme.in' => 'The theme must be one of: light, dark, auto.',
            'preferences.date_format.in' => 'The date format must be one of: DD/MM/YYYY, MM/DD/YYYY, YYYY-MM-DD.',
            'preferences.number_format.in' => 'The number format must be one of: 1,000.00, 1.000,00, 1 000.00.',
            'preferences.start_of_week.between' => 'The start of week must be between 0 and 6.',
            'preferences.default_account_id.exists' => 'The selected default account does not exist.',
            'preferences.default_category_id.exists' => 'The selected default category does not exist.',
            'preferences.budget_period.in' => 'The budget period must be one of: weekly, monthly, quarterly, yearly.',
        ];
    }
}

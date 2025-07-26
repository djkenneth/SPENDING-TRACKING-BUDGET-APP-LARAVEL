<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class ImportTransactionRequest extends FormRequest
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
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'], // 5MB max
            'column_mappings' => ['required', 'array'],
            'column_mappings.date' => ['required', 'string'],
            'column_mappings.description' => ['required', 'string'],
            'column_mappings.amount' => ['required', 'string'],
            'column_mappings.type' => ['nullable', 'string'],
            'column_mappings.category' => ['nullable', 'string'],
            'column_mappings.account' => ['nullable', 'string'],
            'column_mappings.notes' => ['nullable', 'string'],
            'column_mappings.reference_number' => ['nullable', 'string'],
            'import_options' => ['nullable', 'array'],
            'import_options.skip_duplicates' => ['nullable', 'boolean'],
            'import_options.default_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'import_options.default_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'import_options.default_type' => ['nullable', 'string', 'in:income,expense'],
            'import_options.date_format' => ['nullable', 'string', 'in:Y-m-d,m/d/Y,d/m/Y,Y/m/d'],
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
            'csv_file.required' => 'CSV file is required.',
            'csv_file.file' => 'The uploaded file must be a valid file.',
            'csv_file.mimes' => 'The file must be a CSV file.',
            'csv_file.max' => 'The file size must not exceed 5MB.',
            'column_mappings.required' => 'Column mappings are required.',
            'column_mappings.date.required' => 'Date column mapping is required.',
            'column_mappings.description.required' => 'Description column mapping is required.',
            'column_mappings.amount.required' => 'Amount column mapping is required.',
            'import_options.default_account_id.exists' => 'The default account does not exist.',
            'import_options.default_category_id.exists' => 'The default category does not exist.',
            'import_options.default_type.in' => 'Default type must be income or expense.',
            'import_options.date_format.in' => 'Date format must be one of: Y-m-d, m/d/Y, d/m/Y, Y/m/d.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = auth()->user();

            // Validate default account belongs to user
            if ($this->has('import_options.default_account_id')) {
                $accountId = $this->input('import_options.default_account_id');
                if ($accountId && !$user->accounts()->where('id', $accountId)->exists()) {
                    $validator->errors()->add(
                        'import_options.default_account_id',
                        'The default account does not belong to you.'
                    );
                }
            }

            // Validate default category belongs to user
            if ($this->has('import_options.default_category_id')) {
                $categoryId = $this->input('import_options.default_category_id');
                if ($categoryId && !$user->categories()->where('id', $categoryId)->exists()) {
                    $validator->errors()->add(
                        'import_options.default_category_id',
                        'The default category does not belong to you.'
                    );
                }
            }
        });
    }
}

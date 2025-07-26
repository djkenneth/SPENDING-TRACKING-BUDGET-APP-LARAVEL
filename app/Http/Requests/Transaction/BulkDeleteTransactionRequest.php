<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteTransactionRequest extends FormRequest
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
            'transaction_ids' => ['required', 'array', 'min:1', 'max:100'],
            'transaction_ids.*' => ['required', 'integer', 'exists:transactions,id'],
            'confirm_deletion' => ['required', 'boolean', 'accepted'],
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
            'transaction_ids.required' => 'Transaction IDs are required.',
            'transaction_ids.min' => 'At least one transaction ID is required.',
            'transaction_ids.max' => 'Maximum 100 transactions can be deleted at once.',
            'transaction_ids.*.required' => 'Each transaction ID is required.',
            'transaction_ids.*.integer' => 'Each transaction ID must be a valid number.',
            'transaction_ids.*.exists' => 'One or more transactions do not exist.',
            'confirm_deletion.required' => 'Deletion confirmation is required.',
            'confirm_deletion.accepted' => 'You must confirm the deletion.',
        ];
    }
}

<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class BulkCreateTransactionRequest extends FormRequest
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
            'transactions' => ['required', 'array', 'min:1', 'max:100'],
            'transactions.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'transactions.*.category_id' => ['required', 'integer', 'exists:categories,id'],
            'transactions.*.transfer_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'transactions.*.description' => ['required', 'string', 'max:255'],
            'transactions.*.amount' => ['required', 'numeric', 'min:0.01'],
            'transactions.*.type' => ['required', 'string', 'in:income,expense,transfer'],
            'transactions.*.date' => ['required', 'date', 'before_or_equal:today'],
            'transactions.*.notes' => ['nullable', 'string', 'max:1000'],
            'transactions.*.tags' => ['nullable', 'array'],
            'transactions.*.tags.*' => ['string', 'max:50'],
            'transactions.*.reference_number' => ['nullable', 'string', 'max:50'],
            'transactions.*.location' => ['nullable', 'string', 'max:255'],
            'transactions.*.is_cleared' => ['nullable', 'boolean'],
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
            'transactions.required' => 'At least one transaction is required.',
            'transactions.min' => 'At least one transaction is required.',
            'transactions.max' => 'Maximum 100 transactions allowed per batch.',
            'transactions.*.account_id.required' => 'Account is required for each transaction.',
            'transactions.*.account_id.exists' => 'One or more selected accounts do not exist.',
            'transactions.*.category_id.required' => 'Category is required for each transaction.',
            'transactions.*.category_id.exists' => 'One or more selected categories do not exist.',
            'transactions.*.description.required' => 'Description is required for each transaction.',
            'transactions.*.amount.required' => 'Amount is required for each transaction.',
            'transactions.*.amount.min' => 'Amount must be at least 0.01 for each transaction.',
            'transactions.*.type.required' => 'Transaction type is required for each transaction.',
            'transactions.*.type.in' => 'Transaction type must be income, expense, or transfer.',
            'transactions.*.date.required' => 'Date is required for each transaction.',
            'transactions.*.date.before_or_equal' => 'Transaction dates cannot be in the future.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = auth()->user();
            $transactions = $this->input('transactions', []);

            foreach ($transactions as $index => $transactionData) {
                // Validate account ownership
                if (isset($transactionData['account_id'])) {
                    $account = $user->accounts()->find($transactionData['account_id']);
                    if (!$account) {
                        $validator->errors()->add(
                            "transactions.{$index}.account_id",
                            'Account does not belong to you.'
                        );
                    }
                }

                // Validate category ownership
                if (isset($transactionData['category_id'])) {
                    $category = $user->categories()->find($transactionData['category_id']);
                    if (!$category) {
                        $validator->errors()->add(
                            "transactions.{$index}.category_id",
                            'Category does not belong to you.'
                        );
                    }
                }

                // Validate transfer account
                if (isset($transactionData['transfer_account_id'])) {
                    $transferAccount = $user->accounts()->find($transactionData['transfer_account_id']);
                    if (!$transferAccount) {
                        $validator->errors()->add(
                            "transactions.{$index}.transfer_account_id",
                            'Transfer account does not belong to you.'
                        );
                    }

                    if ($transactionData['transfer_account_id'] === $transactionData['account_id']) {
                        $validator->errors()->add(
                            "transactions.{$index}.transfer_account_id",
                            'Transfer account must be different from the main account.'
                        );
                    }
                }

                // Validate transfer requirements
                if (isset($transactionData['type']) && $transactionData['type'] === 'transfer') {
                    if (!isset($transactionData['transfer_account_id'])) {
                        $validator->errors()->add(
                            "transactions.{$index}.transfer_account_id",
                            'Transfer account is required for transfer transactions.'
                        );
                    }
                }
            }
        });
    }
}




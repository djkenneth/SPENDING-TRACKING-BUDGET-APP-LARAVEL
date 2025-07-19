<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
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
            'from_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'to_account_id' => ['required', 'integer', 'exists:accounts,id', 'different:from_account_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reference_number' => ['nullable', 'string', 'max:50'],
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
            'from_account_id.required' => 'The source account is required.',
            'from_account_id.exists' => 'The selected source account does not exist.',
            'to_account_id.required' => 'The destination account is required.',
            'to_account_id.exists' => 'The selected destination account does not exist.',
            'to_account_id.different' => 'The destination account must be different from the source account.',
            'amount.required' => 'The transfer amount is required.',
            'amount.numeric' => 'The transfer amount must be a valid number.',
            'amount.min' => 'The transfer amount must be at least 0.01.',
            'description.required' => 'The transfer description is required.',
            'description.max' => 'The description must not exceed 255 characters.',
            'date.required' => 'The transfer date is required.',
            'date.date' => 'The transfer date must be a valid date.',
            'date.before_or_equal' => 'The transfer date cannot be in the future.',
            'notes.max' => 'The notes must not exceed 1000 characters.',
            'reference_number.max' => 'The reference number must not exceed 50 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = auth()->user();

            // Validate that both accounts belong to the user
            $fromAccount = $user->accounts()->find($this->input('from_account_id'));
            $toAccount = $user->accounts()->find($this->input('to_account_id'));

            if (!$fromAccount) {
                $validator->errors()->add('from_account_id', 'The source account does not belong to you.');
            }

            if (!$toAccount) {
                $validator->errors()->add('to_account_id', 'The destination account does not belong to you.');
            }

            // Validate sufficient balance (except for credit cards)
            if ($fromAccount && $fromAccount->type !== 'credit_card') {
                $transferAmount = (float) $this->input('amount');
                if ($fromAccount->balance < $transferAmount) {
                    $validator->errors()->add(
                        'amount',
                        'Insufficient balance in source account. Available: ' .
                        $user->getCurrencySymbol() . number_format($fromAccount->balance, 2)
                    );
                }
            }

            // Validate credit limit for credit card destination
            if ($toAccount && $toAccount->type === 'credit_card') {
                $transferAmount = (float) $this->input('amount');
                $availableCredit = $toAccount->credit_limit - abs($toAccount->balance);

                if ($transferAmount > $availableCredit) {
                    $validator->errors()->add(
                        'amount',
                        'Transfer amount exceeds available credit in destination account. Available: ' .
                        $user->getCurrencySymbol() . number_format($availableCredit, 2)
                    );
                }
            }

            // Validate that accounts are active
            if ($fromAccount && !$fromAccount->is_active) {
                $validator->errors()->add('from_account_id', 'The source account is not active.');
            }

            if ($toAccount && !$toAccount->is_active) {
                $validator->errors()->add('to_account_id', 'The destination account is not active.');
            }
        });
    }
}

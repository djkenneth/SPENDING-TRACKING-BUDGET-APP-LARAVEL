<?php

namespace App\Http\Requests\Debt;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'notes' => ['nullable', 'string', 'max:500'],
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
            'amount.required' => 'The payment amount is required.',
            'amount.numeric' => 'The payment amount must be a number.',
            'amount.min' => 'The payment amount must be at least 0.01.',
            'payment_date.required' => 'The payment date is required.',
            'payment_date.date' => 'The payment date must be a valid date.',
            'payment_date.before_or_equal' => 'The payment date cannot be in the future.',
            'transaction_id.exists' => 'The selected transaction does not exist.',
            'notes.max' => 'The notes must not exceed 500 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $debt = $this->route('debt');
            $amount = (float) $this->input('amount', 0);

            // Validate payment doesn't exceed current balance
            if ($amount > $debt->current_balance) {
                $validator->errors()->add(
                    'amount',
                    sprintf(
                        'The payment amount cannot exceed the current balance of %s.',
                        number_format($debt->current_balance, 2)
                    )
                );
            }

            // Validate transaction belongs to user if provided
            if ($this->has('transaction_id')) {
                $user = $this->user();
                $transaction = $user->transactions()->find($this->input('transaction_id'));

                if (!$transaction) {
                    $validator->errors()->add('transaction_id', 'The selected transaction does not belong to you.');
                } elseif ($transaction->type !== 'expense') {
                    $validator->errors()->add('transaction_id', 'The selected transaction must be an expense type.');
                } elseif ($transaction->amount != $amount) {
                    $validator->errors()->add(
                        'transaction_id',
                        'The transaction amount must match the payment amount.'
                    );
                }
            }

            // Check if debt is already paid off
            if ($debt->status === 'paid_off') {
                $validator->errors()->add('amount', 'Cannot record payment for a debt that is already paid off.');
            }
        });
    }
}

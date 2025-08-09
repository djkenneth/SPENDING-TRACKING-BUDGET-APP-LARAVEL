<?php

namespace App\Http\Requests\Debt;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDebtRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:credit_card,personal_loan,mortgage,auto_loan,student_loan'],
            'original_balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'current_balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_payment' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'due_date' => ['nullable', 'date'],
            'payment_frequency' => ['nullable', 'string', 'in:monthly,weekly,bi-weekly'],
            'status' => ['nullable', 'string', 'in:active,paid_off,closed'],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'name.max' => 'The debt name must not exceed 255 characters.',
            'type.in' => 'The selected debt type is invalid.',
            'original_balance.numeric' => 'The original balance must be a number.',
            'original_balance.min' => 'The original balance must be at least 0.',
            'current_balance.numeric' => 'The current balance must be a number.',
            'current_balance.min' => 'The current balance must be at least 0.',
            'interest_rate.numeric' => 'The interest rate must be a number.',
            'interest_rate.min' => 'The interest rate must be at least 0.',
            'interest_rate.max' => 'The interest rate must not exceed 100.',
            'minimum_payment.numeric' => 'The minimum payment must be a number.',
            'due_date.date' => 'The due date must be a valid date.',
            'payment_frequency.in' => 'The selected payment frequency is invalid.',
            'status.in' => 'The selected status is invalid.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $debt = $this->route('debt');

            // Get the values (use existing if not provided)
            $originalBalance = $this->input('original_balance', $debt->original_balance);
            $currentBalance = $this->input('current_balance', $debt->current_balance);

            // Validate that current balance doesn't exceed original balance
            if ($currentBalance > $originalBalance) {
                $validator->errors()->add('current_balance', 'The current balance cannot exceed the original balance.');
            }

            // Validate minimum payment if current balance > 0
            if ($currentBalance > 0 && $this->has('minimum_payment')) {
                $minimumPayment = (float) $this->input('minimum_payment');
                $interestRate = $this->input('interest_rate', $debt->interest_rate);

                // Calculate monthly interest
                $monthlyInterest = ($currentBalance * ($interestRate / 100)) / 12;

                if ($minimumPayment <= $monthlyInterest && $minimumPayment > 0) {
                    $validator->errors()->add(
                        'minimum_payment',
                        'The minimum payment should be greater than the monthly interest to reduce the principal.'
                    );
                }
            }

            // Prevent changing status to active if balance is 0
            if ($this->input('status') === 'active' && $currentBalance == 0) {
                $validator->errors()->add('status', 'Cannot set status to active when balance is 0.');
            }

            // Automatically suggest paid_off status if balance is 0
            if ($currentBalance == 0 && $this->input('status') === 'active') {
                $validator->errors()->add('status', 'Consider marking this debt as paid_off since the balance is 0.');
            }
        });
    }
}

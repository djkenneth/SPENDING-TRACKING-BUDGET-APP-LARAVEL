<?php

namespace App\Http\Requests\Debt;

use Illuminate\Foundation\Http\FormRequest;

class CreateDebtRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:credit_card,personal_loan,mortgage,auto_loan,student_loan'],
            'original_balance' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'current_balance' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'minimum_payment' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'payment_frequency' => ['required', 'string', 'in:monthly,weekly,bi-weekly'],
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
            'name.required' => 'The debt name is required.',
            'name.max' => 'The debt name must not exceed 255 characters.',
            'type.required' => 'The debt type is required.',
            'type.in' => 'The selected debt type is invalid.',
            'original_balance.required' => 'The original balance is required.',
            'original_balance.numeric' => 'The original balance must be a number.',
            'original_balance.min' => 'The original balance must be at least 0.',
            'current_balance.required' => 'The current balance is required.',
            'current_balance.numeric' => 'The current balance must be a number.',
            'current_balance.min' => 'The current balance must be at least 0.',
            'interest_rate.required' => 'The interest rate is required.',
            'interest_rate.numeric' => 'The interest rate must be a number.',
            'interest_rate.min' => 'The interest rate must be at least 0.',
            'interest_rate.max' => 'The interest rate must not exceed 100.',
            'minimum_payment.required' => 'The minimum payment is required.',
            'minimum_payment.numeric' => 'The minimum payment must be a number.',
            'due_date.required' => 'The due date is required.',
            'due_date.date' => 'The due date must be a valid date.',
            'due_date.after_or_equal' => 'The due date must be today or a future date.',
            'payment_frequency.required' => 'The payment frequency is required.',
            'payment_frequency.in' => 'The selected payment frequency is invalid.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that current balance doesn't exceed original balance
            $originalBalance = (float) $this->input('original_balance', 0);
            $currentBalance = (float) $this->input('current_balance', 0);

            if ($currentBalance > $originalBalance) {
                $validator->errors()->add('current_balance', 'The current balance cannot exceed the original balance.');
            }

            // Validate minimum payment is reasonable
            if ($currentBalance > 0 && $this->has('minimum_payment')) {
                $minimumPayment = (float) $this->input('minimum_payment');
                $interestRate = (float) $this->input('interest_rate', 0);

                // Calculate monthly interest
                $monthlyInterest = ($currentBalance * ($interestRate / 100)) / 12;

                if ($minimumPayment <= $monthlyInterest && $minimumPayment > 0) {
                    $validator->errors()->add(
                        'minimum_payment',
                        'The minimum payment should be greater than the monthly interest to reduce the principal.'
                    );
                }
            }
        });
    }
}

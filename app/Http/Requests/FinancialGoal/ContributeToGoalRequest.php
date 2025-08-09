<?php

namespace App\Http\Requests\FinancialGoal;

use Illuminate\Foundation\Http\FormRequest;

class ContributeToGoalRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'The contribution amount is required.',
            'amount.numeric' => 'The contribution amount must be a valid number.',
            'amount.min' => 'The contribution amount must be at least 0.01.',
            'amount.max' => 'The contribution amount is too large.',
            'date.date' => 'The date must be a valid date.',
            'date.before_or_equal' => 'The contribution date cannot be in the future.',
            'transaction_id.exists' => 'The linked transaction does not exist.',
            'notes.max' => 'The notes must not exceed 500 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $goal = $this->route('goal');

            // Check if contribution would exceed target
            if ($this->has('amount')) {
                $newTotal = $goal->current_amount + $this->input('amount');
                if ($newTotal > $goal->target_amount * 1.5) { // Allow up to 150% of target
                    $validator->errors()->add(
                        'amount',
                        'This contribution would exceed 150% of the target amount. Please adjust the amount or update the goal target.'
                    );
                }
            }

            // Validate transaction belongs to the same user if provided
            if ($this->has('transaction_id')) {
                $user = auth()->user();
                $transactionExists = $user->transactions()
                    ->where('id', $this->input('transaction_id'))
                    ->exists();

                if (!$transactionExists) {
                    $validator->errors()->add(
                        'transaction_id',
                        'The selected transaction does not belong to your account.'
                    );
                }
            }
        });
    }
}

<?php

namespace App\Http\Requests\Bill;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class MarkAsPaidRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('bill')->user_id === Auth::id();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'next_due_date' => ['nullable', 'date', 'after:payment_date'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'payment_date.required' => 'Payment date is required.',
            'payment_date.date' => 'Please provide a valid payment date.',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future.',
            'amount.numeric' => 'Payment amount must be a number.',
            'amount.min' => 'Payment amount must be at least 0.01.',
            'transaction_id.exists' => 'The selected transaction does not exist.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
            'next_due_date.date' => 'Please provide a valid next due date.',
            'next_due_date.after' => 'Next due date must be after the payment date.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate transaction belongs to user if provided
            if ($this->has('transaction_id')) {
                $user = $this->user();
                $transaction = $user->transactions()->find($this->input('transaction_id'));

                if (!$transaction) {
                    $validator->errors()->add('transaction_id', 'The selected transaction does not belong to you.');
                } elseif ($transaction->type !== 'expense') {
                    $validator->errors()->add('transaction_id', 'The selected transaction must be an expense type.');
                }
            }
        });
    }
}

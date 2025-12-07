<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateTransactionRequest extends FormRequest
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
            'account_id' => ['sometimes', 'required', 'integer', 'exists:accounts,id'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'transfer_account_id' => ['nullable', 'integer', 'exists:accounts,id', 'different:account_id'],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'type' => ['sometimes', 'required', 'string', 'in:income,expense,transfer'],
            'date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'reference_number' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:2048'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurring_type' => ['nullable', 'string', 'in:weekly,monthly,quarterly,yearly'],
            'recurring_interval' => ['nullable', 'integer', 'min:1', 'max:12'],
            'recurring_end_date' => ['nullable', 'date', 'after:date'],
            'is_cleared' => ['sometimes', 'boolean'],
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
            'account_id.required' => 'The account field is required.',
            'account_id.exists' => 'The selected account does not exist.',
            'category_id.required' => 'The category field is required.',
            'category_id.exists' => 'The selected category does not exist.',
            'transfer_account_id.exists' => 'The selected transfer account does not exist.',
            'transfer_account_id.different' => 'The transfer account must be different from the main account.',
            'description.required' => 'The description field is required.',
            'description.max' => 'The description must not exceed 255 characters.',
            'amount.required' => 'The amount field is required.',
            'amount.numeric' => 'The amount must be a valid number.',
            'amount.min' => 'The amount must be at least 0.01.',
            'type.required' => 'The transaction type is required.',
            'type.in' => 'The transaction type must be one of: income, expense, transfer.',
            'date.required' => 'The transaction date is required.',
            'date.date' => 'The transaction date must be a valid date.',
            'date.before_or_equal' => 'The transaction date cannot be in the future.',
            'notes.max' => 'The notes must not exceed 1000 characters.',
            'tags.*.max' => 'Each tag must not exceed 50 characters.',
            'reference_number.max' => 'The reference number must not exceed 50 characters.',
            'location.max' => 'The location must not exceed 255 characters.',
            'attachments.*.file' => 'Each attachment must be a valid file.',
            'attachments.*.mimes' => 'Each attachment must be a file of type: jpg, jpeg, png, pdf, doc, docx.',
            'attachments.*.max' => 'Each attachment must not exceed 2MB.',
            'recurring_type.in' => 'The recurring type must be one of: weekly, monthly, quarterly, yearly.',
            'recurring_interval.min' => 'The recurring interval must be at least 1.',
            'recurring_interval.max' => 'The recurring interval must not exceed 12.',
            'recurring_end_date.after' => 'The recurring end date must be after the transaction date.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = Auth::user();
            $transaction = $this->route('transaction');

            // Validate that account belongs to user
            if ($this->has('account_id')) {
                $account = $user->accounts()->find($this->input('account_id'));
                if (!$account) {
                    $validator->errors()->add('account_id', 'The selected account does not belong to you.');
                    return;
                }

                // Check if account is active
                if (!$account->is_active) {
                    $validator->errors()->add('account_id', 'The selected account is not active.');
                }

                // For expenses, check balance (except credit cards)
                if ($this->input('type', $transaction->type) === 'expense' && $account->type !== 'credit_card') {
                    $newAmount = (float) $this->input('amount', $transaction->amount);
                    $oldAmount = $transaction->account_id === $account->id ? (float) $transaction->amount : 0;
                    $netChange = $newAmount - $oldAmount;

                    if ($account->balance < $netChange) {
                        $validator->errors()->add(
                            'amount',
                            'Insufficient balance in account. Available: ' .
                            $user->getCurrencySymbol() . number_format($account->balance, 2)
                        );
                    }
                }

                // For credit cards, check credit limit
                if ($account->type === 'credit_card' && $this->input('type', $transaction->type) === 'expense') {
                    $newAmount = (float) $this->input('amount', $transaction->amount);
                    $oldAmount = $transaction->account_id === $account->id ? (float) $transaction->amount : 0;
                    $netChange = $newAmount - $oldAmount;
                    $availableCredit = $account->credit_limit - abs($account->balance) + $oldAmount;

                    if ($netChange > $availableCredit) {
                        $validator->errors()->add(
                            'amount',
                            'Transaction exceeds available credit. Available: ' .
                            $user->getCurrencySymbol() . number_format($availableCredit, 2)
                        );
                    }
                }
            }

            // Validate that category belongs to user
            if ($this->has('category_id')) {
                $category = $user->categories()->find($this->input('category_id'));
                if (!$category) {
                    $validator->errors()->add('category_id', 'The selected category does not belong to you.');
                } elseif (!$category->is_active) {
                    $validator->errors()->add('category_id', 'The selected category is not active.');
                }
            }

            // Validate that transfer account belongs to user
            if ($this->has('transfer_account_id')) {
                $transferAccount = $user->accounts()->find($this->input('transfer_account_id'));
                if (!$transferAccount) {
                    $validator->errors()->add('transfer_account_id', 'The selected transfer account does not belong to you.');
                } elseif (!$transferAccount->is_active) {
                    $validator->errors()->add('transfer_account_id', 'The selected transfer account is not active.');
                }
            }

            // Validate transfer account requirements
            $type = $this->input('type', $transaction->type);
            if ($type === 'transfer' && !$this->has('transfer_account_id') && !$transaction->transfer_account_id) {
                $validator->errors()->add('transfer_account_id', 'Transfer account is required for transfer transactions.');
            }

            if ($type !== 'transfer' && $this->has('transfer_account_id')) {
                $validator->errors()->add('transfer_account_id', 'Transfer account should only be provided for transfer transactions.');
            }

            // Validate recurring fields
            if ($this->boolean('is_recurring', $transaction->is_recurring)) {
                if (!$this->has('recurring_type') && !$transaction->recurring_type) {
                    $validator->errors()->add('recurring_type', 'Recurring type is required for recurring transactions.');
                }

                if (!$this->has('recurring_interval') && !$transaction->recurring_interval) {
                    $validator->errors()->add('recurring_interval', 'Recurring interval is required for recurring transactions.');
                }
            }

            // Validate category type matches transaction type
            if ($this->has('category_id') && $this->has('type')) {
                $category = $user->categories()->find($this->input('category_id'));
                if ($category && $category->type !== $this->input('type') && $category->type !== 'transfer') {
                    $validator->errors()->add(
                        'category_id',
                        'Category type does not match transaction type. Expected: ' . $this->input('type')
                    );
                }
            }

            // Prevent updating cleared transactions older than 30 days
            if ($transaction->is_cleared && $transaction->date < now()->subDays(30)) {
                if ($this->has('amount') || $this->has('account_id') || $this->has('type')) {
                    $validator->errors()->add(
                        'transaction',
                        'Cannot modify critical fields of cleared transactions older than 30 days.'
                    );
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean up tags
        if ($this->has('tags') && is_array($this->input('tags'))) {
            $cleanTags = array_filter(array_map('trim', $this->input('tags')));
            $this->merge(['tags' => array_values($cleanTags)]);
        }
    }
}

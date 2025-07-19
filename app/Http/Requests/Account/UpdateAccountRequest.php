<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'in:cash,bank,credit_card,investment,ewallet'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'balance' => ['sometimes', 'required', 'numeric'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'color' => ['sometimes', 'required', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'icon' => ['sometimes', 'required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'include_in_net_worth' => ['sometimes', 'boolean'],
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
            'name.required' => 'The account name is required.',
            'name.max' => 'The account name must not exceed 255 characters.',
            'type.required' => 'The account type is required.',
            'type.in' => 'The account type must be one of: cash, bank, credit_card, investment, ewallet.',
            'balance.required' => 'The balance is required.',
            'balance.numeric' => 'The balance must be a valid number.',
            'credit_limit.numeric' => 'The credit limit must be a valid number.',
            'credit_limit.min' => 'The credit limit must be at least 0.',
            'currency.required' => 'The currency is required.',
            'currency.size' => 'The currency must be exactly 3 characters.',
            'color.required' => 'The color is required.',
            'color.regex' => 'The color must be a valid hex color code (e.g., #FF0000).',
            'icon.required' => 'The icon is required.',
            'icon.max' => 'The icon name must not exceed 50 characters.',
            'description.max' => 'The description must not exceed 500 characters.',
            'account_number.max' => 'The account number must not exceed 50 characters.',
            'bank_name.max' => 'The bank name must not exceed 255 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $account = $this->route('account');

            // Validate credit limit for credit card accounts
            $accountType = $this->input('type', $account->type);
            if ($accountType === 'credit_card') {
                $creditLimit = $this->input('credit_limit', $account->credit_limit);
                if (!$creditLimit) {
                    $validator->errors()->add('credit_limit', 'Credit limit is required for credit card accounts.');
                }

                // Validate that credit card balance doesn't exceed credit limit
                if ($this->has('balance') && $creditLimit) {
                    $balance = abs((float) $this->input('balance'));
                    if ($balance > $creditLimit) {
                        $validator->errors()->add('balance', 'Balance cannot exceed credit limit for credit card accounts.');
                    }
                }
            }

            // Validate currency is supported
            if ($this->has('currency')) {
                $supportedCurrencies = array_keys(config('user.currencies', []));
                if (!in_array($this->input('currency'), $supportedCurrencies)) {
                    $validator->errors()->add('currency', 'The selected currency is not supported.');
                }
            }

            // Prevent deactivating account if it has recent transactions
            if ($this->has('is_active') && !$this->boolean('is_active')) {
                $recentTransactionCount = $account->transactions()
                    ->where('date', '>=', now()->subDays(30))
                    ->count();

                if ($recentTransactionCount > 0) {
                    $validator->errors()->add(
                        'is_active',
                        "Cannot deactivate account with {$recentTransactionCount} transactions in the last 30 days."
                    );
                }
            }
        });
    }
}

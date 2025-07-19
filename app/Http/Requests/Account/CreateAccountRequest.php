<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class CreateAccountRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:cash,bank,credit_card,investment,ewallet'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'balance' => ['required', 'numeric'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'include_in_net_worth' => ['nullable', 'boolean'],
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
            'balance.required' => 'The initial balance is required.',
            'balance.numeric' => 'The balance must be a valid number.',
            'credit_limit.numeric' => 'The credit limit must be a valid number.',
            'credit_limit.min' => 'The credit limit must be at least 0.',
            'currency.size' => 'The currency must be exactly 3 characters.',
            'color.regex' => 'The color must be a valid hex color code (e.g., #FF0000).',
            'icon.max' => 'The icon name must not exceed 50 characters.',
            'description.max' => 'The description must not exceed 500 characters.',
            'account_number.max' => 'The account number must not exceed 50 characters.',
            'bank_name.max' => 'The bank name must not exceed 255 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values based on user preferences
        if (!$this->has('currency')) {
            $this->merge([
                'currency' => auth()->user()->currency ?? 'USD'
            ]);
        }

        // Set default color and icon based on account type
        if (!$this->has('color') || !$this->has('icon')) {
            $defaults = $this->getDefaultsForAccountType($this->input('type'));

            if (!$this->has('color')) {
                $this->merge(['color' => $defaults['color']]);
            }

            if (!$this->has('icon')) {
                $this->merge(['icon' => $defaults['icon']]);
            }
        }

        // Set default values for boolean fields
        if (!$this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }

        if (!$this->has('include_in_net_worth')) {
            $this->merge(['include_in_net_worth' => true]);
        }
    }

    /**
     * Get default color and icon for account type
     */
    private function getDefaultsForAccountType(?string $type): array
    {
        $defaults = [
            'cash' => ['color' => '#4CAF50', 'icon' => 'account_balance_wallet'],
            'bank' => ['color' => '#2196F3', 'icon' => 'account_balance'],
            'credit_card' => ['color' => '#F44336', 'icon' => 'credit_card'],
            'investment' => ['color' => '#FF9800', 'icon' => 'trending_up'],
            'ewallet' => ['color' => '#9C27B0', 'icon' => 'account_balance_wallet'],
        ];

        return $defaults[$type] ?? ['color' => '#607D8B', 'icon' => 'account_balance'];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate credit limit for credit card accounts
            if ($this->input('type') === 'credit_card' && !$this->has('credit_limit')) {
                $validator->errors()->add('credit_limit', 'Credit limit is required for credit card accounts.');
            }

            // Validate that credit card balance doesn't exceed credit limit
            if ($this->input('type') === 'credit_card' && $this->has('credit_limit')) {
                $balance = abs((float) $this->input('balance'));
                $creditLimit = (float) $this->input('credit_limit');

                if ($balance > $creditLimit) {
                    $validator->errors()->add('balance', 'Balance cannot exceed credit limit for credit card accounts.');
                }
            }

            // Validate currency is supported
            if ($this->has('currency')) {
                $supportedCurrencies = array_keys(config('user.currencies', []));
                if (!in_array($this->input('currency'), $supportedCurrencies)) {
                    $validator->errors()->add('currency', 'The selected currency is not supported.');
                }
            }
        });
    }
}

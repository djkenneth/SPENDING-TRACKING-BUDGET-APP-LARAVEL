<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
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
            'type' => ['sometimes', 'required', 'string', 'in:income,expense,transfer'],
            'color' => ['sometimes', 'required', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'icon' => ['sometimes', 'required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
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
            'name.required' => 'The category name is required.',
            'name.max' => 'The category name must not exceed 255 characters.',
            'type.required' => 'The category type is required.',
            'type.in' => 'The category type must be one of: income, expense, transfer.',
            'color.required' => 'The color is required.',
            'color.regex' => 'The color must be a valid hex color code (e.g., #FF0000).',
            'icon.required' => 'The icon is required.',
            'icon.max' => 'The icon name must not exceed 50 characters.',
            'description.max' => 'The description must not exceed 500 characters.',
            'sort_order.min' => 'The sort order must be at least 0.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = auth()->user();
            $category = $this->route('category');

            // Check if category name already exists for this user and type
            if ($this->has('name') && $this->has('type')) {
                $existingCategory = $user->categories()
                    ->where('name', $this->input('name'))
                    ->where('type', $this->input('type'))
                    ->where('id', '!=', $category->id)
                    ->first();

                if ($existingCategory) {
                    $validator->errors()->add(
                        'name',
                        'A category with this name already exists for this type.'
                    );
                }
            }

            // Prevent changing type if category has transactions
            if ($this->has('type') && $this->input('type') !== $category->type) {
                $transactionCount = $category->transactions()->count();
                if ($transactionCount > 0) {
                    $validator->errors()->add(
                        'type',
                        "Cannot change category type. This category has {$transactionCount} transactions."
                    );
                }
            }

            // Prevent deactivating category if it has recent transactions
            if ($this->has('is_active') && !$this->boolean('is_active')) {
                $recentTransactionCount = $category->transactions()
                    ->where('date', '>=', now()->subDays(30))
                    ->count();

                if ($recentTransactionCount > 0) {
                    $validator->errors()->add(
                        'is_active',
                        "Cannot deactivate category with {$recentTransactionCount} transactions in the last 30 days."
                    );
                }

                // Check if category is used in active budgets
                $activeBudgetCount = $category->budgets()
                    ->where('is_active', true)
                    ->count();

                if ($activeBudgetCount > 0) {
                    $validator->errors()->add(
                        'is_active',
                        "Cannot deactivate category with {$activeBudgetCount} active budget(s)."
                    );
                }
            }
        });
    }
}

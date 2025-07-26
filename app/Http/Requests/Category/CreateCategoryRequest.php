<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class CreateCategoryRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:income,expense,transfer'],
            'color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
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
            'color.regex' => 'The color must be a valid hex color code (e.g., #FF0000).',
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

            // Check if category name already exists for this user and type
            if ($this->has('name') && $this->has('type')) {
                $existingCategory = $user->categories()
                    ->where('name', $this->input('name'))
                    ->where('type', $this->input('type'))
                    ->first();

                if ($existingCategory) {
                    $validator->errors()->add(
                        'name',
                        'A category with this name already exists for this type.'
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
        // Set default values based on category type
        if (!$this->has('color') || !$this->has('icon')) {
            $defaults = $this->getDefaultsForCategoryType($this->input('type'));

            if (!$this->has('color')) {
                $this->merge(['color' => $defaults['color']]);
            }

            if (!$this->has('icon')) {
                $this->merge(['icon' => $defaults['icon']]);
            }
        }

        // Set default values for other fields
        if (!$this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }

        if (!$this->has('sort_order')) {
            $user = auth()->user();
            $maxSortOrder = $user->categories()->max('sort_order') ?? -1;
            $this->merge(['sort_order' => $maxSortOrder + 1]);
        }
    }

    /**
     * Get default color and icon for category type
     */
    private function getDefaultsForCategoryType(?string $type): array
    {
        $defaults = [
            'income' => ['color' => '#4CAF50', 'icon' => 'trending_up'],
            'expense' => ['color' => '#F44336', 'icon' => 'shopping_cart'],
            'transfer' => ['color' => '#00BCD4', 'icon' => 'swap_horiz'],
        ];

        return $defaults[$type] ?? ['color' => '#607D8B', 'icon' => 'category'];
    }
}

<?php

namespace App\Http\Requests\FinancialGoal;

use Illuminate\Foundation\Http\FormRequest;

class CreateFinancialGoalRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'target_date' => ['required', 'date', 'after:today'],
            'priority' => ['required', 'string', 'in:high,medium,low'],
            'status' => ['sometimes', 'string', 'in:active,paused'],
            'color' => ['sometimes', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'icon' => ['sometimes', 'string', 'max:50'],
            'monthly_target' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'milestone_settings' => ['nullable', 'array'],
            'milestone_settings.milestones' => ['nullable', 'array'],
            'milestone_settings.milestones.*' => ['integer', 'min:1', 'max:100'],
            'milestone_settings.notifications_enabled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The goal name is required.',
            'name.max' => 'The goal name must not exceed 255 characters.',
            'description.max' => 'The description must not exceed 1000 characters.',
            'target_amount.required' => 'The target amount is required.',
            'target_amount.numeric' => 'The target amount must be a valid number.',
            'target_amount.min' => 'The target amount must be at least 0.01.',
            'target_amount.max' => 'The target amount is too large.',
            'target_date.required' => 'The target date is required.',
            'target_date.date' => 'The target date must be a valid date.',
            'target_date.after' => 'The target date must be in the future.',
            'priority.required' => 'The priority is required.',
            'priority.in' => 'The priority must be high, medium, or low.',
            'status.in' => 'The status must be active or paused.',
            'color.regex' => 'The color must be a valid hex color code (e.g., #FF0000).',
            'icon.max' => 'The icon name must not exceed 50 characters.',
            'monthly_target.numeric' => 'The monthly target must be a valid number.',
            'milestone_settings.milestones.*.integer' => 'Milestone percentages must be integers.',
            'milestone_settings.milestones.*.min' => 'Milestone percentages must be at least 1.',
            'milestone_settings.milestones.*.max' => 'Milestone percentages must not exceed 100.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values if not provided
        $this->merge([
            'status' => $this->input('status', 'active'),
            'color' => $this->input('color', '#2196F3'),
            'icon' => $this->input('icon', 'flag'),
        ]);
    }
}

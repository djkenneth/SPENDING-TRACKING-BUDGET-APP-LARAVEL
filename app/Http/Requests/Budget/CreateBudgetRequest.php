<?php

namespace App\Http\Requests\Budget;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class CreateBudgetRequest extends FormRequest
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
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) {
                    return $query->where('user_id', auth()->id());
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'period' => ['required', 'string', 'in:weekly,monthly,yearly'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['nullable', 'boolean'],
            'alert_threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'alert_enabled' => ['nullable', 'boolean'],
            'rollover_settings' => ['nullable', 'array'],
            'rollover_settings.enabled' => ['nullable', 'boolean'],
            'rollover_settings.carry_over_unused' => ['nullable', 'boolean'],
            'rollover_settings.reset_on_overspend' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Please select a category for this budget.',
            'category_id.exists' => 'The selected category is invalid or does not belong to you.',
            'name.required' => 'Budget name is required.',
            'name.max' => 'Budget name cannot exceed 255 characters.',
            'amount.required' => 'Budget amount is required.',
            'amount.min' => 'Budget amount must be at least 0.01.',
            'amount.max' => 'Budget amount cannot exceed 999,999,999.99.',
            'period.required' => 'Budget period is required.',
            'period.in' => 'Budget period must be weekly, monthly, or yearly.',
            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Please provide a valid start date.',
            'end_date.required' => 'End date is required.',
            'end_date.date' => 'Please provide a valid end date.',
            'end_date.after' => 'End date must be after the start date.',
            'alert_threshold.numeric' => 'Alert threshold must be a number.',
            'alert_threshold.min' => 'Alert threshold cannot be negative.',
            'alert_threshold.max' => 'Alert threshold cannot exceed 100%.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'alert_threshold' => 'alert threshold',
            'alert_enabled' => 'alert enabled',
            'rollover_settings' => 'rollover settings',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for overlapping budgets for the same category
            if (!$validator->errors()->has('category_id') && !$validator->errors()->has('start_date') && !$validator->errors()->has('end_date')) {
                $overlappingBudget = Auth::user()->budgets()
                    ->where('category_id', $this->category_id)
                    ->where('is_active', true)
                    ->where(function ($query) {
                        $query->whereBetween('start_date', [$this->start_date, $this->end_date])
                            ->orWhereBetween('end_date', [$this->start_date, $this->end_date])
                            ->orWhere(function ($q) {
                                $q->where('start_date', '<=', $this->start_date)
                                  ->where('end_date', '>=', $this->end_date);
                            });
                    })
                    ->exists();

                if ($overlappingBudget) {
                    $validator->errors()->add('start_date', 'A budget for this category already exists for the specified period.');
                }
            }

            // Validate period matches date range
            if (!$validator->errors()->has('period') && !$validator->errors()->has('start_date') && !$validator->errors()->has('end_date')) {
                $startDate = \Carbon\Carbon::parse($this->start_date);
                $endDate = \Carbon\Carbon::parse($this->end_date);
                $daysDiff = $startDate->diffInDays($endDate) + 1;

                $expectedDays = match ($this->period) {
                    'weekly' => 7,
                    'monthly' => $startDate->daysInMonth,
                    'yearly' => $startDate->isLeapYear() ? 366 : 365,
                    default => null,
                };

                if ($expectedDays && abs($daysDiff - $expectedDays) > 2) { // Allow 2-day tolerance
                    $validator->errors()->add('period', "The selected period doesn't match the date range. Expected approximately {$expectedDays} days, got {$daysDiff} days.");
                }
            }
        });
    }
}

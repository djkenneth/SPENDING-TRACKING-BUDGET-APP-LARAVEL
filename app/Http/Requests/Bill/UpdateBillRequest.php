<?php

namespace App\Http\Requests\Bill;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('bill')->user_id === auth()->id();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'category_id' => [
                'sometimes',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) {
                    return $query->where('user_id', auth()->id());
                }),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'due_date' => ['sometimes', 'date'],
            'frequency' => [
                'sometimes',
                'string',
                'in:weekly,bi-weekly,monthly,quarterly,semi-annually,annually'
            ],
            'reminder_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'status' => ['sometimes', 'string', 'in:active,paid,overdue,cancelled'],
            'is_recurring' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'The selected category is invalid or does not belong to you.',
            'name.max' => 'Bill name cannot exceed 255 characters.',
            'amount.numeric' => 'Bill amount must be a number.',
            'amount.min' => 'Bill amount must be at least 0.01.',
            'due_date.date' => 'Please provide a valid due date.',
            'frequency.in' => 'Invalid billing frequency selected.',
            'status.in' => 'Invalid status selected.',
            'reminder_days.integer' => 'Reminder days must be a whole number.',
            'reminder_days.min' => 'Reminder days cannot be negative.',
            'reminder_days.max' => 'Reminder days cannot exceed 30.',
            'color.regex' => 'Color must be a valid hex color code (e.g., #FF5733).',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('schedule_type') === 'recurring' && ! $this->filled('recurrence_mode')) {
            $this->merge(['recurrence_mode' => 'weekly']);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->is_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'area' => ['required', 'string', 'max:255'],
            'schedule_type' => ['required', 'in:recurring,specific_date'],
            'specific_date' => ['required_if:schedule_type,specific_date', 'nullable', 'date', 'after_or_equal:today'],
            'recurrence_mode' => ['nullable', Rule::requiredIf(fn () => $this->input('schedule_type') === 'recurring'), 'in:weekly,interval'],
            'recurrence_start_date' => ['nullable', Rule::requiredIf(fn () => $this->isIntervalRecurrence()), 'date'],
            'recurrence_interval_days' => ['nullable', Rule::requiredIf(fn () => $this->isIntervalRecurrence()), 'integer', 'min:1', 'max:365'],
            'days' => ['nullable', Rule::requiredIf(fn () => $this->input('schedule_type') === 'recurring' && ! $this->isIntervalRecurrence()), 'string', 'max:255'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i', 'after:time_start'],
            'truck' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,pending,inactive'],
        ];
    }

    private function isIntervalRecurrence(): bool
    {
        return $this->input('schedule_type') === 'recurring'
            && $this->input('recurrence_mode') === 'interval';
    }
}

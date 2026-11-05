<?php

namespace App\Http\Requests;

use App\Validation\RequiredCoordinates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreServiceAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['area_name', 'barangay', 'address', 'details'] as $field) {
            if (! $this->has($field) || ! is_string($this->input($field))) {
                continue;
            }

            $value = trim($this->input($field));
            $values[$field] = $value === '' ? null : $value;
        }

        if (isset($values['area_name'])) {
            $values['area_name'] = preg_replace('/\s+/u', ' ', $values['area_name']) ?? $values['area_name'];
        }

        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'area_name' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            ...RequiredCoordinates::rules(),
            'details' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'area_name.required' => 'Area / Purok / Sitio is required.',
            'barangay.required' => 'Barangay is required.',
            ...RequiredCoordinates::messages('Please select the requested service location.'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => RequiredCoordinates::rejectNullIsland(
                $validator,
                'Please select a valid requested service location.'
            ),
        ];
    }
}

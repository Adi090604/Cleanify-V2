<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'barangay' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'details' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

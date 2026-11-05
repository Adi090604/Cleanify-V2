<?php

namespace App\Http\Requests;

use App\Validation\RequiredCoordinates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'barangay' => is_string($this->barangay) ? trim($this->barangay) : $this->barangay,
            'location' => is_string($this->location) ? trim($this->location) : $this->location,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'barangay' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            ...RequiredCoordinates::rules(),
            'description' => ['required', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'barangay.required' => 'Barangay is required.',
            'location.required' => 'Please enter the specific location or a nearby landmark.',
            ...RequiredCoordinates::messages('Please select the report location before submitting.'),
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
                'Please select a valid report location.'
            ),
        ];
    }
}

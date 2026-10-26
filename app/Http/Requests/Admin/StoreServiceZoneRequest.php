<?php

namespace App\Http\Requests\Admin;

use App\Validation\ServiceZoneRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceZoneRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'barangay' => is_string($this->input('barangay')) ? trim($this->input('barangay')) : $this->input('barangay'),
        ]);
    }

    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->is_admin;
    }

    public function rules(): array
    {
        return ServiceZoneRules::create($this->input('barangay'));
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Local Area / Purok / Sitio is required.',
            'barangay.required' => 'Barangay is required.',
        ];
    }
}

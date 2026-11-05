<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignExistingServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'service_zone_id' => ['required', 'integer', 'exists:service_zones,id'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

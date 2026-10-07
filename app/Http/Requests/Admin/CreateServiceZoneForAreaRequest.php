<?php

namespace App\Http\Requests\Admin;

use App\Validation\ServiceZoneRules;
use Illuminate\Foundation\Http\FormRequest;

class CreateServiceZoneForAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            ...ServiceZoneRules::create(),
            'assign_to_requester' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A Service Zone with this name already exists. Review the existing Service Zones before creating another one.',
        ];
    }
}

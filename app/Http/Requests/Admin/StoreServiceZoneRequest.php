<?php

namespace App\Http\Requests\Admin;

use App\Validation\ServiceZoneRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->is_admin;
    }

    public function rules(): array
    {
        return ServiceZoneRules::create();
    }
}

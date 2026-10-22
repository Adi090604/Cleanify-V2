<?php

namespace App\Http\Requests\Admin;

use App\Models\Truck;
use Illuminate\Foundation\Http\FormRequest;

class StoreTruckRequest extends FormRequest
{
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
            'code' => ['required', 'string', 'max:255', 'unique:trucks,code'],
            'driver' => ['required', 'string', 'max:255'],
            'route' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,on_break,offline,maintenance'],
            'coverage_radius_meters' => ['required', 'integer', 'min:'.Truck::MIN_COVERAGE_RADIUS_METERS, 'max:'.Truck::MAX_COVERAGE_RADIUS_METERS],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}

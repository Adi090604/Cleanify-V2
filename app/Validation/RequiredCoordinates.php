<?php

namespace App\Validation;

use Illuminate\Validation\Validator;

final class RequiredCoordinates
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'latitude' => ['bail', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['bail', 'required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $requiredMessage): array
    {
        return [
            'latitude.required' => $requiredMessage,
            'longitude.required' => $requiredMessage,
            'latitude.numeric' => 'Please select a valid location.',
            'longitude.numeric' => 'Please select a valid location.',
            'latitude.between' => 'Please select a valid location.',
            'longitude.between' => 'Please select a valid location.',
        ];
    }

    public static function rejectNullIsland(Validator $validator, string $message): void
    {
        if ($validator->errors()->has('latitude') || $validator->errors()->has('longitude')) {
            return;
        }

        $latitude = (float) $validator->getData()['latitude'];
        $longitude = (float) $validator->getData()['longitude'];

        if ($latitude === 0.0 && $longitude === 0.0) {
            $validator->errors()->add('latitude', $message);
            $validator->errors()->add('longitude', $message);
        }
    }
}

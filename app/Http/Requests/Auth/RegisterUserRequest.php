<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\PhilippineMobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'regex:'.PhilippineMobileNumber::REGEX],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'The mobile number field is required.',
            'phone.regex' => 'Enter a valid Philippine mobile number, such as 09171234567 or +639171234567.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => trim($this->input('phone'))]);
        }
    }

    public function normalizedPhone(): string
    {
        return PhilippineMobileNumber::normalize($this->validated('phone'));
    }
}

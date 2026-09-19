<?php

namespace App\Http\Requests\Api\Auth;

use App\enum\Gender;
use App\enum\Role;
use App\Http\Requests\Concerns\NormalizesMsisdn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use NormalizesMsisdn;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'dial_code' => ['nullable', 'string', 'max:10'],
            'msisdn' => ['required', 'string', 'digits_between:10,15', Rule::unique('users', 'msisdn')],
            'gender' => ['nullable', Rule::in(Gender::names())],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'role' => ['nullable', Rule::in(array_column(Role::selfRegisterable(), 'name'))],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * The attributes used to create the user.
     *
     * Absent optional fields are dropped so the column defaults apply, and the
     * role is excluded because it is assigned explicitly rather than filled.
     *
     * @return array<string, mixed>
     */
    public function attributesForUser(): array
    {
        return array_filter(
            $this->safe()->except('role'),
            fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * The role the visitor is registering as. Admins are never self assigned.
     */
    public function role(): Role
    {
        return $this->filled('role')
            ? Role::fromName($this->string('role')->toString())
            : Role::Passenger;
    }
}

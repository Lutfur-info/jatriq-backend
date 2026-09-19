<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Concerns\NormalizesMsisdn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    use NormalizesMsisdn;

    /**
     * Get the validation rules that apply to the request.
     *
     * The password is held to the same standard registration applies, and is
     * confirmed here because a reset is typed blind and cannot be retried
     * with the code that was just spent.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'msisdn' => ['required', 'string', 'digits_between:10,15', Rule::exists('users', 'msisdn')],
            'code' => ['required', 'string', 'digits:'.config('otp.length')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'msisdn.exists' => 'No account was found for this number.',
        ];
    }

    /**
     * The name recorded against the token issued after the reset.
     */
    public function deviceName(): string
    {
        return $this->filled('device_name')
            ? $this->string('device_name')->toString()
            : (string) $this->userAgent();
    }
}

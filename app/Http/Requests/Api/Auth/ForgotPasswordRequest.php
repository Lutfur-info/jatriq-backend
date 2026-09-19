<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Concerns\NormalizesMsisdn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForgotPasswordRequest extends FormRequest
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
            'msisdn' => ['required', 'string', 'digits_between:10,15', Rule::exists('users', 'msisdn')],
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
}

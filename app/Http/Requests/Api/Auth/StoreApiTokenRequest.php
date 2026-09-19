<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreApiTokenRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The name recorded against the issued token.
     */
    public function deviceName(): string
    {
        return $this->filled('device_name')
            ? $this->string('device_name')->toString()
            : (string) $this->userAgent();
    }
}

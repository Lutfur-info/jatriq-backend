<?php

namespace App\Http\Requests\Api\EmergencyContact;

use App\Http\Requests\Concerns\NormalizesMsisdn;
use App\Models\EmergencyContact;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmergencyContactRequest extends FormRequest
{
    use NormalizesMsisdn;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'relation' => ['sometimes', 'required', 'string', 'max:50'],
            'dial_code' => ['sometimes', 'required', 'string', 'max:10'],
            'msisdn' => [
                'sometimes',
                'required',
                'string',
                'digits_between:10,15',
                Rule::notIn([$user->msisdn]),
                Rule::unique('emergency_contacts', 'msisdn')
                    ->where('user_id', $user->getKey())
                    ->ignoreModel($this->contact()),
            ],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The contact being edited, resolved from the route.
     */
    public function contact(): EmergencyContact
    {
        /** @var EmergencyContact $contact */
        $contact = $this->route('emergency_contact');

        return $contact;
    }

    /**
     * Only the fields the request actually sent, so a partial edit is partial.
     *
     * @return array<string, mixed>
     */
    public function attributesForContact(): array
    {
        return $this->safe()->only(['name', 'relation', 'dial_code', 'msisdn']);
    }

    /**
     * Whether the contact was asked to become the primary one.
     *
     * Null means the request said nothing about it, which leaves the current
     * primary alone - the list always keeps exactly one.
     */
    public function isPrimary(): ?bool
    {
        return $this->has('is_primary') ? $this->boolean('is_primary') : null;
    }

    /**
     * Custom messages for the rules whose defaults read poorly here.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'msisdn.not_in' => 'An emergency contact cannot be your own number.',
            'msisdn.unique' => 'This number is already saved as an emergency contact.',
        ];
    }
}

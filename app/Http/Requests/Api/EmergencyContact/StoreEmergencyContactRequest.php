<?php

namespace App\Http\Requests\Api\EmergencyContact;

use App\Http\Requests\Concerns\NormalizesMsisdn;
use App\Models\User;
use App\Repositories\Contracts\EmergencyContactRepository;
use App\Services\EmergencyContactService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * @phpstan-import-type ContactAttributes from EmergencyContactRepository
 */
class StoreEmergencyContactRequest extends FormRequest
{
    use NormalizesMsisdn;

    /**
     * The ceiling lives in the service, so the request asks rather than
     * counting rows itself - no Eloquent query outside the repository.
     */
    public function __construct(private EmergencyContactService $contacts)
    {
        parent::__construct();
    }

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
            'name' => ['required', 'string', 'max:100'],
            'relation' => ['required', 'string', 'max:50'],
            'dial_code' => ['nullable', 'string', 'max:10'],
            'msisdn' => [
                'required',
                'string',
                'digits_between:10,15',
                // Calling yourself in an emergency helps nobody.
                Rule::notIn([$user->msisdn]),
                Rule::unique('emergency_contacts', 'msisdn')->where('user_id', $user->getKey()),
            ],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Stop the list growing past the configured ceiling.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $user */
                $user = $this->user();

                if ($this->contacts->remainingSlots($user) > 0) {
                    return;
                }

                $maximum = $this->contacts->maximum();

                $validator->errors()->add(
                    'msisdn',
                    "You can save at most {$maximum} emergency contacts. Remove one before adding another.",
                );
            },
        ];
    }

    /**
     * The stored attributes, with absent optionals left to the column default.
     *
     * @return ContactAttributes
     */
    public function attributesForContact(): array
    {
        $attributes = [
            'name' => $this->string('name')->toString(),
            'relation' => $this->string('relation')->toString(),
            'msisdn' => $this->string('msisdn')->toString(),
        ];

        // Omitted rather than nulled, so the dial_code column default applies.
        if ($this->filled('dial_code')) {
            $attributes['dial_code'] = $this->string('dial_code')->toString();
        }

        return $attributes;
    }

    /**
     * Whether the contact should become the one called first.
     */
    public function isPrimary(): bool
    {
        return $this->boolean('is_primary');
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

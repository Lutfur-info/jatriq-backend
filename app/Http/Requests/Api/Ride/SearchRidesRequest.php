<?php

namespace App\Http\Requests\Api\Ride;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A passenger's journey, as two stops on the network.
 *
 * Both optional, but **only together**: "from Laksam" does not say which way
 * the passenger is going, and a corridor read backwards is a different set of
 * rides entirely. Send neither and the endpoint stays the open shop window it
 * has always been.
 *
 * Unlike publishing, a retired stop is accepted here. Retiring one stops new
 * rides being offered from it; it must not blind a passenger to the rides
 * already published against it.
 *
 * Shared with the public web board, which searches on exactly the same pair -
 * so the rule about both or neither is stated once. It lives under `Api`
 * because that is where the search was born; a browser hitting it gets a
 * redirect back with the errors instead of a 422, which is Laravel deciding
 * that from the request, not a second set of rules.
 */
class SearchRidesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $known = Rule::exists('stops', 'id');

        return [
            'from_stop_id' => ['nullable', 'integer', $known, 'required_with:to_stop_id'],
            'to_stop_id' => [
                'nullable',
                'integer',
                $known,
                'required_with:from_stop_id',
                'different:from_stop_id',
            ],
        ];
    }

    /**
     * Where the passenger wants to board, if they said.
     */
    public function fromStopId(): ?int
    {
        return $this->filled('from_stop_id') ? $this->integer('from_stop_id') : null;
    }

    /**
     * Where they want to get off, if they said.
     */
    public function toStopId(): ?int
    {
        return $this->filled('to_stop_id') ? $this->integer('to_stop_id') : null;
    }

    /**
     * Human readable field names for the validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from_stop_id' => 'pick-up point',
            'to_stop_id' => 'drop-off point',
        ];
    }

    /**
     * Messages worth spelling out.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_stop_id.required_with' => 'Say where you want to be picked up.',
            'to_stop_id.required_with' => 'Say where you want to get off.',
            'to_stop_id.different' => 'Pick a drop-off point other than where you are boarding.',
        ];
    }
}

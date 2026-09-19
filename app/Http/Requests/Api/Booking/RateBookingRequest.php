<?php

namespace App\Http\Requests\Api\Booking;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A passenger's score for a trip she took.
 *
 * The score is the whole request. **Whether she may score it at all** is
 * `RatingService`'s call, not a rule here - it depends on the driver having
 * confirmed her seat and the ride having departed, neither of which is in
 * the body.
 *
 * One to five and integral: half stars are a display decision a client can
 * make about an *average*, never something a single passenger submits.
 */
class RateBookingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }

    /**
     * The score she gave.
     */
    public function rating(): int
    {
        return $this->integer('rating');
    }

    /**
     * Messages worth spelling out.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.min' => 'A rating runs from 1 to 5.',
            'rating.max' => 'A rating runs from 1 to 5.',
        ];
    }
}

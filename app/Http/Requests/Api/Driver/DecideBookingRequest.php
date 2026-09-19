<?php

namespace App\Http\Requests\Api\Driver;

use App\enum\BookingStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The driver's answer to a request for seats on their ride.
 *
 * The status is the whole request. Nothing else about the booking is the
 * driver's to change - the seat count is what the passenger asked for, and
 * confirming fewer seats than were asked for would be a different booking.
 *
 * `BookingStatus::decidable()` is the allow-list, so `Pending` is refused:
 * that is where a request arrives, and a passenger asking again is the only
 * thing that puts one back. Shaped after `DocumentStatus::reviewable()`,
 * which gates a reviewer's decision the same way.
 */
class DecideBookingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_column(BookingStatus::decidable(), 'name'))],
        ];
    }

    /**
     * The decision the driver recorded.
     */
    public function status(): BookingStatus
    {
        return BookingStatus::fromName($this->string('status')->toString());
    }

    /**
     * Messages worth spelling out, because the default lists case names.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Answer a request with Confirmed or Declined.',
        ];
    }
}

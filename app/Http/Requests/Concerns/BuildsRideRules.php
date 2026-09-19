<?php

namespace App\Http\Requests\Concerns;

use App\Models\Ride;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The rules a ride's details answer to, shared by publishing and editing.
 *
 * Both ends of the trip are **stop ids**, never free text: a ride only
 * becomes searchable once it sits at a known position on a known corridor,
 * and a hand-typed place name cannot give it one. The label on the ride is
 * written from the chosen stop by TravelRouteService, so nothing here
 * validates it.
 *
 * Only the rules, the messages and the departure window live here. Building
 * the attributes does not: publishing produces a complete ride and editing
 * produces only the fields that were sent, which is a real difference rather
 * than a flag to pass around.
 */
trait BuildsRideRules
{
    /**
     * @param  int  $seatsMax  The vehicle's passenger seats - the most a ride
     *                         in it can offer.
     * @param  bool  $partial  Whether an absent field is allowed, as it is on
     *                         an edit of one detail.
     * @return array<string, array<int, mixed>>
     */
    protected function rideRules(int $seatsMax, bool $partial = false): array
    {
        $needed = $partial ? ['sometimes', 'required'] : ['required'];

        // A retired stop stays valid on the rides already published against
        // it, but nobody may publish a new one from it.
        $known = Rule::exists('stops', 'id')->where('is_active', true);

        return [
            'origin_stop_id' => [...$needed, 'integer', $known],

            /*
             * `different` catches a ride that starts where it ends while both
             * ends are in the body. A partial edit that sends only one of
             * them is caught by startsWhereItEnds() instead, which reads the
             * other end off the ride.
             */
            'destination_stop_id' => [...$needed, 'integer', $known, 'different:origin_stop_id'],

            'departs_at' => [
                ...$needed,
                'date',
                'after_or_equal:'.$this->earliestDeparture()->toDateTimeString(),
                'before_or_equal:'.$this->latestDeparture()->toDateTimeString(),
            ],

            'seat_price' => [
                ...$needed,
                'numeric',
                'min:'.(int) config('rides.price.min'),
                'max:'.(int) config('rides.price.max'),
            ],

            'seats_offered' => [...$needed, 'integer', 'min:1', 'max:'.$seatsMax],
        ];
    }

    /**
     * Whether the trip would start and end at the same stop.
     *
     * A *minimum trip length* is a product decision nobody has taken - two
     * neighbouring towns on a corridor are a legitimate hop - so this only
     * catches the degenerate case rather than inventing a threshold.
     *
     * On an edit the end that was not sent is read from the ride, so moving
     * only the destination is still checked against the real start.
     */
    protected function startsWhereItEnds(?Ride $ride = null): bool
    {
        $origin = $this->stopId('origin_stop_id', $ride);
        $destination = $this->stopId('destination_stop_id', $ride);

        return $origin !== null && $origin === $destination;
    }

    /**
     * Messages worth spelling out, because the defaults read as timestamps.
     *
     * @return array<string, string>
     */
    protected function rideMessages(): array
    {
        $lead = (int) config('rides.departure.min_lead_minutes');
        $days = (int) config('rides.departure.max_days_ahead');

        return [
            'departs_at.after_or_equal' => "A ride must leave at least {$lead} minutes from now.",
            'departs_at.before_or_equal' => "A ride cannot be scheduled more than {$days} days ahead.",
            'seats_offered.max' => 'Your vehicle does not have that many passenger seats.',
            'origin_stop_id.exists' => 'That start point is not a stop we run through.',
            'destination_stop_id.exists' => 'That destination is not a stop we run through.',
            'destination_stop_id.different' => 'The destination must be somewhere other than the start point.',
        ];
    }

    /**
     * Human readable field names for the validation messages.
     *
     * @return array<string, string>
     */
    protected function rideAttributeNames(): array
    {
        return [
            'origin_stop_id' => 'start point',
            'destination_stop_id' => 'destination',
            'departs_at' => 'departure time',
            'seat_price' => 'seat price',
            'seats_offered' => 'seats offered',
        ];
    }

    /**
     * The earliest departure the lead time allows.
     */
    protected function earliestDeparture(): Carbon
    {
        return Carbon::now()->addMinutes((int) config('rides.departure.min_lead_minutes'));
    }

    /**
     * The far edge of the booking horizon.
     */
    protected function latestDeparture(): Carbon
    {
        return Carbon::now()->addDays((int) config('rides.departure.max_days_ahead'));
    }

    /**
     * One end of the trip: what the request sent, else what the ride holds.
     */
    private function stopId(string $field, ?Ride $ride): ?int
    {
        if ($this->has($field)) {
            return $this->integer($field);
        }

        return $ride?->getAttribute($field);
    }
}

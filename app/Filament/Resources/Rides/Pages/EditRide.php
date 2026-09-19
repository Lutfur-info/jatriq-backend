<?php

namespace App\Filament\Resources\Rides\Pages;

use App\Filament\Resources\Rides\RideResource;
use App\Models\Ride;
use App\Services\RideService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * An admin's correction to a ride, booked or not.
 *
 * The only screen in the panel that writes something a rider authored, and
 * the reason it exists is that nothing else can: there is no cancellation
 * anywhere in the product, so a ride published with the wrong departure and
 * three passengers aboard has no other way of being put right.
 *
 * The write goes through `RideService::override()` rather than Filament's
 * own save, for the reason every decision goes through `VerificationService`:
 * the corridor and both sequences are the service's conclusion about which
 * road the two stops share, and a ride saved without them is still listed
 * but invisible to every search.
 */
class EditRide extends EditRecord
{
    protected static string $resource = RideResource::class;

    /**
     * Hand the edit to the service, under the same lock a booking takes.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Ride $record */
        try {
            return app(RideService::class)->override($record, [
                'origin_stop_id' => (int) $data['origin_stop_id'],
                'destination_stop_id' => (int) $data['destination_stop_id'],
                'departs_at' => Carbon::parse($data['departs_at']),
                'seat_price' => (string) $data['seat_price'],
                'seats_offered' => (int) $data['seats_offered'],
            ]);
        } catch (ValidationException $exception) {
            // The service keys its refusals on the ride's own field names;
            // the form's state lives under `data`, so a message would
            // otherwise land nowhere the admin can see it.
            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field): array => ["data.{$field}" => $messages])
                    ->all(),
            );
        }
    }

    protected function getRedirectUrl(): string
    {
        // Back to the ride, where the passenger list is.
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}

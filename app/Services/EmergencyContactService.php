<?php

namespace App\Services;

use App\Models\EmergencyContact;
use App\Models\User;
use App\Repositories\Contracts\EmergencyContactRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Maintains a user's emergency contact list, whichever side of the ride
 * they are on.
 *
 * The list holds exactly one primary contact whenever it is not empty: the
 * first contact added becomes primary, and deleting the primary promotes the
 * next one rather than leaving the list without a first port of call.
 *
 * @phpstan-import-type ContactAttributes from EmergencyContactRepository
 */
class EmergencyContactService
{
    public function __construct(private EmergencyContactRepository $contacts) {}

    /**
     * The user's contacts, primary first.
     *
     * @return Collection<int, EmergencyContact>
     */
    public function list(User $user): Collection
    {
        return $this->contacts->forUser($user);
    }

    /**
     * How many more contacts the user may add.
     */
    public function remainingSlots(User $user): int
    {
        return max(0, $this->maximum() - $this->contacts->countForUser($user));
    }

    /**
     * The number of contacts one user may hold.
     */
    public function maximum(): int
    {
        return (int) config('verification.emergency_contacts.max');
    }

    /**
     * Add a contact to the user's list.
     *
     * @param  ContactAttributes  $attributes
     */
    public function create(User $user, array $attributes, bool $isPrimary = false): EmergencyContact
    {
        return DB::transaction(function () use ($user, $attributes, $isPrimary): EmergencyContact {
            $isFirst = $this->contacts->countForUser($user) === 0;

            if ($isPrimary && ! $isFirst) {
                $this->contacts->clearPrimary($user);
            }

            return $this->contacts->create($user, $attributes, $isPrimary || $isFirst);
        });
    }

    /**
     * Change a contact's details, and optionally make it the primary one.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(EmergencyContact $contact, array $attributes, ?bool $isPrimary = null): EmergencyContact
    {
        return DB::transaction(function () use ($contact, $attributes, $isPrimary): EmergencyContact {
            $contact = $this->contacts->update($contact, $attributes);

            // Unflagging the only primary would leave the list without one, so
            // a contact is only ever promoted here, never demoted.
            if ($isPrimary === true) {
                $contact = $this->contacts->makePrimary($contact);
            }

            return $contact;
        });
    }

    /**
     * Remove a contact, promoting a replacement if the primary one goes.
     */
    public function delete(EmergencyContact $contact): void
    {
        DB::transaction(function () use ($contact): void {
            $wasPrimary = $contact->is_primary;
            $user = $contact->user;

            $this->contacts->delete($contact);

            if (! $wasPrimary) {
                return;
            }

            $successor = $this->contacts->firstForUser($user);

            if ($successor !== null) {
                $this->contacts->makePrimary($successor);
            }
        });
    }
}

<?php

namespace App\Repositories\Contracts;

use App\Models\EmergencyContact;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Persistence for a user's emergency contacts.
 *
 * @phpstan-type ContactAttributes array{
 *     name: string,
 *     relation: string,
 *     dial_code?: string,
 *     msisdn: string,
 * }
 */
interface EmergencyContactRepository
{
    /**
     * The user's contacts, primary first then oldest first.
     *
     * @return Collection<int, EmergencyContact>
     */
    public function forUser(User $user): Collection;

    /**
     * How many contacts the user already holds.
     */
    public function countForUser(User $user): int;

    /**
     * The user's oldest contact, which is the one that takes over as primary
     * once the previous primary is gone. Null when the list is empty.
     */
    public function firstForUser(User $user): ?EmergencyContact;

    /**
     * Add a contact to the user's list.
     *
     * @param  ContactAttributes  $attributes
     */
    public function create(User $user, array $attributes, bool $isPrimary): EmergencyContact;

    /**
     * Change an existing contact's details.
     *
     * @param  ContactAttributes|array<string, mixed>  $attributes
     */
    public function update(EmergencyContact $contact, array $attributes): EmergencyContact;

    /**
     * Remove a contact from the user's list.
     */
    public function delete(EmergencyContact $contact): void;

    /**
     * Move the primary flag onto one contact and off every other.
     */
    public function makePrimary(EmergencyContact $contact): EmergencyContact;

    /**
     * Drop the primary flag from all of the user's contacts.
     */
    public function clearPrimary(User $user): void;
}

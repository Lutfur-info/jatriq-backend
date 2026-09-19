<?php

namespace App\Repositories\Eloquent;

use App\Models\EmergencyContact;
use App\Models\User;
use App\Repositories\Contracts\EmergencyContactRepository;
use Illuminate\Support\Collection;

/**
 * @phpstan-import-type ContactAttributes from EmergencyContactRepository
 */
class EmergencyContactEloquentRepository implements EmergencyContactRepository
{
    /**
     * {@inheritDoc}
     */
    public function forUser(User $user): Collection
    {
        return $user->emergencyContacts()
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function countForUser(User $user): int
    {
        return $user->emergencyContacts()->count();
    }

    /**
     * {@inheritDoc}
     */
    public function firstForUser(User $user): ?EmergencyContact
    {
        return $user->emergencyContacts()
            ->orderBy('id')
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function create(User $user, array $attributes, bool $isPrimary): EmergencyContact
    {
        $contact = $user->emergencyContacts()->make($attributes);

        // Never fillable: which contact is primary is decided by the service,
        // not by whatever the request body happens to carry.
        $contact->is_primary = $isPrimary;
        $contact->save();

        // Pull in the column defaults applied on insert, such as dial_code.
        return $contact->refresh();
    }

    /**
     * {@inheritDoc}
     */
    public function update(EmergencyContact $contact, array $attributes): EmergencyContact
    {
        $contact->fill($attributes)->save();

        return $contact;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(EmergencyContact $contact): void
    {
        $contact->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function makePrimary(EmergencyContact $contact): EmergencyContact
    {
        $contact->user->emergencyContacts()
            ->whereKeyNot($contact->getKey())
            ->update(['is_primary' => false]);

        $contact->forceFill(['is_primary' => true])->save();

        return $contact;
    }

    /**
     * {@inheritDoc}
     */
    public function clearPrimary(User $user): void
    {
        $user->emergencyContacts()->update(['is_primary' => false]);
    }
}

<?php

namespace App\enum;

use Illuminate\Support\Str;

/**
 * The identity documents a user may be asked to submit.
 *
 * Cases are persisted by name, matching `$table->enum('type', DocumentType::names())`.
 */
enum DocumentType
{
    case NidFront;
    case NidBack;
    case DrivingLicence;
    case VehicleRegistration;
    case ProfilePhoto;

    /**
     * The values persisted in the database.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Resolve a case from its persisted name.
     *
     * @throws \Error when the name does not match a case.
     */
    public static function fromName(string $name): self
    {
        return constant(self::class.'::'.$name);
    }

    /**
     * Resolve a case from the request field it is uploaded under.
     */
    public static function fromField(string $field): self
    {
        return self::fromName(Str::studly($field));
    }

    /**
     * The documents a role must submit before its badge can be awarded.
     *
     * A driver and a passenger are asked for the same three: both NID pages
     * prove who they are and the photo puts a face to the account. That is
     * why there is one verification endpoint and not one per role.
     *
     * An empty list means the role is not subject to verification, which is
     * why an admin never picks up a badge.
     *
     * @return array<int, self>
     */
    public static function requiredFor(Role $role): array
    {
        return match ($role) {
            Role::Driver, Role::Passenger => [
                self::NidFront,
                self::NidBack,
                self::ProfilePhoto,
            ],
            Role::Admin => [],
        };
    }

    /**
     * Everything a role may upload, which for a driver is the required set
     * plus the licence and the vehicle registration paper.
     *
     * Those two are stored and reviewed like any other document, but the
     * badge does not wait on them - a driver is badged on identity alone.
     * Moving a case into requiredFor() is what makes the badge depend on it.
     *
     * @return array<int, self>
     */
    public static function acceptableFor(Role $role): array
    {
        return match ($role) {
            Role::Driver => [
                ...self::requiredFor($role),
                self::DrivingLicence,
                self::VehicleRegistration,
            ],
            Role::Passenger, Role::Admin => self::requiredFor($role),
        };
    }

    /**
     * The names of the documents a role must submit.
     *
     * @return array<int, string>
     */
    public static function requiredNamesFor(Role $role): array
    {
        return array_column(self::requiredFor($role), 'name');
    }

    /**
     * The request field this document is uploaded under, such as "nid_front".
     */
    public function field(): string
    {
        return Str::snake($this->name);
    }

    /**
     * A human readable name for validation messages and clients.
     */
    public function label(): string
    {
        return match ($this) {
            self::NidFront => 'NID front page',
            self::NidBack => 'NID back page',
            self::DrivingLicence => 'driving licence',
            self::VehicleRegistration => 'vehicle registration paper',
            self::ProfilePhoto => 'profile photo',
        };
    }

    /**
     * Whether the upload must be an image rather than a scan or PDF.
     */
    public function isPhoto(): bool
    {
        return $this === self::ProfilePhoto;
    }
}

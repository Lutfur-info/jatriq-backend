<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\enum\Gender;
use App\enum\Role;
use App\enum\VerificationStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $full_name
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string $dial_code
 * @property string $msisdn
 * @property Carbon|null $msisdn_verified_at
 * @property Gender $gender
 * @property string|null $date_of_birth
 * @property Role $role
 * @property bool $is_active
 * @property VerificationStatus $verification_status
 * @property Carbon|null $verified_at
 * @property numeric-string|null $rating_average
 * @property int $ratings_count
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, UserDocument> $documents
 * @property-read Collection<int, EmergencyContact> $emergencyContacts
 * @property-read Vehicle|null $vehicle
 */
#[Fillable(['first_name', 'last_name', 'email', 'dial_code', 'msisdn', 'gender', 'date_of_birth', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Whether this account may open the admin panel.
     *
     * The only gate on the panel, and the reason the reviewing endpoints no
     * longer exist in the API: an admin reviews in Filament, and a driver or
     * passenger has no business there at all. Filament's login page calls
     * this before the session is issued, so a rider is refused at the form
     * rather than let in and shown a 403.
     *
     * A suspended admin is refused too - `is_active` is the switch that takes
     * an account out of service, and it should take away the panel with it.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && $this->is_active;
    }

    /**
     * What the panel calls the signed-in reviewer.
     *
     * There is no `name` column - a user has a first and last name - so
     * Filament has to be told, or it reads null and fails rendering the
     * account menu.
     */
    public function getFilamentName(): string
    {
        return $this->full_name;
    }

    /**
     * Column defaults the database applies, mirrored so a model that has not
     * been read back still answers honestly.
     *
     * Only `ratings_count`: a freshly created user has no ratings and the
     * count is **0**, where null would make `RiderResource` report "no
     * count" on a driver who simply has not been rated yet. The average
     * stays null on purpose - that is what "nobody has rated him" means.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ratings_count' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'msisdn_verified_at' => 'datetime',
            'password' => 'hashed',
            'gender' => Gender::class,
            'role' => Role::class,
            'is_active' => 'boolean',
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
            'rating_average' => 'decimal:2',
            'ratings_count' => 'integer',
        ];
    }

    /**
     * The identity documents the user has submitted.
     *
     * @return HasMany<UserDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(UserDocument::class);
    }

    /**
     * The people to call on the user's behalf in an emergency.
     *
     * @return HasMany<EmergencyContact, $this>
     */
    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    /**
     * The seats the user has booked on other people's rides.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * The rides a driver is offering.
     *
     * @return HasMany<Ride, $this>
     */
    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    /**
     * The trips passengers have scored on this driver's rides.
     *
     * The relation the average is taken over. It reaches through `rides`,
     * because a rating sits on the booking and the booking belongs to the
     * ride this driver published - there is no direct line from a driver to
     * a score, which is the point: only somebody who actually travelled can
     * leave one.
     *
     * @return HasManyThrough<Booking, Ride, $this>
     */
    public function receivedRatings(): HasManyThrough
    {
        return $this->hasManyThrough(Booking::class, Ride::class)->rated();
    }

    /**
     * Whether anybody has scored this driver yet.
     *
     * `ratings_count` rather than `rating_average`, because a null average
     * and a zero count say the same thing and the count is the honest one.
     */
    public function hasRating(): bool
    {
        return $this->ratings_count > 0;
    }

    /**
     * The drivers this passenger wants to ride with again.
     *
     * Both ends are `users`, because a driver and a passenger are the same
     * table with a different `role`. Only a Passenger ever has any - the
     * endpoint that writes them is gated on the role - and only a Driver is
     * ever in the list.
     *
     * The pivot carries nothing but its timestamps: a favourite *is* the
     * fact that the row exists. `withTimestamps()` is what lets the list be
     * ordered most recently favourited first.
     *
     * @return BelongsToMany<User, $this>
     */
    public function favouriteDrivers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favourite_drivers', 'user_id', 'driver_id')
            ->withTimestamps();
    }

    /**
     * The passengers who have favourited this driver.
     *
     * The other side of the same pivot. Nothing reads it yet - a driver is
     * deliberately not told who has favourited them, which would be a
     * different privacy decision - but the relation is what makes a count
     * possible without a second table.
     *
     * @return BelongsToMany<User, $this>
     */
    public function favouritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favourite_drivers', 'driver_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * The vehicle a driver drives, of which there is at most one.
     *
     * Only a driver ever has one; the relation is empty for everybody else
     * because the route that writes it is gated on the role.
     *
     * @return HasOne<Vehicle, $this>
     */
    public function vehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class);
    }

    /**
     * The user's first and last name.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * Limit the query to users holding the given role.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    #[Scope]
    protected function ofRole(Builder $query, Role $role): Builder
    {
        return $query->where('role', $role->name);
    }

    /**
     * Determine whether the user has confirmed ownership of their msisdn.
     */
    public function hasVerifiedMsisdn(): bool
    {
        return $this->msisdn_verified_at !== null;
    }

    /**
     * Confirm the user's msisdn and open the account for use.
     */
    public function markMsisdnAsVerified(): bool
    {
        return $this->forceFill([
            'msisdn_verified_at' => $this->freshTimestamp(),
            'is_active' => true,
        ])->save();
    }

    /**
     * Replace the user's password and cut every session loose from the old one.
     *
     * The password is hashed by the cast. Issued tokens are revoked and the
     * remember token is rotated because a reset is what somebody locked out
     * of their account does, and whoever locked them out must not keep the
     * access they already hold.
     */
    public function resetPassword(string $password): bool
    {
        $this->tokens()->delete();

        return $this->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();
    }

    /**
     * Limit the query to users whose badge sits in the given state.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    #[Scope]
    protected function withVerificationStatus(Builder $query, VerificationStatus $status): Builder
    {
        return $query->where('verification_status', $status->name);
    }

    /**
     * Determine whether the user's identity documents have been accepted.
     *
     * This is the verification badge, and is unrelated to msisdn ownership.
     */
    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::Verified;
    }

    /**
     * Determine whether the user holds the given role.
     */
    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin);
    }

    public function isDriver(): bool
    {
        return $this->hasRole(Role::Driver);
    }

    public function isPassenger(): bool
    {
        return $this->hasRole(Role::Passenger);
    }
}

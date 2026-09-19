<?php

namespace App\Models;

use Database\Factories\EmergencyContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Someone a driver or passenger wants called if a ride goes wrong.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $relation
 * @property string $dial_code
 * @property string $msisdn
 * @property bool $is_primary
 * @property string $international_msisdn
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['name', 'relation', 'dial_code', 'msisdn'])]
class EmergencyContact extends Model
{
    /** @use HasFactory<EmergencyContactFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * The user who listed this contact.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The number in dialable form, with the local trunk zero dropped.
     *
     * @return Attribute<string, never>
     */
    protected function internationalMsisdn(): Attribute
    {
        return Attribute::get(fn (): string => $this->dial_code.ltrim($this->msisdn, '0'));
    }
}

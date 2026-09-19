<?php

namespace App\Models;

use Database\Factories\DistrictFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One of the country's 64 districts, picked rather than typed.
 *
 * It exists for one reason: a stop used to carry a free-text district, so
 * "Cumilla" and "Comilla" were two different places as far as anything could
 * tell, and an admin had to remember how the last town was spelled. Now the
 * spelling is a row and a stop points at it.
 *
 * Nothing about a journey touches this. Matching runs on a stop's `sequence`
 * along a corridor, a ride carries no district at all, and the label exists
 * only so two same-named towns read apart in a picker.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'is_active',
])]
class District extends Model
{
    /** @use HasFactory<DistrictFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The towns filed under this district.
     *
     * @return HasMany<Stop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(Stop::class);
    }
}

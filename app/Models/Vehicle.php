<?php

namespace App\Models;

use App\enum\CabinClass;
use App\enum\VehicleModel;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The one vehicle a driver drives.
 *
 * The driving licence and registration paper behind it are not stored here:
 * they are UserDocuments like every other scan, so they inherit the private
 * disk and the admin review the badge is built on.
 *
 * @property int $id
 * @property int $user_id
 * @property string $registration_number
 * @property VehicleModel $model
 * @property CabinClass $cabin_class
 * @property int $seats
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['registration_number', 'model', 'cabin_class', 'seats'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'model' => VehicleModel::class,
            'cabin_class' => CabinClass::class,
            'seats' => 'integer',
        ];
    }

    /**
     * The driver who registered it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

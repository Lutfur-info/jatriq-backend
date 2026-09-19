<?php

namespace App\Models;

use App\enum\DocumentStatus;
use App\enum\DocumentType;
use Database\Factories\UserDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An identity document submitted by a driver or passenger.
 *
 * @property int $id
 * @property int $user_id
 * @property DocumentType $type
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property DocumentStatus $status
 * @property string|null $rejection_reason
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read User|null $reviewer
 */
#[Fillable(['type', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class UserDocument extends Model
{
    /** @use HasFactory<UserDocumentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'size' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The user the document belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The administrator who last reviewed the document.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Limit the query to documents in the given review state.
     *
     * @param  Builder<UserDocument>  $query
     * @return Builder<UserDocument>
     */
    #[Scope]
    protected function withStatus(Builder $query, DocumentStatus $status): Builder
    {
        return $query->where('status', $status->name);
    }

    /**
     * Determine whether the document has been accepted by a reviewer.
     */
    public function isApproved(): bool
    {
        return $this->status === DocumentStatus::Approved;
    }

    /**
     * Determine whether the document was turned down and must be replaced.
     */
    public function isRejected(): bool
    {
        return $this->status === DocumentStatus::Rejected;
    }
}

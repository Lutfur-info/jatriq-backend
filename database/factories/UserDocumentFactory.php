<?php

namespace Database\Factories;

use App\enum\DocumentStatus;
use App\enum\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserDocument>
 */
class UserDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(DocumentType::cases());

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'disk' => config('verification.disk'),
            'path' => 'verifications/'.fake()->randomNumber(4).'/'.$type->field().'-'.Str::ulid().'.jpg',
            'original_name' => $type->field().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(50_000, 2_000_000),
            'status' => DocumentStatus::Pending,
        ];
    }

    /**
     * Indicate the document type being submitted.
     */
    public function ofType(DocumentType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'original_name' => $type->field().'.jpg',
        ]);
    }

    /**
     * Indicate that a reviewer has accepted the document.
     */
    public function approved(?User $reviewer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Approved,
            'reviewed_by' => $reviewer?->getKey() ?? User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Indicate that a reviewer has turned the document down.
     */
    public function rejected(?User $reviewer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Rejected,
            'rejection_reason' => 'The photo is too blurry to read.',
            'reviewed_by' => $reviewer?->getKey() ?? User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }
}

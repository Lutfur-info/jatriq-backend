<?php

namespace App\Http\Requests\Api\Favourite;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A passenger keeping a driver.
 *
 * The driver's id is the whole request - a favourite has no payload, it is
 * the fact that the row exists.
 *
 * `exists` checks the id is a real account and nothing more. Whether that
 * account is a **driver** is `FavouriteDriverService`'s call, so the rule
 * lives in one place for a future console command or admin action rather
 * than being spelled out in a `where()` here as well.
 */
class StoreFavouriteDriverRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * The driver being kept.
     */
    public function driver(): User
    {
        return User::query()->findOrFail($this->integer('driver_id'));
    }

    /**
     * Messages worth spelling out.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'driver_id.exists' => 'That account does not exist.',
        ];
    }
}

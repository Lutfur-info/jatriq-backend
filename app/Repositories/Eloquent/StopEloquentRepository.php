<?php

namespace App\Repositories\Eloquent;

use App\Models\Stop;
use App\Repositories\Contracts\StopRepository;

class StopEloquentRepository implements StopRepository
{
    /**
     * {@inheritDoc}
     */
    public function find(int $id): ?Stop
    {
        return Stop::query()->find($id);
    }
}

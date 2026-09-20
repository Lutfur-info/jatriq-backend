<?php

namespace App\Providers;

use App\Repositories\Contracts\BookingRepository;
use App\Repositories\Contracts\EmergencyContactRepository;
use App\Repositories\Contracts\FavouriteDriverRepository;
use App\Repositories\Contracts\NotificationRepository;
use App\Repositories\Contracts\RideRepository;
use App\Repositories\Contracts\StopRepository;
use App\Repositories\Contracts\TravelRouteRepository;
use App\Repositories\Contracts\UserDocumentRepository;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Contracts\VehicleRepository;
use App\Repositories\Eloquent\BookingEloquentRepository;
use App\Repositories\Eloquent\EmergencyContactEloquentRepository;
use App\Repositories\Eloquent\FavouriteDriverEloquentRepository;
use App\Repositories\Eloquent\NotificationEloquentRepository;
use App\Repositories\Eloquent\RideEloquentRepository;
use App\Repositories\Eloquent\StopEloquentRepository;
use App\Repositories\Eloquent\TravelRouteEloquentRepository;
use App\Repositories\Eloquent\UserDocumentEloquentRepository;
use App\Repositories\Eloquent\UserEloquentRepository;
use App\Repositories\Eloquent\VehicleEloquentRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Binds every repository contract to its Eloquent implementation.
 *
 * Services type hint the contract, so a test can swap in a fake and a future
 * read model can be introduced without touching a service.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * The contract to implementation map applied to the container.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepository::class => UserEloquentRepository::class,
        UserDocumentRepository::class => UserDocumentEloquentRepository::class,
        EmergencyContactRepository::class => EmergencyContactEloquentRepository::class,
        VehicleRepository::class => VehicleEloquentRepository::class,
        RideRepository::class => RideEloquentRepository::class,
        BookingRepository::class => BookingEloquentRepository::class,
        FavouriteDriverRepository::class => FavouriteDriverEloquentRepository::class,
        TravelRouteRepository::class => TravelRouteEloquentRepository::class,
        StopRepository::class => StopEloquentRepository::class,
        NotificationRepository::class => NotificationEloquentRepository::class,
    ];
}

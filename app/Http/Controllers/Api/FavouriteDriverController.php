<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Favourite\StoreFavouriteDriverRequest;
use App\Http\Resources\FavouriteDriverResource;
use App\Models\User;
use App\Services\FavouriteDriverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The drivers a passenger wants to ride with again.
 *
 * A private note she keeps, and nothing more: a favourite reserves no seat,
 * jumps no queue, and is never shown to the driver. That is what makes it a
 * `role:Passenger` list rather than something both sides share.
 *
 * Both writes are **idempotent**. Favouriting a driver already kept answers
 * `200` where the first answered `201` - the same shape a repeat booking
 * uses - and un-favouriting one that was never there is not an error, so a
 * client whose response went missing simply repeats the tap.
 *
 * Keeping one needs the identity badge, like everything else a passenger
 * *does*; reading the list does not, the same bargain her bookings make.
 */
class FavouriteDriverController extends Controller
{
    public function __construct(private FavouriteDriverService $favourites) {}

    /**
     * Her favourite drivers, most recently kept first.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $passenger */
        $passenger = $request->user();

        $drivers = $this->favourites->listFor($passenger);

        return response()->json([
            'message' => $drivers->isEmpty()
                ? 'No favourite drivers yet.'
                : 'Your favourite drivers.',
            'data' => [
                'drivers' => FavouriteDriverResource::collection($drivers),
            ],
        ]);
    }

    /**
     * Keep a driver.
     */
    public function store(StoreFavouriteDriverRequest $request): JsonResponse
    {
        /** @var User $passenger */
        $passenger = $request->user();

        $driver = $request->driver();
        $added = $this->favourites->add($passenger, $driver);

        return response()->json([
            'message' => $added
                ? 'Driver added to your favourites.'
                : 'That driver is already in your favourites.',
            'data' => [
                'driver' => new FavouriteDriverResource($driver->loadMissing('vehicle')),
            ],
        ], $added ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * Drop a driver from the list.
     *
     * The id is a plain user, not a favourite row: a passenger holds at most
     * one favourite per driver, so the driver *is* the identifier and a
     * client never has to remember a pivot id.
     */
    public function destroy(Request $request, User $driver): JsonResponse
    {
        /** @var User $passenger */
        $passenger = $request->user();

        $this->favourites->remove($passenger, $driver);

        return response()->json([
            'message' => 'Driver removed from your favourites.',
        ]);
    }
}

---
paths:
  - 'app/Http/Controllers/Api/FavouriteDriverController.php'
  - 'app/Services/FavouriteDriverService.php'
  - 'app/Repositories/Contracts/FavouriteDriverRepository.php'
  - 'app/Repositories/Eloquent/FavouriteDriverEloquentRepository.php'
  - 'app/Http/Resources/FavouriteDriverResource.php'
  - 'app/Http/Resources/RiderResource.php'
---

# Favourite drivers

## A favourite is a private note, and it grants nothing
It reserves no seat, jumps no queue, changes no search, and **the driver is never told**. That is what keeps it a `role:Passenger` list rather than something both sides share, and why there is no locking here and no seat arithmetic to protect. `User::favouritedBy()` is the other side of the pivot and nothing reads it - surfacing "twelve passengers kept you" is its own privacy decision, not a field to add.

If a favourite ever *does* grant something - priority on a full ride, a filter on the board - it stops being a private note and every rule below has to be re-argued.

## No model, because a favourite has no payload
`favourite_drivers` is a pivot with two `users` foreign keys and its timestamps. `User::favouriteDrivers()` is a `belongsToMany` over it; there is nothing to store but the fact that the row exists. `withTimestamps()` is load bearing - it is what `favourited_at` reads and what the list is ordered by.

Both columns `cascadeOnDelete`: the row is meaningless once either account is gone, and nothing hangs off it.

## Both writes are idempotent, and that is the contract
`syncWithoutDetaching` on add and `detach` on remove, so:

- favouriting a driver already kept answers `200` where the first answered `201` (the shape a repeat booking uses), and **does not move the pivot timestamp** - otherwise tapping a filled heart silently reorders her list;
- removing one she never kept is not an error.

This is what makes a lost response safe for the app to repeat, and it is what the optimistic heart in `FavouriteDriverController` depends on. Do not "tidy" either into a uniqueness check that 422s.

## Only a Driver can be favourited, and the service says so
`role:Passenger` says who may call; nothing about the route says who may be *named in the body*. `FavouriteDriverService::refuseNonDriver()` is the check - a 422 keyed on `driver_id`, like every other refusal in this API, because the row exists and is simply not something to favourite. `StoreFavouriteDriverRequest` only checks the id is a real account, so a future console command or admin action inherits the role rule rather than restating it in a `where()`.

## Keeping needs the badge; looking does not
`POST` and `DELETE` take `verified.identity`, `GET` does not - the same bargain `GET /api/bookings` and `GET /driver/rides` make. A passenger whose badge lapses can still see who she kept.

## No contact details, in either direction
`RiderResource` (name + badge) and `FavouriteDriverResource` (the same four fields plus the car) are both deliberately **not** `UserResource`, which carries email, msisdn and date of birth. Favouriting must not become a way to collect phone numbers by tapping a heart.

Whether a *confirmed booking* should unlock a number so the two can arrange a pickup is a real product question and still unanswered. If it is ever answered yes, it belongs on the booking - not here, where a passenger can keep a driver she has never met.

## The driver on a booking is what makes the feature usable
`BookingResource` names the driver on **her own list** (`ride.user`, eager loaded in `BookingRepository::forUser()`). Without it there is nowhere in the app to favourite from: `RideResource` deliberately carries no driver, because `GET /api/rides` is public and a name there is a name on the open internet. `FavouriteDriverTest` pins both halves - the driver appears on her booking, and never on the board.

Symmetrically, the driver's queue names the passenger and omits the driver. Each end sees the other, never itself.

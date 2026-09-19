---
paths:
  - 'app/Http/Controllers/Api/Driver/RideController.php'
  - 'app/Services/RideService.php'
  - 'app/Models/Ride.php'
  - 'app/Repositories/Contracts/RideRepository.php'
  - 'app/Repositories/Eloquent/RideEloquentRepository.php'
---

# Rides

## A ride is always in the driver's registered vehicle, and never names one
`POST /api/driver/rides` carries no `vehicle_id`. `RideService::offer()` resolves the vehicle through `VehicleRepository::forUser()` and refuses a driver who has not registered one - a **422 keyed on `vehicle`**, not a 403: the driver is allowed on the endpoint, they are simply not ready, and one error shape keeps the client parsing one thing. The repository associates `user_id` and `vehicle_id` itself, and neither is in the model's `#[Fillable]`, so no body can post a ride into somebody else's name or car.

Because the vehicle is resolved twice - once in `StoreRideRequest` for the seat ceiling, once in the service - the request falls back to `config('vehicles.seats.max')` when there is no vehicle. That fallback is never reached in practice: the service refuses the request straight after validation.

## `seats_offered` is passenger seats, capped by the vehicle
Bounded by `1` and the vehicle's own `seats`, which already excludes the driver (see `vehicles.md`). A driver may offer fewer than the car holds - carrying cargo or family - so this is its own column rather than a read of `vehicle.seats`. Never add the driver's seat back into either number.

## Editing closes the moment anybody books
`PATCH /api/driver/rides/{ride}` is partial, like an emergency contact edit. `RideService::change()` refuses it as soon as `BookingRepository::anyOn()` is true - a **422 keyed on `ride`**, matching the missing-vehicle refusal rather than introducing a 409. The reasoning is the passenger's: they agreed to that route, that departure and that fare, so the ride stops being editable rather than moving under them. There is no cancellation, so this is permanent; the driver's remaining move is to publish another ride.

Somebody else's ride is a **404**, not a 403, exactly as an emergency contact is - whether an id exists is not the caller's business when the row is not theirs.

`UpdateRideRequest` reads the fields it was *not* sent from the ride, which is why moving only the destination is still checked against the real start point. Its seat ceiling comes from **the ride's own vehicle**, not the driver's current one.

The check and the write are **one locked transaction** (`RideRepository::lockForWrite()`, `attempts: 3`), for the same reason booking is: without the lock a booking landing between "has anybody booked?" and the update would be silently edited underneath, which is the exact thing this rule exists to prevent. See `bookings.md`.

## Publishing and editing need the identity badge; listing does not
`verified.identity` middleware guards `POST` and `PATCH`: a passenger is getting into a stranger's car, so the documents behind the trip have to have been reviewed. Three deliberate exclusions:

- **`GET /driver/rides` is open**, so a driver whose badge lapses can still see what they already have out there.
- **The vehicle and verification endpoints are open**, or earning the badge would depend on already holding it.
- **The badge is not msisdn ownership.** Login already refuses an unverified number before it issues a token, and `is_active` is separate again. See `services.md` on not conflating the four flags.

## Both ends are stops, and the ride keeps a snapshot of each
`POST /driver/rides` sends `origin_stop_id` and `destination_stop_id` - never a label or a place id, and there is no coordinate left in the system to send. A ride is only searchable once it sits at a known position on a known corridor, which hand-typed text cannot give it. See `routes.md`.

`TravelRouteService::place()` then resolves the corridor and both sequences and **copies the stop's name and place id onto the ride**. That copy is deliberate: renaming or correcting a stop must not rewrite a trip a passenger already agreed to, and it is what lets `BookingResource` read `origin_name` straight off the ride. `travel_route_id`, both `*_stop_id` and both `*_sequence` columns are guarded - `RideEloquentRepository::place()` assigns them by hand, exactly as `user_id` and `vehicle_id` are associated, so no body can claim a corridor its two stops do not share.

Coordinates stay `decimal(10,8)` / `decimal(11,8)` rather than a spatial POINT: the suite runs on **sqlite in memory** (`phpunit.xml`), which has no spatial type, and matching is on integer sequences, never on distance. If a proximity search ever arrives, that is the point to reconsider - and a MySQL-only spatial index would mean the suite can no longer run on sqlite.

## Moving one end re-places the pair
`RideService::replace()` re-resolves the corridor whenever an edit carries either stop, reading the end it was not sent off the ride - because moving one end can put the trip on a different road. An edit touching neither leaves the placement alone, so a fare correction re-derives nothing. A ride published before corridors existed has null stops, so an edit naming only one end is refused (**422 on `origin_stop_id`**): both have to be sent to bring it onto a route.

## What the API deliberately does not decide
- **No minimum trip length.** Only the same stop at both ends is refused - `different:origin_stop_id` while both are in the body, and `startsWhereItEnds()` on a partial edit that sends one. Two neighbouring towns on a corridor are a legitimate hop, so do not invent a threshold in validation.
- **No status column and no lifecycle.** There is no cancel, no complete, no `RideStatus`. The list filters on `departs_at >= now()` instead, so a ride simply stops being upcoming. Adding cancellation means a status column, filtering it out of every read, and deciding what happens to bookings - not a boolean bolted on.
- **No fare arithmetic.** `seat_price` is what one seat costs, in BDT, as a fixed-point string; there is no currency column because the platform is Bangladesh only. Nothing multiplies it by seats yet.
- **No fare for a partial leg.** `seat_price` is what one seat costs whatever distance the passenger actually travels, so somebody joining at Laksam pays the same as somebody who boarded at Sonaimuri. Charging by leg needs a fare table and a fare snapshot on the booking; it is not a calculation to slip into `BookingResource`.
- **The corridor a ride is filed under can be ambiguous, and that is fine.** Two towns on the shared stretch out of Dhaka belong to several corridors; `TravelRouteService::mostDirect()` picks the one they are closest together on, breaking ties on route id. The choice only decides which corridor *name* the ride is displayed under - the passengers it matches are identical either way, because the shared stretch carries the same towns on every corridor using it.

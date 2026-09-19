---
paths:
  - 'app/Http/Controllers/Api/BookingController.php'
  - 'app/Http/Controllers/Api/Driver/RideBookingController.php'
  - 'app/enum/BookingStatus.php'
  - 'app/Services/BookingService.php'
  - 'app/Models/Booking.php'
  - 'app/Repositories/Contracts/BookingRepository.php'
  - 'app/Repositories/Eloquent/BookingEloquentRepository.php'
---

# Bookings

## `GET /api/rides` is public - the only unauthenticated read in the API
The home screen shows upcoming rides to somebody who has not signed up yet, and signing in is what booking a seat asks for. It sits **outside** the `auth:sanctum` group in `routes/api.php`, throttled by IP (`throttle:rides-browse`) because there is no account to key a limit to.

Two things follow. It must never carry anything about the driver - that would publish a name and number to the open internet, and it is why the driver is absent from `RideResource` in the first place. And it does expose each ride's **vehicle registration number** to anonymous callers; a plate is visible on the street, so that was judged acceptable, but it is the field to reconsider first if that changes.

Upcoming and **not yet full**, soonest first. "Not full" is a correlated subquery on `bookings.seats` rather than a `HAVING` on the `withSum` alias, which would need a `GROUP BY` over every selected column. It counts `Pending` and `Confirmed` alike - a request holds its seat - and the two case names are written out because `whereRaw` takes a literal. It selects the sum too, so `seats_available` is honest - book into a car the list said was empty and the whole feature is pointless.

Open like her own bookings: browsing is how somebody whose documents are still in review finds out what the badge is for. Taking a seat is what needs it.

No pagination and no filters yet. The list is every bookable ride in the country, which is fine at this size and will not stay fine - that is the next thing to change here, and it changes the response shape.

## `GET /api/bookings` is hers alone, and stays open
Every booking the passenger holds, **past trips included** - a booking is the record that she travelled, not only that she is about to. `BookingRepository::forUser()` orders upcoming trips soonest-first and then the trips already taken latest-first, joining `rides` to sort on the departure (hence the `select('bookings.*')`, or the two tables' `id` and `created_at` collide).

It does **not** take `verified.identity`: it is the booking that needs the badge, not the looking, so a passenger whose badge lapses can still see what she has already paid for. Same bargain as `GET /driver/rides`.

The query eager-loads `ride.vehicle`, because `BookingResource` reads the plate, both place names and the departure off the ride. It does not select the ride's booked-seat sum, because the flat shape does not report inventory - but note that `RideResource` reports `seats_booked` as `0` when that aggregate was not selected, so **any query that renders a `RideResource` still has to select it** or it will tell the client the car is empty.

## `BookingResource` is flat, and is what a booking reads as on screen
Seats, `status` and `status_label`, `decided_at`, `total_amount`, `vehicle_number`, `origin_name`, `destination_name`, the departure, and when it was asked for. The label ships beside the case name so a screen never spells a state out of an enum, and the server can reword one without a client release. Deliberately *not* the ride's own shape - place ids, seat inventory - which lives in `RideResource`; the create response carries one of those alongside the booking for the updated availability.

It needs `ride` and `ride.vehicle` loaded. Every caller does: the list eager-loads them, and `BookingEloquentRepository::put()` attaches the ride it already holds rather than reading it back.

## `total_amount` is `bcmul`, never float arithmetic
`Booking::totalAmount()` multiplies the ride's `seat_price` by the seats in fixed point, so 650.50 x 2 is exactly `1301.00`. `Ride::$seat_price` is typed `numeric-string` for that reason - it is what makes the `bcmul` call type-safe rather than a cast.

Derived rather than stored, and safe because a ride with a booking can no longer change its price, so the total can never drift from what was agreed. **If ride editing ever opens up after booking, this and the missing fare snapshot both stop being safe.**

## Booking is the API's one `role:Passenger` group
`POST /api/rides/{ride}/bookings`, and the seat count is the entire body - the route, the departure and the fare are the driver's. A driver offering seats and a passenger taking them are different enough to gate apart, and the gate is also what stops a driver booking their own ride: no self-booking check exists anywhere, because the role makes it unreachable. If booking ever opens to drivers, that check has to be written.

It also needs `verified.identity`: the driver is letting a stranger into their car. See `rides.md` for the mirror-image rule and for what is deliberately left open.

## A seat is asked for, not taken
Every booking arrives **`Pending`** and waits for the driver, who answers with `PATCH /api/driver/bookings/{booking}` (2026-09-19). The driver is letting a stranger into their car, so the confirmation is the point of the whole feature - `BookingStatus` is a pure enum persisted by case name, like `Role`.

**A pending request holds its seats exactly as a confirmed one does.** This is the load-bearing decision. Counting only confirmed seats would let a driver confirm more requests than the car has, which moves the oversell from the booking to the confirmation rather than removing it. **Declining is the only thing that gives a seat back.**

So every seat sum in the system reads `BookingStatus::holdingNames()` and never the raw column:

- `BookingRepository::seatsTakenOn()` and `anyOn()` go through the model's `holding()` scope.
- `RideEloquentRepository` runs every `withSum` through `holdingSeats()`, and the "not full" correlated subquery spells the two case names out because `whereRaw` takes a literal.
- The panel does the same through `RideResource::seatsBooked()` and the filter in `RidesTable`.

A new case is added to `holdingNames()` **and** to the two raw SQL strings, or the board starts selling seats somebody is holding.

`anyOn()` excluding declines is what reopens a ride to editing once every request has been turned down: nobody is travelling, so there are no terms left to protect. A *pending* request still closes it - the passenger asked for that route, that departure and that fare, and the driver answering is not what settles them.

## The driver answers; nobody else does
`PATCH /api/driver/bookings/{booking}` is `role:Driver` + `verified.identity`, and somebody else's ride is a **404**, as a ride itself is. `GET /api/driver/rides/{ride}/bookings` is deliberately left open to a lapsed badge, the same bargain `GET /driver/rides` makes - reading who is waiting is not what puts a stranger in the car.

`DecideBookingRequest` allow-lists `BookingStatus::decidable()`, so `Pending` is refused: that is where a request arrives, and only the passenger asking again puts one back. Shaped after `DocumentStatus::reviewable()`.

**Re-confirming a declined request re-checks the seats under the ride's lock.** Declining put them back on sale and somebody else may have taken them, so a confirmation that no longer fits is refused rather than overselling. Confirming a request that is already holding needs no room - it is already counted.

The panel shows the decision and nothing more: an admin reads the queue on the ride's passenger list but does not answer it. If admin override is ever wanted, it goes through `BookingService::decide()`, never a status column written by hand.

## What a driver sees about a passenger, and what they do not
`RiderResource` - name and verification badge, and **no contact details**. It is deliberately not `UserResource`, which carries email, msisdn and date of birth. Whether a *confirmed* booking should unlock a phone number so the two can arrange a pickup is a real product question and a privacy decision; nothing in the API answers it yet, and it is not a field to bolt on.

It is attached with `whenLoaded('user')`, so it appears in the driver's queue and nowhere else - a passenger reading her own list does not need to be told who she is.

## One booking per passenger per ride, and `seats` is a running total
`unique(['ride_id', 'user_id'])`. Booking again **tops up** the same row rather than appending, so `seats` is a total and not a single act of booking - a repeat answers `200` where the first answered `201` (`Booking::$wasRecentlyCreated` is what tells them apart). The unique key is also the backstop against two simultaneous requests inserting two rows for one passenger, which would corrupt every seat sum.

Two rules follow from the driver's answer, both in `BookingRepository::put()`:

- **A top-up goes back to `Pending` and clears `decided_at`.** The driver agreed to two seats and is now being asked for three, which is a new question.
- **A request the driver declined starts over rather than topping up.** Those seats went back on the market, so adding to them would invent seats nobody is holding.

## Counting seats and taking them is one locked step
`BookingService::book()` opens a transaction and calls `RideRepository::lockForWrite()` before it sums anything. Without the row lock two passengers reaching for the last seat both read "one free" and both succeed. **Any read-then-write on a ride has to take that same lock** - the edit guard in `RideService::change()` does, which is what makes the two serialise: either the edit lands before any booking, or the booking wins and the edit is refused. A seat check outside that transaction is not a check.

Both transactions pass `attempts: 3`. Laravel re-runs the closure only on a **concurrency error** - a deadlock or a lock wait timeout - so a request that queued too long retries instead of returning a 500. A `ValidationException` is not a concurrency error and is never retried, which is what keeps a refusal a refusal.

A refusal throws, so nothing is written: the seat rules are checked inside the transaction and a failure rolls it back whole. `tests/Feature/RideBookingTest.php` proves that by binding a `BookingRepository` that writes and then throws, and asserting nothing survives - the transaction is load-bearing, not decorative. Removing the `DB::transaction` call makes that test fail.

Note that `lockForUpdate()` is a no-op on sqlite, which is what the test suite runs on, so **the oversell race itself cannot be caught by a test here** - only the rollback can. It is MySQL that enforces the isolation.

## The fare is not snapshotted on a booking, on purpose
There is no `seat_price` column on `bookings`. A ride cannot be edited once it has a booking, so `ride.seat_price` can never move underneath one - the agreed price is always the ride's own, and a copy would only be a second thing to keep in step. **If ride editing ever opens up after booking, this stops being true** and a snapshot becomes necessary.

## The driver is deliberately not in the response
A booking carries its ride and that ride's vehicle, but nothing about the person driving. `UserResource` is "the only shape in which a user is ever returned" and it holds email, msisdn and date of birth - far more than a passenger should receive about a driver. Surfacing "who am I riding with" needs a **new minimal shape** (name, badge, maybe a masked number) and is a privacy decision, not a field to bolt on.

## What does not exist yet
**No cancellation.** `BookingStatus` exists now, but it is the *driver's* answer and not a passenger's way out - she cannot withdraw a request, and a confirmed seat cannot be given back by either side except by the driver declining it after the fact. A real cancellation still means deciding what happens to a ride whose last passenger leaves.

**Nobody is told anything.** A driver is not notified that somebody is waiting, and a passenger is not notified when her request is answered - she finds out by opening the app. That is the most conspicuous gap in this feature.

**The driver app has no queue screen yet.** The endpoints and `BookingStatus` are carried through `apps/lib`, and a passenger sees the answer on her bookings, but nothing in the Flutter app lists pending requests or calls `PATCH /driver/bookings/{booking}`.

`seats_offered` on a ride is what was *offered*; the seats still free are `seats_offered` minus the **holding** bookings' seats, which `RideResource` exposes as `seats_available`.

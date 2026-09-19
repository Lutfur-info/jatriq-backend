---
paths:
  - 'app/Models/TravelRoute.php'
  - 'app/Models/Stop.php'
  - 'app/Services/TravelRouteService.php'
  - 'app/Http/Controllers/Api/TravelRouteController.php'
  - 'app/Http/Requests/Api/Ride/SearchRidesRequest.php'
  - 'app/Repositories/Contracts/TravelRouteRepository.php'
  - 'app/Repositories/Eloquent/TravelRouteEloquentRepository.php'
  - 'app/Repositories/Contracts/StopRepository.php'
  - 'app/Repositories/Eloquent/StopEloquentRepository.php'
  - 'database/seeders/TravelRouteSeeder.php'
---

# Routes and stops

## Sequence runs outbound from Dhaka, on every corridor
`travel_route_stop.sequence` is counted one way only: Dhaka is the lowest number on every route. **There is no direction column anywhere.** A trip toward Dhaka is one whose `destination_sequence` is below its `origin_sequence`, and comparing the two is the entire representation of direction - in the ride, in the leg, and in the search. Seed a new corridor the wrong way round and every ride on it matches backwards.

## Placement arithmetic lives in TravelRouteService
`placementsOn()`, `putStopOn()`, `moveStopOn()`, `takeStopOff()` and `renumber()` are the only things that write `travel_route_stop` outside the seeder, and `TravelRouteRepository` is the only thing that touches Eloquent for it. A gap is halved for an insert (`intdiv`) and extended by ten past the end; `null` means the gap is full, which callers must surface rather than round away. `sequenceFor()` also takes `exact:{n}` - a number an admin typed - and applies the same free-and-in-range check to it.

`renumber()` is the one operation that re-spaces a road, and it rewrites the published rides' copied sequences in the same transaction. Re-spacing the pivot without that leaves every ride on the corridor pointing at positions it no longer has: still listed, missing from every search. That pairing is the rule, wherever the re-spacing happens.

## Sequences are written out, spaced in tens, and renumbering is the trap
`TravelRouteSeeder::ROUTES` maps `town => sequence` explicitly — **never derived from the position in the list**. It was derived once, and that was a latent bug: inserting a town shifted every number after it, and because **a ride copies both sequences onto itself when it is published** and nothing goes back to correct them, every ride already on that corridor would have been silently mis-positioned.

So the tens are the working room. Adding a town is one new line taking a number out of the gap, and nothing else moves — Natherpetua, Bipulashar and Khila were added between Laksam (60) and Sonaimuri (70) at 62, 65 and 68, and Sonaimuri stayed at 70. `db:seed --class=TravelRouteSeeder` is safe to re-run on a live database for exactly that reason; `TravelRouteSeederTest` pins it.

Only an exhausted gap forces a real renumber. That is `TravelRouteService::renumber()`, on the road's header in `/admin` - it re-spaces the corridor in tens and re-places the rides on it in the same transaction. It keeps the towns in their existing order; a genuine reorder is still an open question, because rides were sold on the old one.

### Adding a stop to a corridor
On a running system, in `/admin` → Network: open the corridor and add the town to it, or open the town and put it on the corridor - the same pivot from either end. The position is chosen by **naming the two towns it sits between**, never by typing a number; `TravelRouteService::placementsOn()` does the arithmetic and refuses a gap with nothing left in it. See `admin-panel.md`.

In the seed data, when the town belongs to the network everybody gets:
1. Add the town to `STOPS` with its district. (`STOPS` is `name => district name`, and the name must match a row `DistrictSeeder` creates, or the town is seeded with no district at all.)
2. Add `'Town' => n` to the corridor in `ROUTES`, **choosing `n` from the gap** between its two neighbours.
3. Re-run `php artisan db:seed --class=TravelRouteSeeder`. It is idempotent: `updateOrCreate` on the stop, `syncWithoutDetaching()` on the pivot.
4. Mirror both in the app's `FakeRouteCatalogue` (`apps/lib/api/fake_route_api.dart`), or the offline board stops matching the real one.

**The seeder never detaches.** It is no longer the only author of the pivot - the panel writes it too - so a plain `sync()` would take every hand-placed town off its corridor on the next seed. The cost is that deleting a line from `ROUTES` no longer removes that town on a re-run; detach it in the panel, or retire the stop with `is_active`.

## Nothing in the network points at a map
Coordinates were dropped from `stops` and `rides` on 2026-09-18, and the Google `place_id` from both on 2026-09-19. Nothing ever matched on either - a stop is found by its position along a corridor, never by distance - and a driver sends two stop ids, so the ride's columns could only ever hold a copy of the stop's. There has been no map in either client since 2026-09-18, and nobody could fill a place id in by hand anyway.

`origin.place_id` and `destination.place_id` are gone from the ride responses rather than null; `Stop`'s went with it. Do not reintroduce either on the *ride*: if a pin is ever wanted, it belongs on the stop, nullable, read through `origin_stop_id` - the snapshot columns existed so a renamed town could not rewrite a published trip, and a moved pin does not have that problem.

## A district is a row, not a string
`districts` (2026-09-19) and `stops.district_id`. It was free text until then, so "Cumilla", "cumilla" and "Comilla" were three districts as far as anything could tell, and an admin had to remember how the last town was filed. `DistrictSeeder` ships all 64, matched by name and safe to re-run; the panel picks from them under Network → Districts.

Nothing about a journey touches it. A ride carries no district, no search reads one, and the label exists only so two same-named towns read apart in a picker - so it is the one thing in the network that cannot break a search. That is also why the column is `nullOnDelete` and not `restrictOnDelete`: losing a district must never take a town off the network with it, and a town is usable the moment it has a name.

`StopResource` still serves `district` as a plain **string** (`$stop->district?->name`), so no client changed. Anything reading stops for the API has to eager load the relation, or a corridor of fifty towns is fifty queries - see `TravelRouteEloquentRepository::active()`.

## A stop belongs to every corridor it is on
`stops` is one row per town, shared. Cumilla is attached to Chattogram, Cox's Bazar and Noakhali at three sequences; Elenga to Rangpur and Rajshahi; Bhanga to Khulna and Barishal. This is load bearing, not tidiness: the Dhaka-Cumilla stretch is one physical road, and a passenger standing at Cumilla has to be offered the vehicles coming down all three of them. Give a corridor its own private copy of a town and that search silently returns a third of the rides.

For the same reason, **any town on a shared stretch must be attached to every corridor using that stretch**. A stop missing from one of them is invisible to passengers on it.

## Corridors, not divisions
There are nine seeded routes, not eight: seven Dhaka-to-division trunks plus the Noakhali and Cox's Bazar branches. Laksam and Sonaimuri sit on the Noakhali branch and are not on the Dhaka - Chattogram highway, so a one-route-per-division model cannot express a ride from either. New branches are seed data, not schema.

## `legsBetween()` is plural, and its empty result means "impossible"
It answers "which corridors carry a journey from A to B, and where does each end sit on them". Several corridors can, so every caller handles a list. Two distinctions callers must keep:

- `[]` from `legsBetween()` - no corridor carries it. `RideRepository::available([])` returns nothing rather than everything.
- `null` passed to `available()` - no journey was asked for. Every bookable ride.

Collapsing those two is the bug that turns a hopeless search into an unfiltered list.

## The matching rule is containment, and it stays in plain SQL
A ride serves a leg when it is on that corridor, running the same way, and its own span **contains** the leg's. The consequence that reads as a bug until you draw it: **a ride is never offered to somebody boarding further out than it starts.** A vehicle setting out from Bipulashar (65) for Dhaka has already left Khila (68) behind and never goes there, so a Khila → Laksam search does not return it — while a Sonaimuri (70) ride does. `SeededNetworkSearchTest` pins that pair, because it is the question people ask first. Four integer comparisons against columns on `rides` - no join through the pivot, and no `LEAST`, `GREATEST` or `SIGN`, because the suite runs on sqlite. Keep it that way; the moment matching needs a SQL function, the test suite stops being able to run.

## `TravelRoute`, never `Route`
The model is `TravelRoute` on `travel_routes` purely so it never collides with `Illuminate\Support\Facades\Route`. The pivot is `travel_route_stop`, which is *not* Laravel's alphabetical default (`stop_travel_route`), so both `belongsToMany` calls name it explicitly. To every client this is still a "route" - `TravelRouteResource` and `GET /api/routes` do the renaming.

## Retiring is asymmetric, deliberately
`is_active` on a stop or a corridor hides it from the pickers and refuses new rides from it (`StoreRideRequest`), but `SearchRidesRequest` still accepts it and rides already published against it still list. Retiring a town must not strand the passengers of the rides already running past it. Never add an `is_active` check to the search.

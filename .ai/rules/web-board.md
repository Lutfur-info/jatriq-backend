---
paths:
  - 'app/Http/Controllers/Web/**'
  - 'routes/web.php'
  - 'resources/js/pages/rides/**'
  - 'resources/js/components/ride-*.tsx'
---

# The public web board

## The front page is the ride list, and it is the only public web surface
`/` renders `rides/index` and `/rides/{id}` renders `rides/show`, both through `App\Http\Controllers\Web\RideBoardController`, both unauthenticated and both under `throttle:rides-browse` — the same limiter `GET /api/rides` uses, keyed by address, because there is no account to key either to. Everything else a rider does is the JSON API; everything a reviewer does is Filament at `/admin`. Do not grow a web surface for signing in, booking, or publishing without deciding that deliberately.

`resources/js/pages/welcome.tsx` is the starter kit's page and is no longer routed.

## The board pages; `GET /api/rides` deliberately still does not
`RideRepository` has both `available()` (the whole collection, what the API returns) and `availablePage()` (a `LengthAwarePaginator`, what the board reads), and `RideEloquentRepository::bookable()` is the one statement of what bookable means that both are built from. Paging the JSON list would change a response shape the mobile clients already parse, so it was left alone — decided 2026-09-14. Anything that changes what "bookable" means goes in `bookable()`, never in one caller.

The empty-legs case is the one place the two diverge. `available([])` answers with no query at all; `availablePage([])` cannot, because a paginator still has to count and still has to carry the page links, so the impossibility is written into the query as `whereRaw('1 = 0')`. Both must answer "nothing" — a journey no corridor carries must never page as an unfiltered list.

## The search is the API's search, form request included
The board reuses `App\Http\Requests\Api\Ride\SearchRidesRequest`, so "both stops or neither" is stated once. A browser failing it gets a redirect back with the errors rather than a 422, which is Laravel reading the request, not a second set of rules. The matching itself is unchanged: same corridor, same direction, the ride's span containing the passenger's — see `routes.md`.

The pickers are built from `TravelRouteService::active()`, shipped with the page rather than fetched from `GET /api/routes`, and **deduplicated by stop id**: a town is on every corridor it sits on, so listing them per corridor would offer Cumilla three times. Alphabetical order is correct in the picker and wrong inside a corridor, where sequence is the meaning.

## The board must never carry a driver
Both pages are readable by anybody on the internet. `RideResource` carries no driver and must not learn to — a name and a number are not public. The vehicle's plate is exposed, judged acceptable because a plate is visible on the street; that judgement is recorded in `bookings.md` and unchanged here. `RideBoardTest` pins it on both pages.

## A departed ride is a 404; a full one is not
`RideRepository::findUpcoming()` filters on `departs_at >= now()` only, and the route takes an id rather than a bound model so the "has it left?" question is answered in the same read that loads the vehicle and the seat sum. A ride whose seats are all taken drops off the board but keeps its page: somebody following a link is owed "fully booked" rather than being told the trip never existed.

## Resource props are unwrapped, except the paginated list
`RideBoardController::plain()` sends a resource through `->response()->getData(true)['data']`, so a page component never sees Laravel's `data` wrapper. The round trip is load bearing: `resolve()` only goes one level down and leaves nested resources — a corridor's stops, a ride's vehicle — as objects, which survives being encoded into the page but not being read as an array by `assertInertia`.

The paginated ride list keeps its wrapper, because there `links` and `meta` beside the rows are the point.

## Testing the board
`RideBoardTest` calls `$this->withoutVite()`: the page components are compiled by Vite and the built manifest will not know a page added since the last `npm run build`. Note that `tests/Feature/ExampleTest.php` hits `/` without that escape, so it fails until `npm run build` has run — a real signal that the assets are stale, not a flaky test.

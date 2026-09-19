---
paths:
  - 'app/Filament/**'
  - 'app/Providers/Filament/**'
---

# Admin panel

## Reviewing lives in Filament, never in the API
`/admin` is the only review surface. There are no admin API routes and no `app/Http/Controllers/Api/Admin/` - a mobile client never reviews anybody, so do not reintroduce an endpoint for it. `GET /api/documents/{document}` is the one API route an admin uses, to read a scan; a panel session satisfies it because `config('sanctum.guard')` is `['web']`.

## `User::canAccessPanel()` is the only gate
Active admins, nobody else. Filament's `Login` page calls it before issuing a session, so a rider with valid credentials is refused at the form rather than admitted and shown a 403. A suspended admin loses the panel with the account. Do not add a second role check inside a resource or page - if the panel needs finer permissions, that belongs in resource policies, not a scattering of `isAdmin()` calls.

## Every decision goes through VerificationService::review()
A Filament action must not write `status`, `reviewed_by`, `reviewed_at` or `verification_status` itself. The service records the decision and recomputes the badge from the whole required set; writing the column directly leaves the badge stale (see `services.md`). The same rule covers any future bulk action.

## The panel reads accounts; it authors the network
`UserResource` registers `index` and `view` only, and `canCreate()` is false. Riders arrive through the registration API with an OTP, a rejection is answered by the applicant re-uploading, and no badge is ever set by hand. Adding a create, edit or delete page means arguing with all three of those.

`TravelRouteResource`, `StopResource` and `DistrictResource` are the deliberate opposite, and the only resources that author rows: the network is admin data with no other way into the system, so all three create, edit and delete. The rules below are not optional.

`RideResource` is the third case and the only one that *corrects* what a rider wrote - see below. It creates nothing and deletes nothing.

## A ride is corrected here, and nowhere else
`RideResource` registers `index`, `view` and `edit`, and `canCreate()` is false. A ride is published by a driver and a booking made by a passenger, so there is nothing to create; `bookings.ride_id` would take a passenger's seats with it, so there is nothing to delete. Editing is open **even once the ride is booked**, which `RideService::change()` refuses outright for the driver - with no cancellation anywhere in the product, a wrong departure on a ride with passengers aboard would otherwise be unfixable by anybody.

`EditRide::handleRecordUpdate()` hands the write to **`RideService::override()`**, never to Filament's own save, for the reason a review goes through `VerificationService`: the corridor and both sequences are the service's conclusion about which road the two stops share, and a ride saved without re-resolving them stays listed but drops out of every search. A `ValidationException` from the service is re-keyed onto `data.*` so the message lands on the field rather than nowhere.

Two rules survive the override and must not be relaxed:

- **`seats_offered` may never fall below the seats passengers already hold.** The form sets the minimum and the service checks it again under `lockForWrite()`, because the form's number is read before the lock and a booking can land in between. Below it, the free-seat arithmetic every read runs goes negative.
- **Both ends are re-placed through `TravelRouteService::place()`**, exactly as a driver's edit is. Writing `travel_route_id` or either sequence from the panel is the same bug as re-spacing a corridor without moving the rides.

What the override deliberately *does* allow, and what the form warns about in as many words: changing `seat_price` under a booking rewrites what every passenger owes, because `Booking::totalAmount()` multiplies the ride's own fare rather than storing a copy. That was safe only while a booked ride could not be repriced. If this becomes routine rather than a correction, snapshot the fare onto the booking.

The driver's departure window (`rides.departure.min_lead_minutes` / `max_days_ahead`) is **not** applied on this form. An admin correcting the record of a trip that has already run would be locked out by it.

## A position is chosen by its neighbours, or typed through the same check
`PlacementSelect` asks *where on the road* - "between Dhaka and Cumilla" - and `TravelRouteService::placementsOn()` turns that into a number: half the gap for an insert, ten past the end. "Set the number myself…" reveals the number field for an admin who knows the road; it resolves as `exact:{n}` through the same `sequenceFor()`, so it is checked for being inside the smallint and free on that corridor. Both relation managers use `PlacementSelect::fields()` and `::resolve()`, in both directions.

Do not add a position field that bypasses `sequenceFor()`. The check is what stops two towns landing on one number, and the pivot's unique index is a backstop, not the error message.

**A full gap is refused, never widened silently.** Two towns one apart have no whole number between them, so the option is listed as "no gap left", disabled, and refused by a rule if posted anyway. The answer is the renumber below.

## A renumber moves the rides with the road, or it is a bug
`TravelRouteService::renumber()` re-spaces a corridor on 10, 20, 30 … **and** rewrites `origin_sequence` / `destination_sequence` on every ride published along it, in one transaction. A ride carries a copy of its two sequences, so re-spacing alone leaves it listed but pointing at positions the road no longer has - invisible to every search. Never re-space the pivot without the second half, in the panel, a command, or a migration.

`TravelRouteRepository::resequence()` clears the corridor and re-lays it instead of updating in place, because the unique `(travel_route_id, sequence)` index rejects the intermediate states an in-place shuffle passes through. It must stay inside the caller's transaction.

The action is disabled while `isEvenlySpaced()`, and it never reorders - the towns keep their order, only the numbers between them change. A real reorder has to decide what happens to rides sold on the old order, and nothing in the panel does that.

## Placement belongs to the service
`TravelRouteService::putStopOn()`, `moveStopOn()` and `takeStopOff()` own every pivot write, through `TravelRouteRepository`. A Filament action must not call `attach()`, `sync()` or `updateExistingPivot()` itself - the same reason `VerificationService` owns a review, so a future admin API or console command inherits the rules. Panel *reads* still build their own queries, per the note below.

## The road reads top to bottom
`StopsRelationManager` sets no `defaultSort`, because the relation is already ordered by the pivot and the order **is** the corridor's direction - sequence runs outbound from Dhaka, and a ride toward Dhaka is one running back down it. Do not add a sort control there, and do not offer a drag-to-reorder: reordering is renumbering.

## A corridor's slug is frozen after creation
`TravelRouteSeeder` matches corridors on the slug, so a slug that followed a rename would make the next seed create a second corridor rather than update this one. The field is disabled and dehydrated, filled from the name on create only.

## A district is picked, never typed
`stops.district` was free text until 2026-09-19, so the same district could be spelled three ways and an admin had to remember how the last town was filed. `StopForm` and the corridor's inline "add a town" modal both offer a `Select` over `districts`; do not put a `TextInput` back in either.

`DistrictResource` is one screen - 64 rows that are a name and a switch, created and edited in a modal, so there is no create or edit page. Delete is hidden once any town is filed under one: `stops.district_id` is `nullOnDelete`, so deleting would not fail, it would silently blank the district on every town under it. `is_active = false` is the retirement, and a retired district stays visible on the towns already filed under it - the select does not filter it out, or editing such a town would clear it.

## A stop is only real once it is on a corridor
Creating one redirects to its edit page, where the corridors relation manager is; the list badges a stop on zero corridors in red, and a corridor with fewer than two towns is badged the same way. Do not "simplify" either redirect back to an index.

## Delete is for a mistake, not for retirement
`rides` restricts `travel_route_id` and both stop columns on delete. `StopResource::isReferencedByRide()` and `TravelRouteResource::carriesRides()` hide delete once anything is published, and detach is hidden for a corridor carrying a ride from that town - the ride would stay on the board but drop out of every search. `is_active = false` retires either without touching what is published.

Filament tables build their own Eloquent query, so `UserResource::getEloquentQuery()` - not a repository - is where a panel read is scoped. `UserRepository` exists for the writes a service performs; do not add read methods to it for the panel.

## Enum presentation
`VerificationStatus` and `DocumentStatus` implement `HasLabel` / `HasColor`, so a badge colours itself from the case. `Role` and `Gender` deliberately do not - their case name is the label, and the panel formats them with `formatStateUsing()` rather than coupling more domain enums to Filament. `User` implements `HasName` because there is no `name` column.

## Do not enable password reset
`password_reset_tokens` is keyed by msisdn for the OTP flow, so Laravel's email-based broker cannot drive it. `->passwordReset()` on the panel would present a form that cannot work.

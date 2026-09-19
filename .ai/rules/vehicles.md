---
paths:
  - 'app/Http/Controllers/Api/Driver/**'
  - 'app/Services/VehicleService.php'
  - 'app/Models/Vehicle.php'
---

# Vehicles

## One vehicle per driver, so the endpoint has no id and no list
A unique `vehicles.user_id` is what enforces it. `GET/POST /api/driver/vehicle` under `role:Driver` is the whole surface: `POST` registers the vehicle the first time and replaces the details afterwards, via `VehicleRepository::put()`, which creates-or-overwrites. Do not add `/driver/vehicles/{id}` routes without dropping that unique index first.

This is the only `role:Driver` group in `routes/api.php`. Everything else a rider does - verification, emergency contacts - is `role:Driver,Passenger`, because a driver and a passenger are treated alike everywhere except here.

## The licence is a UserDocument, never a column on the vehicle
`VehicleService::save()` passes an attached `driving_licence` to `VerificationService::storeMany()`, which owns the private disk, the replacement of the file it stands in for, the reset to `Pending`, and the badge refresh. `/api/verification` accepts the same document type for a driver. Two entry points, one writer - do not persist a document any other way.

The licence does not move the badge, because `DocumentType::DrivingLicence` is not in `requiredFor()`. The refresh still has to run: every path that changes a document calls it (see `services.md`).

## The vehicle row carries no review state
Nobody approves a vehicle. The licence and registration paper behind it are what an admin decides on in the Filament panel, and the vehicle is data the driver maintains. Introducing review means a status column, a panel action, and an edit putting the vehicle back into `Pending` - not a flag bolted onto the badge. The panel shows the vehicle read-only on the applicant's review page.

## Enums, plates and seats
`VehicleModel` and `CabinClass` are pure enums persisted by case name, matching `$table->enum(...)` built from `names()`, so a new model needs a migration to alter the column. `VehicleModel::typicalSeats()` is a default offered to the driver, never a rule - a refitted microbus is ordinary, and the only bound is `config('vehicles.seats')`.

`StoreVehicleRequest::prepareForValidation()` trims, single-spaces and uppercases the plate before validation, so hand-typed spellings of the same vehicle collide on the unique index. Any new writer of `registration_number` has to normalise it the same way.

## `vehicles.seats` counts PASSENGER seats, never the driver's own
Changed 2026-09-07; it used to count every seat including the driver's. What a rider needs to know is how many people can travel, so the column, the API field, `typicalSeats()` and the app's field all mean passenger capacity - one meaning end to end, with no conversion anywhere. Consequences worth knowing:

- the floor is **1**, not 2 (`config('vehicles.seats.min')`) - one passenger is a whole vehicle
- `typicalSeats()` returns one below the totals the models are sold with: a 12-seat HiAce is `11`
- `StoreVehicleRequest::attributes()` maps `seats` to "passenger seats" so the range 422 reads truthfully
- `2026_09_07_160739_convert_vehicle_seats_to_passenger_seats` decremented the rows written under the old meaning

Anything that reads `seats` as a total - a capacity display, a fare split, a matching rule - is off by one. Do not "fix" a seat count by adding the driver back.

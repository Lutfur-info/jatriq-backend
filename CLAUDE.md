<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>

<!--
Everything above this line is generated by `php artisan boost:update` and will be
overwritten. Project notes go below it, and survive regeneration.
-->

# Jatriq

Ride-hailing backend for the Bangladesh market. Laravel 13 on PHP 8.4, MySQL (`jatriq`),
Sanctum token auth, Pest 5. The repo is the Laravel React starter kit, so an Inertia v3 +
React front end sits in `resources/js`, but the product is driven by the JSON API under
`/api` that the mobile clients call.

## Where things live

Deviations from a stock Laravel install, and why:

| Path | Notes |
| --- | --- |
| `app/enum/` | Lower-case namespace `App\enum` (existing choice, keep it). Holds `Gender`, `Role`, `OtpStatus`, `DocumentType`, `DocumentStatus`, `VerificationStatus`, `VehicleModel`, `CabinClass`, `BookingStatus`. |
| `app/Services/` | Approved 2026-08-28 for `OtpService`. Business operations that outgrow a controller belong here. |
| `app/Notifications/` | Added 2026-09-20 for `BookingDecided`. One class per thing somebody is told; each is **sent by the thing that happened**, not by a feed endpoint. |
| `app/Http/Controllers/Api/Auth/` | JSON auth controllers. Routes are flat (`/api/login`), not versioned — decided 2026-08-28. |
| `app/Http/Requests/Api/Auth/` | One form request per endpoint. All input validation lives here, never in controllers. |
| `app/Http/Requests/Concerns/` | Shared request behaviour: `NormalizesMsisdn`, `ValidatesDocumentUploads`, `BuildsDocumentFileRules`. |
| `app/Http/Resources/` | `UserResource` is the only shape in which a user is ever returned. |
| `app/Repositories/` | Approved 2026-08-28. `Contracts/` interfaces, `Eloquent/` implementations, bound in `RepositoryServiceProvider`. Services depend on the contract. |
| `app/Http/Resources/RiderResource.php` | The minimal shape one rider is shown about another: name and badge, **never contact details**. Used by both ends of a booking and by favourites. |
| `config/otp.php` | Every OTP tunable. No magic numbers in the service. |
| `config/verification.php` | Document disk, size/mime limits, emergency contact ceiling. |
| `config/vehicles.php` | Seat range and plate length for the driver's vehicle. |
| `config/rides.php` | Seat price range and departure window for a ride. |
| `app/Http/Controllers/Web/` | The public web board at `/`. Added 2026-09-14; the only web surface besides Filament, and the Inertia mirror of `Api/`. |
| `app/Filament/` | The admin panel's resources. Reviewing lives here, not in the API; so does maintaining the network — corridors and stops (2026-09-18) — and the board of published rides, with the passengers on each (2026-09-19). |
| `app/Providers/Filament/` | `AdminPanelProvider` — the `/admin` panel and who may open it. |
| `database/seeders/DistrictSeeder.php` | The country's 64 districts. Reference data; matched by name, so safe to re-run on a live database. Runs before `TravelRouteSeeder`, which files each town under one by name. |
| `database/seeders/TravelRouteSeeder.php` | The nine highway corridors and the 52 towns on them, as explicit `town => sequence` maps. Reference data - runs on every environment, and safe to re-run on a live one: it attaches without detaching, so it never undoes a town placed from `/admin`. |
| `database/seeders/RideSeeder.php` | Development data: a driver who can publish, a passenger who can book, and 8 rides laid out to demonstrate every rule of the search. Prints the searches worth trying; `RideSeederTest` pins each one. |
| `.ai/rules/` | Recorded rules. **Read `.ai/rules/index.md` before editing matched paths.** |

## Domain model

`users` is a single table for all three roles (`App\enum\Role`: Admin, Driver, Passenger) —
one role per user in the `users.role` enum column. No pivot table, no `spatie/permission`.

Four separate flags, don't conflate them:

- `msisdn_verified_at` — the user proved they own the number. Null until an OTP is confirmed.
- `is_active` — the account may be used. False at registration, set true on verification,
  and the switch an admin flips to suspend someone.
- `verification_status` / `verified_at` — the **identity badge**. Derived from reviewed
  documents by `VerificationService::refreshBadge()`, never assigned. Nothing to do with the msisdn.
- `email_verified_at` — unused so far. Email is optional and plays no part in auth.

`notifications` is Laravel's own table, unaltered (2026-09-20): a uuid key, the class in `type`,
the recipient in a `notifiable` morph, the whole payload as JSON in `data`, and `read_at` null
until it has been opened. Left alone deliberately — a column per fact would have to grow with
every kind of notification, and each one is rendered from its own payload.

`Gender` and `Role` are **pure (non-backed) enums** storing the case *name* (`'Driver'`), matching
`$table->enum('role', Role::names())` in the migration. Eloquent resolves pure enums via
`constant()`, so casts work — but adding a case needs a migration to alter the column.

## Auth flow

```
POST /api/register     201  user created, is_active=false, OTP sent, no token
POST /api/msisdn/verify 200  correct code -> verified + active + first token
POST /api/msisdn/resend 200  new code (429 while the cooldown holds)
POST /api/msisdn/code   200  reads back the live code (404 unless otp.expose_codes)
POST /api/login        200  token   |  403 unverified (auto-resends)  |  403 deactivated
POST /api/password/forgot 200  sends a reset code (429 while the cooldown holds);
                               403 unverified / deactivated, exactly as login answers
POST /api/password/reset  200  code + new password -> revokes every old token, issues one
POST /api/logout       200  revokes the current token only
POST /api/tokens/create 201 auth'd: issues an extra token (403 if deactivated)
GET  /api/user         200  UserResource
```

## Verification badge & emergency contacts

```
GET  /api/verification                     badge + documents + what is missing
POST /api/verification                     nid_front, nid_back, profile_photo (each optional);
                                           a Driver may add driving_licence and
                                           vehicle_registration, which the badge ignores
GET  /api/documents/{document}             streams the file (owner or admin only)

GET    /api/emergency-contacts             list, primary first   (Driver or Passenger)
POST   /api/emergency-contacts             name, relation, msisdn, is_primary?
PATCH  /api/emergency-contacts/{contact}
DELETE /api/emergency-contacts/{contact}

GET  /api/driver/vehicle                   Driver only: the vehicle, its licence, and the
                                           model / class / seat choices to pick from
POST /api/driver/vehicle                   registration_number, model, cabin_class, seats
                                           (+ optional driving_licence file); replaces

GET   /api/driver/rides                    Driver only: my rides that have not left yet
POST  /api/driver/rides                    publish a ride: origin_stop_id, destination_stop_id,
                                           departs_at, seat_price, seats_offered (needs the badge)
PATCH /api/driver/rides/{ride}             edit one, until somebody books (needs the badge)

GET   /api/routes                          PUBLIC, no token: the corridors and their stops, in order
GET   /api/rides                           PUBLIC, no token: upcoming rides with a seat free;
                                           ?from_stop_id=&to_stop_id= searches along a route
POST  /api/rides/{ride}/bookings           Passenger only: ask for {seats} seats (needs the badge)
GET   /api/bookings                        Passenger only: my bookings, past trips included
POST  /api/bookings/{booking}/rating       Passenger only: {rating: 1..5} on a trip taken
GET    /api/favourite-drivers              Passenger only: the drivers she kept
POST   /api/favourite-drivers              {driver_id} (needs the badge)
DELETE /api/favourite-drivers/{driver}     (needs the badge)

GET   /api/driver/rides/{ride}/bookings    Driver only: who has asked for a seat on my ride
PATCH /api/driver/bookings/{booking}       Driver only: {status: Confirmed|Declined}
                                           (needs the badge)
```

There are **no admin endpoints**. Reviewing an applicant and deciding on a document happen in
the Filament panel (below); no mobile client ever reviews anybody, so that surface is not in
the API at all. `GET /api/documents/{document}` is the one route an admin does call, to read a
submitted scan — and a panel session satisfies it, because `sanctum.guard` is `['web']`.

Role separation is enforced by the `role:` middleware alias, not by a check in the controller.
The badge is **derived**: uploading completes the set (`Pending`), approving every required
document flips it to `Verified`, one rejection drops it to `Rejected`. Re-uploading a document
resets it to `Pending` and clears the earlier decision. Admins have no required set and are
never badged. Every tunable is in `config/verification.php`.

**Verification is one endpoint, not one per role.** `DocumentType::requiredFor()` returns the
same three documents — `NidFront`, `NidBack`, `ProfilePhoto` — for a Driver and a Passenger
alike, so `/api/verification` serves both under `role:Driver,Passenger`. Two lists, not one:
`requiredFor()` is what the badge waits on, `acceptableFor()` is what the endpoint stores, and
a driver's `DrivingLicence` / `VehicleRegistration` sit in the second only. They are uploaded
and reviewed like any other document but **do not hold the badge back** — a driver reaches
`Verified` on identity documents alone. To make an extra count, move its case into
`requiredFor()`; never add a parallel check. A file a role cannot submit (a passenger sending
a licence) is ignored, not rejected, because `StoreDocumentsRequest` builds its rules from
`acceptableFor($user->role)`.

Documents are written to the private disk (`config('verification.disk')`) with no public URL —
`GET /api/documents/{document}` is the only reader and re-checks who is asking.

Emergency contacts share that shape: a driver and a passenger keep the same list from the same
`role:Driver,Passenger` endpoint, since either side can be the one in trouble, and admins are
refused. A user holds at most `verification.emergency_contacts.max` (default 5) contacts,
exactly one of them primary while the list is non-empty; the service promotes a successor on
delete.

So `routes/api.php` has one **`role:Driver,Passenger`** group — verification and emergency
contacts. A new rider endpoint belongs in that shared group unless the two roles genuinely
differ; the two that do are **`role:Driver`** (the vehicle, and offering rides) and
**`role:Passenger`** (booking a seat — a driver offers seats, a passenger takes them, and
gating it that way is also what stops a driver booking their own ride).

## The driver's vehicle

The one thing only a driver has, and therefore the only `role:Driver` group left.

```
GET  /api/driver/vehicle    the vehicle, the licence on file, and the choices
POST /api/driver/vehicle    registration_number, model, cabin_class, seats,
                            driving_licence (optional file)
```

- **At most one vehicle per driver**, enforced by a unique `vehicles.user_id`. So there is no id
  in the path and no list endpoint: `POST` registers it the first time and replaces the details
  afterwards, and `VehicleRepository::put()` creates-or-overwrites accordingly.
- **`VehicleModel`** (`HiAce`, `Noah`, `Corolla`) and **`CabinClass`** (`Ac`, `NonAc`) are pure
  enums persisted by case name, like `Role` and `Gender` — so adding a model needs a migration to
  alter the `enum` column, not just a new case. `typicalSeats()` is a *starting point* offered to
  the driver, never a validation rule: a refitted microbus is normal, so the seat count is only
  bounded by `config('vehicles.seats')`.
- **`seats` counts passenger seats, not every seat.** The driver's own seat is excluded, because
  what a rider needs to know is how many people can travel — so the floor is **1**, and
  `typicalSeats()` is one below the total a model is sold with (a 12-seat HiAce returns `11`).
  It counted the driver until 2026-09-07;
  `2026_09_07_160739_convert_vehicle_seats_to_passenger_seats` decremented the rows written
  under the old meaning. Anything reading `seats` as a total is off by one.
- **Both verbs serve the choices** under `data.options`, so a client renders the pickers from the
  API instead of hardcoding the enums.
- **The plate is folded to one spelling** in `prepareForValidation()` — trimmed, single-spaced,
  uppercased — because it is typed by hand off a metal sign and `dhaka metro-ga-11-2233` has to
  collide with `DHAKA METRO-GA-11-2233` on the unique index.
- **The licence file is not stored on the vehicle.** `VehicleService` hands it to
  `VerificationService::storeMany()`, so it becomes an ordinary `UserDocument`: private disk,
  replacement of the previous file, back into `Pending`, and the same admin review. It does
  **not** move the badge — `DrivingLicence` is not in `requiredFor()` — but the refresh still
  runs, because every path that changes a document must (see `.ai/rules/services.md`).
- **The vehicle row itself carries no status.** Nobody approves it; the licence and registration
  paper behind it are what an admin reviews. Adding review later means a status column, a review
  endpoint, and putting an edited vehicle back into `Pending`.

## The driver's rides

A ride is a seat offer: where it starts, where it ends, when it leaves the start point, and
what one passenger seat costs. `role:Driver`, like the vehicle, and for the same reason - the
seats being sold are that driver's vehicle's seats.

```
GET  /api/driver/rides    my upcoming rides, soonest first
POST /api/driver/rides    origin_stop_id, destination_stop_id,
                          departs_at, seat_price, seats_offered
                          -> 201 with the ride and the vehicle it is in
```

- **A ride never names a vehicle.** There is no `vehicle_id` in the body: `RideService::offer()`
  resolves it with `VehicleRepository::forUser()`, and a driver who has not registered one is
  refused with a **422 keyed on `vehicle`** - allowed on the endpoint, just not ready yet. The
  repository associates `user_id` and `vehicle_id`, and neither is in `Ride`'s `#[Fillable]`, so
  no body can publish a ride into another driver's name or car.
- **`seats_offered` is passenger seats, capped by the vehicle's own `seats`** (1..`vehicle.seats`).
  A driver may offer fewer than the car holds - cargo, family - so it is its own column rather
  than a read of the vehicle. Neither number counts the driver; see the seats note above.
- **Both ends are stop ids**, resolved onto a corridor by `TravelRouteService` - see the
  routes section above. The label on the ride is a snapshot of the stop's name.
- **There are no coordinates anywhere in the network** (dropped 2026-09-18). They were only ever
  a map pin: matching has always run on a stop's `sequence` along a corridor and never on
  distance, and a driver sent two stop ids rather than a point, so nothing could fill them but a
  copy of the stop. Each end still keeps the label and the `place_id`, which a client can resolve
  for itself.
- **The departure window is config, not code**: `rides.departure.min_lead_minutes` (15) keeps a
  ride from being posted after it has effectively left, and `max_days_ahead` (30) keeps the
  upcoming list meaningful. `rides.price` bounds the fare; the ceiling is there to catch a fare
  typed with an extra zero.
- **No status column and no lifecycle.** No cancel, no complete, no `RideStatus`. "Upcoming" is
  `departs_at >= now()`, so a ride simply stops being listed. Adding cancellation means a status
  column, filtering it out of every read, and deciding what happens to bookings.
- **Only exactly-identical start and destination is refused.** A minimum trip distance is a
  product decision nobody has taken, so validation does not invent a threshold.
- **Editing closes the moment anybody books.** `PATCH /api/driver/rides/{ride}` is partial, and
  `RideService::change()` refuses it as soon as the ride has a booking — a **422 keyed on `ride`**,
  because the passenger agreed to that route, that departure and that fare. With no cancellation
  this is permanent; the driver's remaining move is to publish another ride. Somebody else's ride
  is a **404**, as an emergency contact is. An edit that sends only the destination is still
  checked against the start point the ride already holds.
- **Publishing and editing need the identity badge** (`verified.identity` middleware): a passenger
  is getting into a stranger's car. `GET /driver/rides` is deliberately open, so a driver whose
  badge lapses can still see what they have out there — and the vehicle and verification endpoints
  stay open, or earning the badge would depend on already having it. This is
  `verification_status`, **not** `msisdn_verified_at` (login already enforces that) and not
  `is_active`.

## Routes, stops and the search

The thing the product is actually for: a passenger at Laksam wanting Dhaka has to be
shown the vehicle that set out from **Sonaimuri**, because Laksam is still ahead of it on
the same road.

```
GET /api/routes                                  the corridors, each with its stops in order
GET /api/rides?from_stop_id=&to_stop_id=         the rides that will carry that journey
```

- **Three tables.** `travel_routes` are the corridors, `stops` are the towns, and
  `travel_route_stop` carries a **`sequence`** saying where each town falls on each
  corridor. A ride denormalises `travel_route_id`, both `*_stop_id` and both
  `*_sequence` columns, so the search is four integer comparisons on `rides` with no
  join - and no `LEAST`/`SIGN`, which sqlite does not have.
- **Sequence runs outbound from Dhaka, always.** Dhaka is the lowest number on every
  corridor and **there is no direction column**: a ride toward Dhaka is one whose
  `destination_sequence` is below its `origin_sequence`. Seed a corridor the wrong way
  round and every ride on it matches backwards.
- **The rule is containment.** A ride serves a passenger when it is on the same corridor,
  running the same way, and its own span swallows theirs - so a Sonaimuri -> Dhaka ride
  answers Laksam -> Dhaka, Laksam -> Cumilla and Sonaimuri -> Laksam alike, but not
  Maijdee -> Dhaka (it never gets that far out) and not Cumilla -> Laksam (wrong way).
- **A stop belongs to every corridor it is on**, and this is load bearing. Cumilla is one
  row attached to Chattogram, Cox's Bazar and Noakhali at three sequences, because the
  road out of Dhaka is physically the same for all three - so a Cumilla -> Dhaka search
  returns vehicles coming down all of them. Give a corridor its own copy of a town and
  that search silently loses two thirds of its rides.
- **Corridors, not divisions.** Nine seeded routes: seven Dhaka-to-division trunks plus
  the **Noakhali** and **Cox's Bazar** branches. Laksam and Sonaimuri are on the Noakhali
  branch, *not* the Dhaka - Chattogram highway, so one-route-per-division cannot express
  them at all. New branches are seed data, not schema.
- **Sequences are written out in the seeder, not derived from list position**, and spaced in
  tens. That matters because a ride **copies** its sequences at publish time and nothing goes
  back to fix them: a numbering that shifted when a town was inserted would silently
  mis-position every ride already on that corridor. Adding a town is one line taking a number
  out of the gap — Natherpetua, Bipulashar and Khila went in between Laksam (60) and
  Sonaimuri (70) at **62, 65 and 68**, and Sonaimuri stayed at 70. So
  `php artisan db:seed --class=TravelRouteSeeder` is safe on a live database; see
  `.ai/rules/routes.md` for the four-step recipe.
- **A ride is never offered to somebody boarding further out than it starts**, which reads as
  a bug until you draw it. A vehicle leaving **Bipulashar (65)** for Dhaka has already passed
  **Khila (68)** — Khila is further from Dhaka — so a Khila → Laksam search does not return
  it, while a **Sonaimuri (70)** ride does. `SeededNetworkSearchTest` pins that pair.
- **Both ends of a search or neither.** "From Laksam" does not say which way, and a
  corridor read backwards is a different set of rides - so `from_stop_id` and
  `to_stop_id` are `required_with` each other. With neither, `GET /api/rides` stays the
  open shop window it always was.
- **A driver picks two stops and nothing else about the road.** `RideService` resolves the
  corridor through `TravelRouteService::place()` and refuses two stops that share none - a
  **422 keyed on `destination_stop_id`**, matching the missing-vehicle refusal. Where two
  towns are on several corridors it files the ride under the one they are closest together
  on; that choice is cosmetic, deciding only the displayed corridor name, never which
  passengers match.
- **The ride keeps a snapshot** of each stop's name and place id, so renaming a stop never
  rewrites a trip somebody already agreed to, and `BookingResource` still reads `origin_name`
  straight off the ride.
- **Retiring is asymmetric.** `is_active = false` on a stop or corridor hides it from the
  pickers and refuses new rides from it, but the *search* still accepts it and the rides
  already published past it still list. Never add an `is_active` check to the search.
- **The fare is flat.** A passenger joining at Laksam pays the same `seat_price` as one
  who boarded at Sonaimuri. Charging by leg needs a fare table and a fare snapshot on the
  booking.
- **Rides published before corridors existed** have null route columns. They still appear
  in an unfiltered `GET /api/rides` and can never match a search; editing one end of such
  a ride is refused until both are sent.

## Bookings

A passenger's seats on somebody else's ride. `POST /api/rides/{ride}/bookings` with `{seats}` —
the API's one **`role:Passenger`** endpoint, and it also needs the identity badge.

- **A seat is asked for, not taken** (2026-09-19). Every booking arrives `Pending` and the driver
  answers it with `PATCH /api/driver/bookings/{booking}` — they are the one letting a stranger
  into their car. `App\enum\BookingStatus` is `Pending`, `Confirmed`, `Declined`, a pure enum
  persisted by case name.
  - **A pending request holds its seats exactly as a confirmed one does**, and declining is the
    only thing that gives a seat back. This is the load-bearing choice: counting only confirmed
    seats would let a driver confirm more requests than the car has, moving the oversell from the
    booking to the confirmation instead of removing it. So **every seat sum reads
    `BookingStatus::holdingNames()`** — the repository's total, the "not full" subquery behind the
    public board, and the panel's columns. A fourth case is added there *and* to the two raw SQL
    strings, or the board starts selling seats somebody is holding.
  - **Asking again tops up and goes back to `Pending`**: the driver agreed to two seats and is
    now being asked for three. A request they **declined** starts over rather than topping up —
    those seats went back on the market.
  - **A pending request still closes the ride to editing**; a ride whose every request was
    declined reopens, because nobody is travelling and there are no terms left to protect.
  - **Re-confirming a declined request re-checks the seats under the ride's lock**, since
    somebody else may have taken them in between. It is refused rather than overselling.
  - `GET /api/driver/rides/{ride}/bookings` is open to a lapsed badge, like `GET /driver/rides`;
    answering needs it, because the answer is what puts a stranger in the car. Somebody else's
    ride is a **404**.
  - **The driver sees a name and a badge, never a number.** `RiderResource` is a new minimal
    shape, deliberately not `UserResource`. Whether a confirmed booking should unlock a phone
    number is still an open product and privacy decision.
  - **The answer is what tells the passenger** (2026-09-20). `BookingService::decide()` sends
    `BookingDecided` on the `database` channel. From the *service* so every route to a decision
    carries it; **after** the transaction commits, so a rolled-back decision cannot leave a
    notification claiming it happened; and **only when the status actually changed**, because
    re-sending the answer a booking already has is exactly what a client with a lost response
    does and the endpoint accepts that quietly. The payload is a **copy** — both place names,
    the seats, and the worded `title`/`body` — not ids to resolve later, since a feed has to
    render from what it holds.
  - `tests/Feature/DriverBookingDecisionTest.php` pins the decision (18 tests) and
    `tests/Feature/BookingNotificationTest.php` pins what it tells her (15).

- **The seat count is the whole request.** The route, the departure and the fare are the driver's.
- **One booking per passenger per ride** (`unique(['ride_id','user_id']`)). Booking again **tops up**
  that row, so `seats` is a running total: a repeat answers `200` where the first answered `201`.
- **Counting the free seats and taking them is one locked step.** `BookingService::book()` runs in a
  transaction and locks the ride row first, or two passengers both take the last seat. The edit guard
  in `RideService::change()` takes the *same* lock, so the two serialise: either the edit lands before
  any booking, or the booking wins and the edit is refused. Both pass `attempts: 3` — Laravel retries
  only on a deadlock or lock wait timeout, never on a `ValidationException`, so a refusal stays a
  refusal. A refusal throws inside the transaction and rolls it back whole; a test binds a repository
  that writes then throws to prove the transaction is load-bearing. Note `lockForUpdate()` is a no-op
  on sqlite, which the suite runs on — MySQL enforces the isolation, and no test here can catch that
  race (only the rollback).
- **The fare is not copied onto the booking**, because a ride with a booking can no longer change
  its price. If editing ever opens up after booking, a snapshot becomes necessary.
- **`seats_offered` is what was offered.** The seats still free are `seats_offered` minus the
  **holding** bookings' seats, which `RideResource` exposes as `seats_available`
  alongside `seats_booked`. `RideResource` reports `0` booked when that aggregate was not
  selected, so **any query rendering a ride must include `withSum('bookings', 'seats')`** or it
  will tell the client the car is empty.
- **`GET /api/rides` is PUBLIC** — the only unauthenticated read in the API. The home screen shows
  upcoming rides to somebody with no account, and signing in is what booking asks for, so it sits
  outside the `auth:sanctum` group and is throttled by IP (`throttle:rides-browse`). Upcoming and
  not yet full, soonest first, each with its vehicle and honest seat counts; "not full" is a
  correlated subquery on `bookings.seats`, not a `HAVING` on the `withSum` alias. It must never
  carry anything about the driver — that would publish a name and number publicly. It does expose
  the **vehicle plate** anonymously, judged acceptable because a plate is visible on the street.
  It now also takes `?from_stop_id=&to_stop_id=` and answers the route search — see the routes
  section above. **Still no pagination**, and no date or price filter: an unfiltered call returns
  every bookable ride there is, and paging will change the response shape.
- **`GET /api/bookings` is the passenger's own list**, past trips included, upcoming soonest-first
  and then trips already taken latest-first. It does *not* need the badge — it is the booking that
  needs verifying, not the looking, the same bargain `GET /driver/rides` makes.
- **`BookingResource` is flat**: `seats`, `status` + `status_label`, `decided_at`, `rating`,
  `rated_at`, `can_rate`, `total_amount`, `vehicle_number`, `origin_name`, `destination_name`,
  `departs_at`, `created_at`. Not the ride's own shape — place ids and seat inventory stay in
  `RideResource`, which the create response still carries alongside. The label ships beside the
  case name so no client spells a state out of an enum.
  It needs `ride` and `ride.vehicle` loaded; the list eager-loads them and the repository attaches
  the ride it already holds on a write.
- **`total_amount` is `bcmul`, not float arithmetic** — 650.50 × 2 is exactly `1301.00`.
  `Ride::$seat_price` is typed `numeric-string` to make that call type-safe. It is derived rather
  than stored, which is only safe because a booked ride can no longer change its price.
- **Each end sees the other, and neither gets a number.** `passenger` and `driver` are both
  `RiderResource` — name, badge and score, no contact details — and each is present only where
  its relation was loaded, which is always the *opposite* end from whoever is asking. That
  replaced the older rule that a booking carried nothing about the driver (2026-09-19): the
  minimal shape is what made it safe, and `UserResource`, which holds email, msisdn and date of
  birth, is still never sent about somebody else.

## Ratings

What a passenger thought of a trip she took, and what that adds up to for the driver.
Added 2026-09-19.

- **"Completed" is derived, because there is no ride lifecycle.** A trip counts as taken when
  the **driver confirmed her seat** and the **ride has departed** — `Booking::isRateable()`.
  Adding a `RideStatus` to say it better would have to be filtered out of every read and every
  seat sum; those two facts already say it. `BookingResource` ships `can_rate` so no client
  re-derives the rule.
- **The score lives on the booking**, not a table of its own: the booking already *is* the one
  row per passenger per ride, so `unique(ride_id, user_id)` gives "one rating per trip" free.
  It mirrors `status` — his answer to her request, hers to the trip. `POST` creates **or
  replaces**; she has one opinion of a trip and may change her mind, so there is no rating id.
- **The driver's score is derived, exactly as the badge is.** `users.rating_average` and
  `users.ratings_count` are a cache of `RatingService::refreshFor()`, recomputed from the
  ratings inside the same transaction as the write. Nothing else writes either column. Skipping
  the refresh leaves every ride card quoting a number that no longer matches its own ratings.
- **A null average is not a zero.** "Nobody has rated him" and "rated nought" must never render
  alike, which is why the average is nullable and the count always travels with it.
- **The rating is the one thing the public board says about the driver**, and the documented
  exception to "`GET /api/rides` carries nothing about him": a score is an aggregate and
  identifies nobody. It is attached by a **correlated subselect**, so there is no `User` on the
  ride at all for a later change to expose.
- In the app: stars on each taken trip in **My bookings**, the driver's score under his name
  there and on **Favourite drivers**, and a score line on every ride card.
- `tests/Feature/RideRatingTest.php` (16 tests) and `test/rating_test.dart`.
  See `.ai/rules/ratings.md`.

## Favourite drivers

The drivers a passenger wants to ride with again. `role:Passenger`, added 2026-09-19.

- **A favourite grants nothing.** It reserves no seat, jumps no queue, changes no search, and
  **the driver is never told**. That is what keeps it a private note she keeps rather than
  something both sides share, and why there is no locking here and no seat arithmetic.
  `User::favouritedBy()` is the other side of the pivot and nothing reads it.
- **No model.** `favourite_drivers` is a pivot with two `users` foreign keys and its timestamps,
  reached through `User::favouriteDrivers()` — a favourite *is* the fact that the row exists.
  `withTimestamps()` is load bearing: it is what `favourited_at` reads and what orders the list.
- **Both writes are idempotent, and that is the contract.** Favouriting one already kept answers
  `200` where the first answered `201` and **does not move the timestamp** — otherwise tapping a
  filled heart silently reorders her list. Removing one she never kept is not an error. That is
  what makes a lost response safe for the app to repeat, and what the optimistic heart depends on.
- **Only a Driver can be favourited.** `role:Passenger` says who may call; nothing about the route
  says who may be *named in the body*, so `FavouriteDriverService` refuses anybody else with a 422
  keyed on `driver_id`.
- **Keeping needs the badge; looking does not** — the same bargain `GET /api/bookings` makes.
- **No contact details, in either direction.** `RiderResource` (name + badge) and
  `FavouriteDriverResource` (the same four fields plus the car) are deliberately not
  `UserResource`. Favouriting must not become a way to collect phone numbers by tapping a heart.
- **`BookingResource` now names the driver on her own list**, and that is what makes the feature
  usable: nothing else a passenger can open shows her a driver, because `GET /api/rides` is public
  and a name there is a name on the open internet. Each end of a booking sees the other and never
  itself — the driver's queue names the passenger, her list names the driver.
- In the app: **Favourite drivers** on the home menu lists them and removes one; the heart on each
  card in **My bookings** is where one is added. `FavouriteDriverController` is app-wide, not
  per screen, so both read the same answer.
- `tests/Feature/FavouriteDriverTest.php` (15 tests), plus `favourite_driver_controller_test.dart`
  and `favourite_driver_api_test.dart` in the app. See `.ai/rules/favourites.md`.

## Notifications

What somebody is told happened while they were not looking. Added 2026-09-20, and the first
thing in it is the driver's answer to a request for seats — a seat is asked for, not taken, so
that answer is the one thing a passenger is genuinely waiting on.

- **Stored, not pushed.** `via()` is `['database']` and that is the whole delivery: the answer
  waits in her feed and a bell counts it. There is no device token anywhere in this system.
  Adding push later means adding a channel and a token store; nothing here is rewritten.
- **A notification is sent by the thing that happened**, never by the feed. `BookingDecided`
  goes out from `BookingService::decide()` beside the decision it is about — see Bookings for
  the three rules that go with that (from the service, after commit, only on a change).
- **The payload is a copy, and the wording is the server's.** `title` and `body` arrive written,
  for the reason `status_label` does: a phone in somebody's pocket must not need a release to
  reword a sentence. Only `booking_id` and `ride_id` point outward, for a client that wants to
  open what is being talked about.
- `GET /api/notifications`, `POST /api/notifications/{id}/read`, `POST /api/notifications/read-all`.
  All `role:Driver,Passenger` — either end of a ride can be told something — and **none of them
  takes `verified.identity`**: reading what you have already been told is not doing anything.
  `read-all` is declared **before** the `{notification}` route, or it would be read as a uuid.
- **Every response carries `unread_count`**, the two writes included, so a client's badge never
  needs a second call. Marking one read twice is accepted and does **not** move `read_at`, so a
  lost response is safe to repeat.
- **`NotificationResource` is generic**: `type`, `title` and `body` hoisted, the stored payload
  passed through whole as `data`. That is what lets a new kind of notification reach a screen
  with no client change; a resource that named a booking's fields would need a branch per class.
- **Every repository method is scoped to a user** — there is no find-by-id that is not also "and
  it is theirs" — and somebody else's is a **404**, the answer a ride that is not yours gives.
- In the app: a bell with an unread badge in the header, plus **Notifications** in the side menu.
  `NotificationController` is app-wide, and the composition root clears it on every sign-in/out
  transition so the next person on the phone never sees the last one's feed.
- `tests/Feature/BookingNotificationTest.php` (15 tests), plus `notification_api_test.dart` and
  `notification_controller_test.dart` in the app.

## The public web board

The site's front page, at **`/`**, and the ride behind every card on it. Inertia v3 + React,
rendered by `App\Http\Controllers\Web\RideBoardController`; no account, no session, nothing
to sign into.

```
GET /                                    upcoming bookable rides, soonest first, ?page=
GET /?from_stop_id=&to_stop_id=          the same journey search the API answers
GET /rides/{ride}                        one ride in full
```

- **It reads the same rides `GET /api/rides` does, and the same search.** `SearchRidesRequest`
  is shared, so "both stops or neither" is stated once; a browser failing it is redirected back
  with the errors rather than handed a 422. Matching is unchanged - same corridor, same
  direction, containment.
- **The board pages; the JSON list still does not.** `RideRepository::availablePage()` sits
  beside `available()`, both built from one private `bookable()` query, because paging the API
  would change a response shape the mobile clients already parse. `rides.board.per_page`
  (`RIDE_BOARD_PER_PAGE`, 12) is the page size.
- **A departed ride is a 404; a ride with no seats left is not.** `findUpcoming()` filters only
  on `departs_at`, so a full ride drops off the board but keeps its page and says "fully
  booked". The route takes an id rather than a bound model, so that is decided in the read that
  also loads the vehicle and the seat sum.
- **It carries nothing about the driver**, for the reason `GET /api/rides` does not: this is a
  page anybody on the internet can read. `RideBoardTest` pins that on both pages.
- **The pickers ship with the page**, built from `TravelRouteService::active()` and deduplicated
  by stop id - a town is on every corridor it sits on, so grouping by corridor would offer
  Cumilla three times.
- Departure times render in **Asia/Dhaka** (`resources/js/lib/format.ts`); the app stores UTC.
- `resources/js/pages/welcome.tsx` is the starter kit's page and is no longer routed.

See `.ai/rules/web-board.md`.

## Admin panel (Filament 5)

The admin's UI, at **`/admin`**, doing three jobs. It is the *only* place verification happens —
there are no admin API routes, and `app/Http/Controllers/Api/Admin/` no longer exists. Since
2026-09-18 it is also where the **network** is maintained, under a *Network* navigation group:
the corridors and the towns along them, which used to mean editing `TravelRouteSeeder` and
re-running it. Since 2026-09-19 it shows the **rides** — every one ever published, the seats sold
on each, and who bought them — and is the one place a published ride can be corrected.

| Path | What it is |
| --- | --- |
| `app/Providers/Filament/AdminPanelProvider.php` | The panel: id `admin`, path `/admin`, login page, brand navy. |
| `app/Filament/Resources/Users/UserResource.php` | Applicants. Scoped to Driver + Passenger, `index` and `view` pages only. |
| `.../Tables/UsersTable.php` | The queue: name, mobile, role, badge, document count, last activity. |
| `.../Schemas/UserInfolist.php` | The profile a reviewer reads, the derived badge, and the driver's vehicle. |
| `.../RelationManagers/DocumentsRelationManager.php` | The decision: open the file, approve, or reject with a reason. |
| `app/Filament/Resources/Stops/StopResource.php` | The towns. Full CRUD — the one place in the panel that authors rows. |
| `.../Stops/Schemas/StopForm.php` | The place: name, a district **picked from `districts`**, and whether it is still offered. |
| `.../Stops/Tables/StopsTable.php` | The catalogue, alphabetical, flagging any town on no corridor. |
| `.../Stops/RelationManagers/TravelRoutesRelationManager.php` | Attach, move or detach the town along each corridor it sits on. |
| `.../Stops/Pages/{List,Create,Edit}Stop.php` | Create lands on the edit page, where the corridors are; delete is hidden on a stop in use. |
| `app/Filament/Resources/TravelRoutes/TravelRouteResource.php` | The corridors. Full CRUD; `carriesRides()` is what hides a delete. |
| `.../TravelRoutes/Schemas/TravelRouteForm.php` | Name, the slug frozen after creation, and whether the road is open. |
| `.../TravelRoutes/Schemas/PlacementSelect.php` | "Where on the road?" — the neighbour picker both relation managers place with. |
| `.../TravelRoutes/Tables/TravelRoutesTable.php` | The nine roads, with town and ride counts. |
| `.../TravelRoutes/RelationManagers/StopsRelationManager.php` | **The road**: the towns in travel order, and adding, moving or removing one. |
| `app/Filament/Resources/Districts/DistrictResource.php` | The 64 districts, so a stop points at one instead of spelling it. One screen; create and edit are modals. |
| `app/Filament/Resources/Rides/RideResource.php` | The board. `index`, `view` and `edit`; `seatsBooked()` / `seatsAvailable()` are the seat arithmetic every screen reads. |
| `.../Rides/Tables/RidesTable.php` | Every ride, latest departure first, with what is offered, booked and free. |
| `.../Rides/Schemas/RideInfolist.php` | One ride: the trip, the seats and fare, the driver and the vehicle. |
| `.../Rides/Schemas/RideForm.php` | The correction, and the warning it carries when passengers are aboard. |
| `.../Rides/RelationManagers/BookingsRelationManager.php` | **The passengers**: who has asked for a seat, the driver's answer, and what each owes. Read-only — the driver decides. |
| `.../Rides/Pages/{List,View,Edit}Ride.php` | `EditRide` writes through `RideService::override()`, never Filament's own save. |

- **`User::canAccessPanel()` is the whole gate** — an *active* admin and nobody else. Filament's
  login page calls it before issuing a session, so a driver typing valid credentials is refused
  at the form rather than let in and shown a 403. A suspended admin (`is_active = false`) loses
  the panel with the account.
- **Every decision goes through `VerificationService::review()`.** Filament writes no status
  column directly; the service records the decision *and* recomputes the badge from the whole
  required set, so a shortcut here would leave the badge stale.
- **An account is read-only here.** No create page (riders register through the API with an OTP),
  no edit page, no delete, no way to set a badge by hand. A reviewer reads a profile and decides
  on documents; that is all the panel does to a *person*. Stops are the deliberate exception
  below — they are admin data with no other way in.
- **A ride is read here, and corrected here.** `RideResource` creates nothing — a ride is
  published by a driver and a booking made by a passenger — and deletes nothing, because
  `bookings.ride_id` would take a passenger's seats with it. But an admin **may edit any ride,
  booked or not**, which `RideService::change()` refuses outright for the driver: with no
  cancellation anywhere in the product, a wrong departure on a ride with passengers aboard
  would otherwise be unfixable by anybody.
  - The write goes through **`RideService::override()`**, not Filament's save, so both ends are
    re-placed by `TravelRouteService::place()` — a ride saved without re-resolving its corridor
    and sequences stays listed but drops out of every search.
  - **`seats_offered` can never fall below the seats already held.** The form sets the minimum
    and the service checks it again under `lockForWrite()`, because a booking can land between
    the two.
  - **Changing `seat_price` under a booking rewrites what every passenger owes**, since
    `Booking::totalAmount()` multiplies the ride's own fare rather than storing a copy. The form
    says so in as many words rather than refusing it. Making this routine means snapshotting the
    fare onto the booking.
  - The driver's departure window (15 minutes' lead, 30 day horizon) is **not** applied here, or
    an admin could not correct the record of a trip that has already run.
  - `tests/Feature/AdminRideTest.php` pins all of it (16 tests).
- **Documents are opened through `GET /api/documents/{document}`**, the same authenticated
  reader the app uses — the private disk still has no public URL. It works from the panel
  because `config('sanctum.guard')` is `['web']`, so Sanctum accepts the session when no bearer
  token is present. `tests/Feature/AdminPanelTest.php` pins that.
- **Login is by email**, Filament's default, so an admin needs `users.email` set — the seeded one
  has `admin@jatriq.test`. The API logs in by msisdn; if the panel should too, that is a custom
  `Login` page overriding `getCredentialsFromFormData()`.
- **`->passwordReset()` is deliberately not enabled**: the API resets a password with an OTP sent
  to the msisdn (`POST /api/password/{forgot,reset}`), so Laravel's email-based broker drives
  nothing here. An admin who is locked out is reset by hand.
- `VerificationStatus` and `DocumentStatus` implement Filament's `HasLabel` / `HasColor` so a
  badge colours itself from the case. `Role` and `Gender` do not — their case name *is* the
  label, so the panel formats them inline instead of coupling two more enums to Filament.
- `User` implements `HasName`, returning `full_name`. Without it Filament reads a `name`
  attribute that does not exist and 500s rendering the account menu.
- **The network is the one thing the panel authors.** `TravelRouteResource` and `StopResource`
  create, edit and delete corridors and towns, because the network is admin data with no other
  way into the system — a rider is not. Added 2026-09-18; before it, `TravelRouteSeeder` was the
  only way either existed.
  - **A district is picked, never typed** (2026-09-19). It was a free-text column, so "Cumilla",
    "cumilla" and "Comilla" were three districts as far as anything could tell. `districts` is a
    table now, `DistrictSeeder` ships all 64, and both the stop form and the corridor's inline
    "add a town" modal offer a select over it. `stops.district_id` is **`nullOnDelete`**, not
    `restrictOnDelete`, because a district is only a picker label - losing one must never take a
    town off the network. Delete is hidden on a district with towns under it for the same reason:
    it would silently blank theirs. `StopResource` still serves `district` as a plain string, so
    no client changed; eager load the relation or a corridor of fifty towns is fifty queries.
    `tests/Feature/AdminDistrictTest.php` pins it (10 tests).
  - **Adding a town is two halves, and the second is the one that counts.** The row is nothing on
    its own: a stop reaches a picker only through a corridor, so `CreateStop` redirects to the
    edit page — where `TravelRoutesRelationManager` is — rather than back to the list, and the
    table badges a stop on zero corridors in red.
  - **A position is checked against the pivot before the database checks it.**
    `travel_route_stop` is unique on `(travel_route_id, sequence)`; the attach and move forms
    refuse a taken number first, so an admin reads a sentence instead of an integrity error. The
    helper text lists what the corridor already uses, in travel order.
  - **Nothing here renumbers a corridor.** A published ride carries a *copy* of both sequences,
    so moving a town leaves every ride where it was — which is why the sequences are spaced in
    tens, and why the manager moves one town into a free number instead of offering a reorder.
  - **Retire a town in use; never delete it.** `rides` holds `restrictOnDelete` on both stop
    columns, so delete is hidden once any ride runs from or to the stop
    (`StopResource::isReferencedByRide()`), and detach is hidden for a corridor carrying such a
    ride — the ride would stay on the board but stop matching a search. `is_active = false` is
    the supported retirement, and it leaves published rides working.
  - **The panel and `TravelRouteSeeder` now share the pivot**, so the seeder attaches with
    `syncWithoutDetaching()` — a plain `sync()` took every hand-placed town off its corridor the
    next time anybody seeded. The cost: deleting a line from `ROUTES` no longer detaches that
    town on a re-run, so do that in the panel.
  - **A position is chosen by its neighbours by default.** `PlacementSelect` asks *where on the
    road* — "at the start, before Dhaka", "between Dhaka and Cumilla", "at the end, after
    Laksam" — and `TravelRouteService::placementsOn()` works the number out of the gap, halving
    it for an insert and adding ten past the end. The resolved number is shown in each label, so
    an admin can still read the corridor's tens.
  - **"Set the number myself…" is the escape hatch**, added 2026-09-18 because reading a number
    and not being able to type one is its own trap. It resolves through the same
    `sequenceFor()`, as `exact:{n}`, checked for being inside the smallint and free on that
    corridor — so it skips the explanation, not the safety. The move form is pre-filled with the
    town's current number, and submitting it unchanged is a no-op rather than a self-collision.
  - **A full gap is refused, never widened silently.** Two towns one apart have no whole number
    between them; the option is listed as "no gap left", disabled, and refused by a rule if it
    is posted anyway. The answer is the renumber below, not a quiet shuffle of the neighbours.
  - **`renumber()` is the only thing that re-spaces a road, and it moves the rides with it.**
    `TravelRouteService::renumber()` re-lays the corridor on 10, 20, 30 … in the town's existing
    order *and* rewrites `origin_sequence` / `destination_sequence` on every ride published along
    it, in one transaction. Renumbering without that second half is the trap the whole design
    exists to avoid: the rides stay listed but point at positions the road no longer has, so
    every search misses them. `AdminCorridorTest` proves the corridor is still searchable
    end to end through `GET /api/rides` afterwards.
    - The action is on the road's header, `danger`-coloured, confirmed, and **disabled while
      `isEvenlySpaced()`** — a road already on tens has nothing to gain and its rides nothing
      to risk.
    - `TravelRouteRepository::resequence()` clears the corridor and re-lays it rather than
      updating row by row: the pivot's unique `(travel_route_id, sequence)` index rejects the
      intermediate states an in-place shuffle passes through, and no write order avoids that.
    - A ride whose stop has since come off the corridor is left alone, not guessed at — it is
      already unsearchable, and inventing a sequence would put it back on the road in the wrong
      place.
  - **The road reads top to bottom.** `StopsRelationManager` sets no `defaultSort`: the relation
    is already ordered by the pivot, and any other order would misread the corridor's direction.
  - **A corridor's slug is frozen after creation.** `TravelRouteSeeder` matches corridors on the
    slug, so a slug that followed a rename would make the next seed create a *second* corridor
    instead of updating this one. The field is disabled and dehydrated; renaming is safe.
  - **Placement is the service's, not the panel's.** `TravelRouteService::putStopOn()`,
    `moveStopOn()` and `takeStopOff()` own it, through three new `TravelRouteRepository` methods
    plus `placementsOn()` — so a future admin API or console command gets the same rules. The
    panel still builds its own *reads*, per the rule above.
  - `tests/Feature/AdminStopTest.php` and `tests/Feature/AdminCorridorTest.php` pin all of it
    (48 tests). `.ai/rules/admin-panel.md` and `.ai/rules/routes.md` carry the same rules for
    anybody editing these files.
- **Relation managers are lazy** (`CanBeLazy`, Livewire 4). The documents table is *absent from
  the review page's first render* and arrives via a `__lazyLoad` call on intersect. Do not read
  an empty first response as a broken table. It also means
  `Livewire::test(DocumentsRelationManager::class, [...])` mounts the component directly and
  **skips** `canViewForRecord()`, so a manager hidden by authorization still passes such a test —
  assert `UserResource::getRelations()` and `canViewForRecord()` separately, as
  `AdminPanelTest` does.

Verification codes are **not** in `password_reset_tokens`. Laravel's `DatabaseTokenRepository`
hardcodes the `email` column, so the password broker cannot drive a msisdn flow without
replacing the repository and the broker manager. `App\Services\OtpService` handles it instead:

- keys are namespaced by `App\enum\OtpPurpose`: `otp:{purpose}:{msisdn}`, where the segment is
  `msisdn` for verification (unchanged, so codes already in the cache still resolve) and
  `password-reset` for a reset. A number can hold one of each, and neither is accepted in the
  other's place — a reset code must not be spendable as an account activation
- code is `Hash::make`'d into the cache under that key, never stored in plaintext
- the record carries `attempts` and `expires_at`; a wrong code increments attempts **without**
  extending the TTL, and the record is dropped after `otp.max_attempts`
- a correct code is consumed immediately, so it cannot be replayed
- `otp:{purpose}:{msisdn}:cooldown` holds the resend timestamp, so asking for a reset code does
  not consume the cooldown a verification resend needs
- while `otp.expose_codes` is on (default: any non-production env) the plain code is also
  mirrored to `otp:{purpose}:{msisdn}:plain` for the same TTL, purely so `POST /api/msisdn/code`
  can hand it back — that endpoint takes an optional `purpose` (`MsisdnVerification` by default,
  `PasswordReset` for the reset code). Verification still checks the hash; the mirror is never
  read by `verify()`.

`OtpService::deliver()` is the single seam for the SMS provider — it currently only logs.

## Conventions to follow when extending this

- **Never mass assign `role`, `is_active`, or `msisdn_verified_at`.** They are absent from the
  model's `#[Fillable]` on purpose; assign them explicitly. `RegisterRequest` allow-lists
  `Role::selfRegisterable()`, so a request body can never create an Admin.
- Successful responses are `{ "message": ..., "data": { ... } }`. Failures use
  `ValidationException` (422) so the client parses one error shape; reserve bare JSON responses
  for 403/429 states that are not field errors.
- Route names are prefixed `api.` and referenced by name in tests (`route('api.login')`).
- Anything that costs money or guesses a credential gets a named limiter in
  `AppServiceProvider::configureRateLimiters()`, keyed by **both** msisdn and IP.
- Msisdn arrives in any format; the `NormalizesMsisdn` trait reduces it to digits before
  validation, so lookups always compare digits to digits.
- Feature tests live in `tests/Feature`. To learn a plain OTP inside a test, call
  `app(OtpService::class)->send($user)` — the stored one is hashed.

## Commands

```bash
php artisan migrate:fresh --seed            # admin + passenger + driver, the corridors,
                                            # and a board of 8 rides to search
php artisan test --compact                  # full suite
vendor/bin/pint --format agent              # required before finishing PHP changes
vendor/bin/phpstan analyse --memory-limit=1G  # crashes on the default 128M limit
```

**`php artisan serve` throws away your env overrides.** `ServeCommand::startProcess()` blanks
every variable that is not on its passthrough list whenever a `.env` file exists, so
`DB_CONNECTION=sqlite php artisan serve` silently serves from `.env` — against the real `jatriq`
database. To point a live server at a throwaway database, bypass it:

```bash
export DB_CONNECTION=sqlite DB_DATABASE=/tmp/jatriq-live.sqlite CACHE_STORE=database
php artisan migrate:fresh --seed          # artisan itself does inherit the env
cd public && php -S 127.0.0.1:8765 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

That router resolves the public path from `getcwd()`, so it has to be started from `public/`.

`CACHE_STORE=array` will not do for anything touching OTPs: the code lives in the cache, and the
array driver discards it between requests, so `/msisdn/code` returns 422 and resend cooldowns
never register.

Seeded logins (password `Win4Win$`): **`1700000000`** admin, **`1700000001`** passenger,
**`1700000002`** driver — note the **missing leading zero**. `DatabaseSeeder` writes them
that way, so signing in as `01700000001` finds no account and returns a perfectly correct
"These credentials do not match our records." Every other number in the codebase (factories,
tests, the app's fixtures) is written `01…`, which is what makes this easy to trip over.
The admin signs into `/admin` with **`admin@jatriq.test`** — the panel authenticates by
email, the API by msisdn.

## Open items

- **Nothing in the network points at a map any more.** Coordinates went from stops and rides on
  2026-09-18, along with the web board's "Open in maps" link and the app's orphaned
  `location_picker_page.dart`; the Google `place_id` went from both on 2026-09-19. Nothing ever
  read either, there has been no map in either client since 2026-09-18, and nobody could type a
  `ChIJ…` into a form by hand. **`origin.place_id` and `destination.place_id` are gone from the
  ride responses rather than null**, and `Stop`'s went with them — a mobile client parsing either
  field needs the release that drops it. If a map pin is ever wanted back, it belongs on the
  *stop* only, nullable, with the ride reading through `origin_stop_id` rather than a second
  snapshot column — the snapshot existed so a renamed town could not rewrite a published trip,
  and a moved pin does not have that problem.
- **SMS provider is not implemented.** `OtpService::deliver()` logs the code. In `local` the
  register/resend responses also include `debug_code` for Postman; it is omitted everywhere else.
  `POST /api/msisdn/code` covers the same gap for a client that missed the first response. Set
  `OTP_EXPOSE_CODES=false` the moment a real provider lands.
- **`CACHE_STORE` is `database`.** Set it (or `OTP_CACHE_STORE`) to `redis` — phpredis is
  installed — and then add `$middleware->throttleWithRedis()` in `bootstrap/app.php`.
- **`password_reset_tokens` is still unused.** Password reset is built (`PasswordResetController`)
  but runs on `OtpService` with the `PasswordReset` purpose, not the password broker — so the
  table can be dropped whenever somebody is confident nothing else wants it.
- **`tests/Feature/ExampleTest.php` hits `/` without `withoutVite()`**, so it fails with a
  `ViteException` whenever the built manifest is older than the page components. Run
  `npm run build` (or `npm run dev`) and it passes; `RideBoardTest` calls `withoutVite()` and is
  unaffected either way.
- **Two seeder suites fail, and both predate the features around them.**
  `tests/Feature/DatabaseSeederTest.php` (3 failures, 1 error) signs in as `01700000000`–`2`
  while `DatabaseSeeder` stores `1700000000`–`2`, so the lookups miss. Either side could be the
  one to change — the seeder to match every other number in the codebase, or the test to match
  the seeder — so it is left for a decision rather than guessed at.
  `tests/Feature/RideSeederTest.php` (2 failures) expects eight seeded rides and counts seven;
  it has not been traced to a cause. **Everything outside those two passes: 383 of 389 as of
  2026-09-20**, and the Flutter suite is 243 green with a clean `flutter analyze`.
- **`GET /api/rides` still has no pagination, and no date or price filter.** The web board pages
  (`RideRepository::availablePage()`), but the JSON list was deliberately left whole on
  2026-09-14 because paging it changes a response shape the mobile clients already parse. When
  they are ready for it, the query is already shared - only the controller and `RideService`
  need to switch method.
- **A renumber is a panel action now, not a migration** (2026-09-18). `renumber()` re-spaces a
  road and rewrites the published rides on it in the same transaction, so an exhausted gap is
  recoverable without hand-written SQL. What it deliberately does *not* do is reorder: the towns
  keep their sequence, only the numbers between them change. Genuinely reordering a corridor —
  a town that turns out to sit on the other side of another — still means deciding what happens
  to the rides that were sold on the old order, and nothing in the panel does that. The seeder
  remains the way the *shipped* nine corridors are defined; the panel is how a live network is
  changed.
- **No fare for a partial leg.** Somebody riding Laksam to Cumilla pays the same as somebody
  riding Sonaimuri to Dhaka. Per-leg pricing needs a fare table and a fare snapshot on the
  booking, which reopens the "a booked ride cannot change its price" reasoning.
- **No cancellation anywhere.** `BookingStatus` exists now, but it is the *driver's* answer, not
  a passenger's way out: she cannot withdraw a request, and a confirmed seat can only be released
  by the driver declining it after the fact. A driver still cannot cancel or complete a *ride*
  either — that means a status column on `rides` and filtering it out of every read and seat sum.
- **No written review.** A rating is a number; free text brings moderation, abuse reporting and
  a display surface, none of which exist. A driver also cannot rate a passenger.
- **The driver is still not told that somebody is waiting.** The passenger is now told when her
  request is answered (2026-09-20, `BookingDecided`), but the mirror does not exist, so a driver
  only finds a queue by opening it. It needs one more notification class sent from
  `BookingService::book()` and nothing else — not a line of the feed changes.
- **Nothing is pushed.** Delivery is the `database` channel and a feed the client reads on sign
  in and on opening the bell; there is no polling and no socket, so a passenger with the app
  closed learns nothing until she opens it. Push needs a device-token store and an FCM sender,
  and then a channel added to `via()` — none of what is written changes.
- **The feed has no pagination.** `NotificationRepository::forUser()` caps at the latest 50
  rather than paging, on the same reasoning as the ride list: fine at this size, and paging it
  changes a response shape the clients already parse. The clients read `unread_count` from the
  server rather than counting rows, which is what keeps the bell honest past that cap.
- **Each end of a booking can now see the other, and neither can phone them.** The driver's queue
  names the passenger and her list names the driver, both through `RiderResource` — name and badge,
  no contact details. Unlocking a number on a *confirmed* booking so the two can arrange a pickup
  is the privacy decision still unanswered; it belongs on the booking, not on a favourite.
- **`RideSeeder` covers rides and one booking**; there is still no standalone `BookingSeeder`.
  The panel now shows rides and the bookings on them (2026-09-19), but there is no bookings
  resource of its own — a booking is only ever reached through the ride it is on, which is fine
  while nobody needs "every booking this passenger holds" from the panel.
- `date_of_birth` is a `string` column, not `date`.
- **An unauthenticated API request without `Accept: application/json` returns 500, not 401.**
  The auth middleware redirects guests to a route named `login`, which does not exist here
  (`api.login` and `filament.admin.auth.login` do). The mobile clients always send the JSON
  header so they see a clean 401; anything else gets a server error. Fix with
  `$exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*'))` in `bootstrap/app.php`.
- `config/sanctum.php:21` has a pre-existing PHPStan error (published vendor config).

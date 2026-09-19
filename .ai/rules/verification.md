---
paths:
  - 'app/Http/Controllers/Api/Verification/**'
---

# Verification

## One verification endpoint serves both riding roles
`GET/POST /api/verification` is shared by drivers and passengers, gated by `role:Driver,Passenger` (`App\Http\Middleware\EnsureUserHasRole`, matching `App\enum\Role` case names). There is no `/driver/verification` or `/passenger/verification` any more: `DocumentType::requiredFor()` returns the same three documents - `NidFront`, `NidBack`, `ProfilePhoto` - for both roles, so a second endpoint had nothing to say.

Admins are excluded from the route rather than handled inside it. They review applicants in the Filament panel at `/admin` (see `admin-panel.md`) - there is no admin API - and `requiredFor(Role::Admin)` is empty, so they are never badged.

## The required set and the accepted set are different lists
`DocumentType::requiredFor($role)` is what the badge waits on. `DocumentType::acceptableFor($role)` is what the endpoint will store - for a driver that is the required three plus `DrivingLicence` and `VehicleRegistration`, which are uploaded and reviewed like anything else but do not hold the badge back.

`StoreDocumentsRequest` builds its rules from `acceptableFor($user->role)`, so a file a role cannot submit (a passenger sending a licence) is ignored rather than rejected. To make an extra document count towards the badge, move its case into `requiredFor()` - do not add a parallel check.

`POST /api/driver/vehicle` accepts a `DrivingLicence` too, so a driver can send it with their vehicle details. Both paths write through `VerificationService::storeMany()`, which is the only writer: do not add a second way to persist a document.

Uploads are optional field by field so a client can submit one document at a time on a poor connection; the request is only rejected when it carries no file at all. Requiredness is enforced by the badge, not by the validator.

## Documents stay off the public disk
NID scans and licences go to the private disk from `config('verification.disk')` and are never given a public URL or `Storage::url()`. `GET /api/documents/{document}` is the only way to read one and re-checks owner-or-admin on every request. Keep it that way when adding fields to `UserDocumentResource`.

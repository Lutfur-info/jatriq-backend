---
paths:
  - app/Models/User.php
---

# Models

## Roles are a single enum column, never mass assignable
A user holds exactly one role (`App\enum\Role`: Admin, Driver, Passenger) in the `users.role` enum column. No pivot table, no spatie/permission.

`role` is deliberately absent from the model's `#[Fillable]` attribute — assign it explicitly so request input can never promote someone to Admin. `RegisterRequest` allow-lists `Role::selfRegisterable()` (Passenger, Driver); admins are seeded.

## A password change hands back every token issued on the old one
`User::resetPassword()` writes the password, deletes the user's Sanctum tokens and rotates `remember_token`, in that one call. A reset is what somebody locked out of their account does, so whoever locked them out must not keep the access they already hold — never write `password` on its own.

`App\enum\Role` and `App\enum\Gender` are pure (non-backed) enums storing the case *name*, matching the `enum` columns built from `Role::names()` / `Gender::names()`. Eloquent casts resolve pure enums with `constant()`, so this works — but adding a case means a new migration to alter the column.

---
paths:
  - 'app/Repositories/**'
---

# Repositories

## Repositories are contract-first; services never touch Eloquent for these models
Layering is controller -> service -> repository contract -> Eloquent implementation. Services type hint `App\Repositories\Contracts\*`, never a model query.

Contracts live in `Repositories/Contracts` named `<Subject>Repository`; implementations live in `Repositories/Eloquent` named `<Subject>EloquentRepository` (the driver is a suffix, not a prefix, so the subject sorts first). `RepositoryServiceProvider::$bindings` maps them. Add both halves plus the binding when introducing a repository.

Repositories own the "never mass assignable" columns for their model: `is_primary`, `verification_status`, `verified_at` and a document's review fields are set with explicit assignment or `forceFill()` inside the repository, so no request body can reach them. Anything creating a row whose column has a DB default must `refresh()` before returning it (e.g. `dial_code`).

Note the older `OtpService` predates this layer and still uses `User::query()` directly - that is not a pattern to copy for new code.

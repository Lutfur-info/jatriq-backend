---
paths:
  - 'app/Services/RatingService.php'
  - 'app/Http/Controllers/Api/BookingRatingController.php'
  - 'app/Http/Requests/Api/Booking/RateBookingRequest.php'
---

# Ratings

## "Completed" is derived, because there is no ride lifecycle
There is no status on `rides`, no "complete" to press and nothing to press it - see `rides.md`. So a trip counts as taken when **the driver confirmed her seat and the ride has departed**, which is `Booking::isRateable()`. Do not add a `RideStatus` to make this read better: it would have to be filtered out of every read and every seat sum, and the two facts above already say it.

`BookingResource` ships `can_rate` so no client re-derives that rule from a departure time and a status. A client offering "rate this trip" on a trip the API then refuses is the worst way to discover the rule moved.

## The rating lives on the booking, not on a table of its own
`bookings.rating` and `bookings.rated_at`. The booking already **is** the one row per passenger per ride, so `unique(ride_id, user_id)` gives "one rating per trip" for free with nothing to keep in step. It is the mirror of `status`: his answer to her request, hers to the trip.

Both are absent from the model's fillable list, like `status`: the repository writes them, so no body can post a score onto a trip it did not take. Rating again **replaces** - she has one opinion of a trip and may change her mind - which is why `POST` creates-or-replaces and there is no rating id anywhere.

## The driver's score is derived, exactly as the badge is
`users.rating_average` and `users.ratings_count` are a **cache** of `RatingService::refreshFor()`, recomputed from the ratings themselves after every write, inside the same transaction. Nothing else may write either column; `UserRepository::putRating()` is the only door and `forceFill` is how it gets through.

Skipping the refresh leaves every ride card in the country quoting a number that no longer matches its own ratings, and nothing goes back to notice. This is the same rule `VerificationService::refreshBadge()` carries, for the same reason.

**A null average is not a zero.** "Nobody has rated him" and "he is rated nought" are different claims and must never render alike - hence nullable, hence `ratings_count` alongside it, and hence `User::$attributes` defaulting the count to 0 so an unsaved model does not report null.

The average reaches through `User::receivedRatings()`, a has-many-through driver → rides → bookings. There is no direct line from a driver to a score, and that is the point: only somebody who travelled on one of his rides can move it.

## The rating is the one thing the public board says about the driver
`GET /api/rides` is public and must never carry the driver - see `bookings.md`. A **score is an aggregate and identifies nobody**, where a name and a number identify somebody, so `driver_rating` is the documented exception and the only one.

It is attached by a **correlated subselect** in `RideEloquentRepository::withDriverRating()`, not by `with('user')`. That is not a micro-optimisation: with no `User` on the ride there is nothing for a later change to accidentally expose. Keep it that way, and never add the driver relation to a query that renders a `RideResource`.

`RideResource` formats the average with `number_format`, because a raw subselect never passes through `User`'s `decimal:2` and would otherwise report `4` where every other shape reports `4.00`.

## What is deliberately not built
**No written review.** A score is a number; free text brings moderation, abuse reporting and a display surface, none of which exist. Adding one is a product decision, not a column.

**A driver cannot rate a passenger.** The column is on the booking and could carry the mirror, but nothing reads or writes it and the endpoint is `role:Passenger`.

**Nobody is notified of a rating**, and a driver is not told his average moved.

<?php

use App\Http\Controllers\Api\Auth\ApiTokenController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\MsisdnOtpCodeController;
use App\Http\Controllers\Api\Auth\MsisdnOtpController;
use App\Http\Controllers\Api\Auth\MsisdnVerificationController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\AvailableRideController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BookingRatingController;
use App\Http\Controllers\Api\Driver\RideBookingController;
use App\Http\Controllers\Api\Driver\RideController;
use App\Http\Controllers\Api\Driver\VehicleController;
use App\Http\Controllers\Api\EmergencyContactController;
use App\Http\Controllers\Api\FavouriteDriverController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TravelRouteController;
use App\Http\Controllers\Api\Verification\DocumentFileController;
use App\Http\Controllers\Api\Verification\VerificationController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisterController::class, 'store'])
    ->middleware('throttle:register')
    ->name('api.register');

Route::post('/login', [LoginController::class, 'store'])
    ->middleware('throttle:login')
    ->name('api.login');

/*
 * The shop window, and the one read in the whole API that needs no token:
 * the home screen shows upcoming rides to somebody who has not signed up
 * yet, and signing in is what booking a seat asks for. Throttled by address
 * rather than by account, because there is no account.
 *
 * With `from_stop_id` and `to_stop_id` it is also the search: the rides
 * running that corridor, that way round, whose own trip contains the
 * passenger's. Both or neither - one end alone does not say which way.
 */
Route::get('/rides', [AvailableRideController::class, 'index'])
    ->middleware('throttle:rides-browse')
    ->name('api.rides.index');

/*
 * The corridors those two stop ids come from, each with its stops in travel
 * order. Unauthenticated for the same reason the ride list is: the pickers
 * on the home screen are drawn before anybody signs in, and this is
 * reference data with nothing in it to protect.
 */
Route::get('/routes', [TravelRouteController::class, 'index'])
    ->middleware('throttle:rides-browse')
    ->name('api.routes.index');

Route::post('/msisdn/verify', [MsisdnVerificationController::class, 'store'])
    ->middleware('throttle:otp-verify')
    ->name('api.msisdn.verify');

Route::post('/msisdn/resend', [MsisdnOtpController::class, 'store'])
    ->middleware('throttle:otp')
    ->name('api.msisdn.resend');

Route::post('/msisdn/code', [MsisdnOtpCodeController::class, 'show'])
    ->middleware('throttle:otp-code')
    ->name('api.msisdn.code');

/*
 * Forgotten password, in two requests. There is no reset link to click,
 * because an account is identified by its msisdn: "forgot" sends a code to
 * the number, and "reset" spends it on a new password. Posting to "forgot"
 * again is the resend.
 */
Route::post('/password/forgot', [PasswordResetController::class, 'store'])
    ->middleware('throttle:password-forgot')
    ->name('api.password.forgot');

Route::post('/password/reset', [PasswordResetController::class, 'update'])
    ->middleware('throttle:password-reset')
    ->name('api.password.reset');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => new UserResource($request->user()))
        ->name('api.user');

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('api.logout');

    Route::post('/tokens/create', [ApiTokenController::class, 'store'])
        ->middleware('throttle:tokens')
        ->name('api.tokens.store');

    // Submitted documents live on a private disk; this is the only way to read one.
    Route::get('/documents/{document}', [DocumentFileController::class, 'show'])
        ->name('api.documents.show');

    /*
     * Everything a rider does, whichever end of the ride they are on. Admins
     * are excluded from all of it: they review applicants and are never badged
     * themselves, and they do not ride.
     */
    Route::middleware('role:Driver,Passenger')->group(function () {
        /*
         * Verification badge. One endpoint, because a driver and a passenger
         * are asked for the same identity documents; a driver's licence and
         * vehicle papers ride along as extras the badge does not wait on.
         */
        Route::get('/verification', [VerificationController::class, 'show'])
            ->name('api.verification.show');

        Route::post('/verification', [VerificationController::class, 'store'])
            ->middleware('throttle:document-upload')
            ->name('api.verification.store');

        /*
         * What they were told while they were not looking. Open to both
         * riding roles for the same reason emergency contacts are: either
         * end of a ride can be told something. Today that is the driver's
         * answer to a request for seats - a seat is asked for, not taken, so
         * the passenger has been waiting on exactly that.
         *
         * No `verified.identity` anywhere here: reading what you have
         * already been told is not doing anything.
         */
        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('api.notifications.index');

        /*
         * Clearing the bell. Declared before the `{notification}` route, or
         * "read-all" would be read as a uuid and answer 404.
         */
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
            ->name('api.notifications.read-all');

        /*
         * One of them. Somebody else's is a 404, the same answer a ride that
         * is not yours gives, and marking one twice is accepted quietly so a
         * lost response is safe to repeat.
         */
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'update'])
            ->name('api.notifications.read');

        // Both sides of a ride can go wrong, so a driver keeps the same
        // emergency contact list a passenger does.
        Route::get('/emergency-contacts', [EmergencyContactController::class, 'index'])
            ->name('api.emergency-contacts.index');

        Route::post('/emergency-contacts', [EmergencyContactController::class, 'store'])
            ->name('api.emergency-contacts.store');

        Route::patch('/emergency-contacts/{emergency_contact}', [EmergencyContactController::class, 'update'])
            ->name('api.emergency-contacts.update');

        Route::delete('/emergency-contacts/{emergency_contact}', [EmergencyContactController::class, 'destroy'])
            ->name('api.emergency-contacts.destroy');
    });

    /*
     * The vehicle, which is the one thing only a driver has. At most one per
     * driver, so POST registers it and then replaces it - there is no id in
     * the path. It carries the driving licence too, because that is the
     * document about the driving rather than about the person.
     */
    Route::middleware('role:Driver')->group(function () {
        Route::get('/driver/vehicle', [VehicleController::class, 'show'])
            ->name('api.driver.vehicle.show');

        Route::post('/driver/vehicle', [VehicleController::class, 'store'])
            ->middleware('throttle:document-upload')
            ->name('api.driver.vehicle.store');

        /*
         * Rides the driver is offering. Many per driver, unlike the vehicle,
         * so this is a list plus an id-less create - the seats on sale are
         * the registered vehicle's, which is why a ride names no vehicle and
         * a driver without one is refused.
         */
        Route::get('/driver/rides', [RideController::class, 'index'])
            ->name('api.driver.rides.index');

        /*
         * Publishing and editing need the identity badge: a passenger is
         * getting into a stranger's car, so the documents behind that trip
         * have to have been reviewed. Listing is deliberately left open, so
         * a driver whose badge lapses can still see what they have out
         * there - and the vehicle and verification endpoints stay open too,
         * or earning the badge would depend on already having it.
         */
        Route::post('/driver/rides', [RideController::class, 'store'])
            ->middleware(['verified.identity', 'throttle:ride-create'])
            ->name('api.driver.rides.store');

        /*
         * Editing closes the moment anybody books: the passenger agreed to
         * that route, that departure and that fare. Partial, like an
         * emergency contact edit - only the fields sent are written.
         */
        Route::patch('/driver/rides/{ride}', [RideController::class, 'update'])
            ->middleware('verified.identity')
            ->name('api.driver.rides.update');

        /*
         * The requests for seats on one of the driver's rides. A seat is
         * asked for, not taken - the driver is letting a stranger into their
         * car - so every booking arrives Pending and waits here.
         *
         * Readable without the badge, like the ride list above: a driver
         * whose badge lapses can still see who is waiting on them.
         */
        Route::get('/driver/rides/{ride}/bookings', [RideBookingController::class, 'index'])
            ->name('api.driver.rides.bookings.index');

        /*
         * Confirm or decline one. This needs the badge for the reason
         * publishing does - the answer is what actually puts a stranger in
         * the car - and the seats are counted again under the ride's lock,
         * because declining gave them back and somebody else may have taken
         * them since.
         */
        Route::patch('/driver/bookings/{booking}', [RideBookingController::class, 'update'])
            ->middleware('verified.identity')
            ->name('api.driver.bookings.update');
    });

    /*
     * The only role:Passenger group in the API. Booking is the one thing the
     * two riding roles genuinely differ on in the other direction: a driver
     * offers seats, a passenger takes them - and gating it this way is also
     * what stops a driver booking their own ride.
     */
    Route::middleware('role:Passenger')->group(function () {
        /*
         * Her own bookings, past trips included. Open like the driver's own
         * ride list: it is the booking that needs the badge, not the
         * looking - somebody whose badge lapses can still see what they
         * have already paid for.
         */
        Route::get('/bookings', [BookingController::class, 'index'])
            ->name('api.bookings.index');

        /*
         * What she thought of a trip she took. Creates or replaces, like the
         * driver's vehicle - she has one opinion of a trip and may change
         * her mind - so the booking is the identifier and there is no
         * rating id anywhere.
         *
         * No `verified.identity`: rating is gated on having *travelled*,
         * which is stronger. She could not be holding a confirmed booking
         * on a departed ride without the badge in the first place.
         */
        Route::post('/bookings/{booking}/rating', [BookingRatingController::class, 'store'])
            ->name('api.bookings.rating.store');

        // Booking needs the badge for the mirror-image reason: the driver
        // is letting a stranger into their car.
        Route::post('/rides/{ride}/bookings', [BookingController::class, 'store'])
            ->middleware('verified.identity')
            ->name('api.rides.bookings.store');

        /*
         * The drivers she wants to ride with again. A private note she
         * keeps: it reserves no seat, jumps no queue, and the driver is
         * never told. Hence a Passenger-only list rather than something
         * both sides share.
         *
         * Reading it is open, like her bookings - it is doing something
         * that needs the badge, not looking.
         */
        Route::get('/favourite-drivers', [FavouriteDriverController::class, 'index'])
            ->name('api.favourite-drivers.index');

        /*
         * Both writes are idempotent, so a lost response is repeatable: a
         * second favourite answers 200 where the first answered 201, and
         * removing one that was never there is not an error. The driver is
         * the identifier on the delete - she holds at most one favourite
         * per driver, so there is no pivot id for a client to remember.
         */
        Route::post('/favourite-drivers', [FavouriteDriverController::class, 'store'])
            ->middleware('verified.identity')
            ->name('api.favourite-drivers.store');

        Route::delete('/favourite-drivers/{driver}', [FavouriteDriverController::class, 'destroy'])
            ->middleware('verified.identity')
            ->name('api.favourite-drivers.destroy');
    });

    /*
     * There is deliberately no admin group here. Reviewing an applicant and
     * deciding on a document happen in the Filament panel at /admin, gated by
     * User::canAccessPanel(); no mobile client ever reviews anybody, so the
     * API does not carry that surface. GET /documents/{document} above is the
     * one route an admin does use, to read a submitted scan.
     */
});

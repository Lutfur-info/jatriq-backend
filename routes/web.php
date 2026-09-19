<?php

use App\Http\Controllers\Web\RideBoardController;
use Illuminate\Support\Facades\Route;

/*
 * The public board. The site's front page is the list of rides somebody can
 * still book, and the ride they click through to - both readable with no
 * account, exactly like the `GET /api/rides` the mobile clients read, and
 * throttled by address for the same reason: there is nobody to key it to.
 *
 * Searching is `?from_stop_id=&to_stop_id=`, the same pair the API takes,
 * and paging is `?page=`. Neither is a route parameter, so the board and the
 * search are one URL.
 */
Route::middleware('throttle:rides-browse')->group(function (): void {
    Route::get('/', [RideBoardController::class, 'index'])->name('home');

    /*
     * Not a model-bound ride: a departed one has to answer 404 rather than
     * render, and the repository decides that in the same read that loads the
     * vehicle and the seat count. Binding would resolve the row first and ask
     * afterwards, at the cost of a second query.
     */
    Route::get('/rides/{ride}', [RideBoardController::class, 'show'])
        ->whereNumber('ride')
        ->name('rides.show');
});

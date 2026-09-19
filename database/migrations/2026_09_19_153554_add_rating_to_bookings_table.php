<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A passenger's score out of five for a trip she has taken.
 *
 * It lives on the **booking**, not on a table of its own, because the booking
 * already *is* the one row per passenger per ride - `unique(ride_id, user_id)`
 * gives "one rating per passenger per trip" for free, with nothing to keep in
 * step. It is the mirror of `status`: the driver's answer to her request, and
 * her answer to the trip.
 *
 * Both columns are nullable and stay null for every booking that exists
 * today. There is nothing to backfill - nobody has rated anything, and
 * inventing a score would be worse than having none.
 *
 * `rated_at` is when she scored it, not a second copy of whether she did;
 * re-rating moves it, because the later score is the one that counts.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // One to five. Unsigned tiny, because a score has no business
            // being wider than that, and the range is enforced above.
            $table->unsignedTinyInteger('rating')->nullable()->after('decided_at');

            $table->timestamp('rated_at')->nullable()->after('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rated_at']);
        });
    }
};

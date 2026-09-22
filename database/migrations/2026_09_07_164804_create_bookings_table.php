<?php

use App\enum\BookingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ride_id')->constrained()->cascadeOnDelete();

            // The passenger who booked. Only a Passenger may, so this is
            // never the ride's own driver.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Passenger seats taken on this ride. Booking again tops this up
            // rather than adding a second row.
            $table->unsignedTinyInteger('seats');

            /*
             * A booking is a **request the driver answers**, not a seat
             * taken: a driver is letting a stranger into their car, so a seat
             * is asked for and the driver says yes or no. Every new request
             * arrives on the default.
             */
            $table->enum('status', BookingStatus::names())
                ->default(BookingStatus::Pending->name);

            // When the driver answered; null while nobody has. Not a second
            // copy of the status - it is the record of when.
            $table->timestamp('decided_at')->nullable();

            /*
             * The passenger's score out of five for a trip she has taken. It
             * lives here rather than on a table of its own because the
             * booking already *is* the one row per passenger per ride, so
             * unique(ride_id, user_id) gives "one rating per passenger per
             * trip" for free, with nothing to keep in step. It is the mirror
             * of `status`: the driver's answer to her request, and hers to
             * the trip.
             *
             * One to five. Unsigned tiny, because a score has no business
             * being wider than that, and the range is enforced above.
             */
            $table->unsignedTinyInteger('rating')->nullable();

            // When she scored it, not a second copy of whether she did;
            // re-rating moves it, because the later score is the one that
            // counts.
            $table->timestamp('rated_at')->nullable();

            $table->timestamps();

            /*
             * One row per passenger per ride, which is what makes a second
             * booking a top-up. It is also the backstop against two
             * simultaneous requests each inserting a row for the same
             * passenger - the seat total is held by BookingService inside a
             * transaction, and this stops a race producing two rows to sum.
             */
            $table->unique(['ride_id', 'user_id']);

            /*
             * A driver's queue is "the requests waiting on me", which is a
             * status filter across the rides they own.
             */
            $table->index(['ride_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

<?php

use App\enum\BookingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A booking becomes a request the driver answers.
 *
 * Until now taking a seat was the whole transaction: a passenger posted and
 * the seat was hers. A driver is letting a stranger into their car, so a seat
 * is **asked for** now and the driver says yes or no.
 *
 * Every booking that already exists is backfilled to `Confirmed`, not
 * `Pending`. Those passengers were told their seats were booked and their
 * drivers were never asked, so putting them into a queue would retract a seat
 * somebody is already counting on. `decided_at` is backfilled to the row's
 * own `created_at`, which is literally when it was agreed under the old rule
 * - a null there would read as "still waiting".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Where every new request arrives. The rows already in the table
            // are moved off it immediately below.
            $table->enum('status', BookingStatus::names())
                ->default(BookingStatus::Pending->name)
                ->after('seats');

            // When the driver answered; null while nobody has. Not a second
            // copy of the status - it is the record of when.
            $table->timestamp('decided_at')->nullable()->after('status');

            /*
             * A driver's queue is "the requests waiting on me", which is a
             * status filter across the rides they own.
             */
            $table->index(['ride_id', 'status']);
        });

        DB::table('bookings')->update([
            'status' => BookingStatus::Confirmed->name,
            'decided_at' => DB::raw('created_at'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['ride_id', 'status']);
            $table->dropColumn(['status', 'decided_at']);
        });
    }
};

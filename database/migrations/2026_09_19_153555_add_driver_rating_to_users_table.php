<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The driver's score, derived from the trips passengers have rated.
 *
 * **Denormalised on purpose.** The public ride board shows a driver's rating
 * on every card, and it is the one read in the API that takes no token - so
 * it cannot afford to average a join across `bookings` for each row. These
 * two columns are read with a correlated subselect that never loads the
 * driver at all, which is also what keeps a name off a public response.
 *
 * Like the verification badge, this is **derived and never assigned**:
 * `RatingService::refreshFor()` recomputes both from the ratings themselves
 * after every write. Anything that sets them by hand will drift.
 *
 * `rating_average` is null until somebody rates, which is not the same as
 * zero - "no ratings yet" and "rated badly" must not read alike.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 3,2 holds 0.00 to 9.99, so a mean out of five fits with a
            // digit to spare and no float rounding anywhere near it.
            $table->decimal('rating_average', 3, 2)->nullable()->after('verified_at');

            // How many trips the mean is over. A 5.00 from one passenger and
            // a 4.6 from two hundred are not the same claim, so a client is
            // always given both.
            $table->unsignedInteger('ratings_count')->default(0)->after('rating_average');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rating_average', 'ratings_count']);
        });
    }
};

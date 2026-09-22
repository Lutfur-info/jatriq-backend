<?php

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
        Schema::create('rides', function (Blueprint $table) {
            $table->id();

            // Many rides per driver - unlike the one vehicle - so this is a
            // plain index rather than a unique key.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Which car the seats are in. A driver holds at most one vehicle
            // and POST /driver/vehicle overwrites that row instead of adding
            // another, so the reference stays valid across an edit.
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();

            /*
             * Which corridor the ride runs on. Resolved by RideService from
             * the stops the driver picked - the request never names a route,
             * exactly as it never names a vehicle.
             *
             * Nullable because a ride may be published with two free-text
             * ends and no corridor; such a ride still lists under an
             * unfiltered GET /rides but can never match a route search.
             *
             * restrictOnDelete rather than cascade: deleting a corridor or a
             * stop that rides are published against would delete the rides
             * and, through them, somebody's booking. Retire it with
             * is_active instead.
             */
            $table->foreignId('travel_route_id')->nullable()
                ->constrained()->restrictOnDelete();

            /*
             * Both ends of the trip: the stop the driver picked, and the
             * label beside it as a **snapshot** taken at publish time, so
             * renaming a stop never rewrites a trip somebody already agreed
             * to.
             *
             * The two stops' positions on that corridor are copied here too,
             * so the search is four integer comparisons against one table
             * instead of two joins through the pivot per ride. The trap that
             * follows is that RENUMBERING A ROUTE does not move these - which
             * is why sequences are seeded in tens, so inserting a stop never
             * needs a renumber.
             */
            $table->string('origin_label');
            $table->foreignId('origin_stop_id')->nullable()
                ->constrained('stops')->restrictOnDelete();
            $table->unsignedSmallInteger('origin_sequence')->nullable();

            $table->string('destination_label');
            $table->foreignId('destination_stop_id')->nullable()
                ->constrained('stops')->restrictOnDelete();
            $table->unsignedSmallInteger('destination_sequence')->nullable();

            // When the vehicle leaves the start point.
            $table->dateTime('departs_at');

            // Per passenger seat, in BDT.
            $table->decimal('seat_price', 10, 2);

            // Seats for sale on this ride, at most the vehicle's passenger
            // seats - a driver carrying cargo may offer fewer.
            $table->unsignedTinyInteger('seats_offered');

            $table->timestamps();

            // The query the driver's own upcoming list makes.
            $table->index(['user_id', 'departs_at']);

            // The passenger search: a corridor's upcoming rides, soonest first.
            $table->index(['travel_route_id', 'departs_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};

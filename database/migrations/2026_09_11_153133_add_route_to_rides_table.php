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
        Schema::table('rides', function (Blueprint $table) {
            /*
             * Which corridor the ride runs on, and the two stops it runs
             * between. Resolved by RideService from the stops the driver
             * picked - the request never names a route, exactly as it never
             * names a vehicle.
             *
             * Nullable only because rides published before corridors existed
             * have no stop to point at. Every ride written from here on sets
             * all five; a legacy ride still lists under an unfiltered
             * GET /rides but can never match a route search.
             *
             * restrictOnDelete rather than cascade: deleting a corridor or a
             * stop that rides are published against would delete the rides
             * and, through them, somebody's booking. Retire it with
             * is_active instead.
             */
            $table->foreignId('travel_route_id')->nullable()->after('vehicle_id')
                ->constrained()->restrictOnDelete();

            $table->foreignId('origin_stop_id')->nullable()->after('origin_place_id')
                ->constrained('stops')->restrictOnDelete();

            $table->foreignId('destination_stop_id')->nullable()->after('destination_place_id')
                ->constrained('stops')->restrictOnDelete();

            /*
             * The two stops' positions on that corridor, copied here at
             * publish time so the search is four integer comparisons against
             * one table instead of two joins through the pivot per ride.
             *
             * A snapshot, like the labels and coordinates beside them: it is
             * what the corridor looked like when the driver published. The
             * trap that follows is that RENUMBERING A ROUTE does not move
             * these - which is why sequences are seeded in tens, so
             * inserting a stop never needs a renumber.
             */
            $table->unsignedSmallInteger('origin_sequence')->nullable()->after('origin_stop_id');
            $table->unsignedSmallInteger('destination_sequence')->nullable()->after('destination_stop_id');

            // The passenger search: a corridor's upcoming rides, soonest first.
            $table->index(['travel_route_id', 'departs_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropIndex(['travel_route_id', 'departs_at']);

            $table->dropConstrainedForeignId('travel_route_id');
            $table->dropConstrainedForeignId('origin_stop_id');
            $table->dropConstrainedForeignId('destination_stop_id');

            $table->dropColumn(['origin_sequence', 'destination_sequence']);
        });
    }
};

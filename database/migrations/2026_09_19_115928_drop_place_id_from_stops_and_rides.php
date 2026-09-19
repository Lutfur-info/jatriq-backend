<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Google place id leaves the network, as the coordinates did before it.
 *
 * It was the last thing here that pointed at a map, and nothing ever read it:
 * a stop is found by its position along a corridor, and there has been no map
 * in either client since 2026-09-18. Nobody could fill one in either - an
 * admin typing `ChIJ...` into a form by hand is not a workable source.
 *
 * The rides columns go with it for the reason the coordinates' did: a ride's
 * place id was a **snapshot copied from the stop** by
 * `TravelRouteService::place()`, so with no place id on the stop there is no
 * source, and a nullable column nothing ever writes is worse than no column.
 * This changes the shape of `GET /api/rides` and friends - `origin.place_id`
 * and `destination.place_id` are gone rather than null.
 *
 * `down()` restores the columns but not the values; they were only ever a
 * copy of something that no longer exists.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stops', function (Blueprint $table) {
            $table->dropColumn('place_id');
        });

        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['origin_place_id', 'destination_place_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stops', function (Blueprint $table) {
            $table->string('place_id')->nullable()->after('name');
        });

        Schema::table('rides', function (Blueprint $table) {
            $table->string('origin_place_id')->nullable()->after('origin_label');
            $table->string('destination_place_id')->nullable()->after('destination_label');
        });
    }
};

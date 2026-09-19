<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coordinates leave the network entirely.
 *
 * They were only ever a map pin. Matching has always been done on a stop's
 * `sequence` along a corridor and never on distance, so nothing that decides
 * which rides a passenger sees is touched by this - see the stops migration's
 * note, which said as much when the column was added.
 *
 * The rides columns go with them because nothing could fill them any more: a
 * driver sends two stop ids and `TravelRouteService::place()` copied the
 * stop's coordinates onto the ride. With no coordinate on the stop there is
 * no source, and a nullable column nothing ever writes is worse than no
 * column.
 *
 * `place_id` stays on both. It is the Google reference a client may still
 * resolve for itself, and it was never derived from the two decimals.
 *
 * Irreversible in practice: `down()` puts the columns back, but the values
 * are gone and the columns are NOT NULL, so it restores the shape rather than
 * the data - hence the zeroes, and hence not pretending otherwise.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stops', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn([
                'origin_latitude',
                'origin_longitude',
                'destination_latitude',
                'destination_longitude',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stops', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->default(0)->after('district');
            $table->decimal('longitude', 11, 8)->default(0)->after('latitude');
        });

        Schema::table('rides', function (Blueprint $table) {
            $table->decimal('origin_latitude', 10, 8)->default(0)->after('origin_label');
            $table->decimal('origin_longitude', 11, 8)->default(0)->after('origin_latitude');
            $table->decimal('destination_latitude', 10, 8)->default(0)->after('destination_label');
            $table->decimal('destination_longitude', 11, 8)->default(0)->after('destination_latitude');
        });
    }
};

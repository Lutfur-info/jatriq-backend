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
             * Both ends of the trip: the label the driver picked out of
             * Google's place search, the coordinates behind it, and the place
             * id when the client sent one. Decimal rather than a spatial
             * POINT - see config/rides.php.
             *
             * Latitude spans -90..90 and longitude -180..180, hence one more
             * digit of precision on the longitude columns.
             */
            $table->string('origin_label');
            $table->decimal('origin_latitude', 10, 8);
            $table->decimal('origin_longitude', 11, 8);
            $table->string('origin_place_id')->nullable();

            $table->string('destination_label');
            $table->decimal('destination_latitude', 10, 8);
            $table->decimal('destination_longitude', 11, 8);
            $table->string('destination_place_id')->nullable();

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

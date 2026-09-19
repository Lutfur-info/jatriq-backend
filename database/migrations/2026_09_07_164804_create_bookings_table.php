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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ride_id')->constrained()->cascadeOnDelete();

            // The passenger who booked. Only a Passenger may, so this is
            // never the ride's own driver.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Passenger seats taken on this ride. Booking again tops this up
            // rather than adding a second row.
            $table->unsignedTinyInteger('seats');

            $table->timestamps();

            /*
             * One row per passenger per ride, which is what makes a second
             * booking a top-up. It is also the backstop against two
             * simultaneous requests each inserting a row for the same
             * passenger - the seat total is held by BookingService inside a
             * transaction, and this stops a race producing two rows to sum.
             */
            $table->unique(['ride_id', 'user_id']);
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

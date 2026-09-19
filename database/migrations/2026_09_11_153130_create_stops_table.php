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
        Schema::create('stops', function (Blueprint $table) {
            $table->id();

            /*
             * A named point a vehicle can be boarded or left at - a town on a
             * highway, not an arbitrary coordinate. One row per place in the
             * country, shared by every corridor that passes through it:
             * Cumilla sits on the Chattogram, Noakhali and Cox's Bazar runs
             * at three different sequences, and it must be the same stop in
             * all three or a search from Cumilla would only find one of them.
             */
            $table->string('name')->unique();

            // Which district it is in, purely so two same-named towns read
            // apart in a picker. Never matched on.
            $table->string('district')->nullable();

            /*
             * Where it is, for the map pin. Same decimal columns as the ride
             * carries and for the same reason - the suite runs on sqlite,
             * which has no spatial type. Matching is done on sequence, never
             * on distance, so nothing here is load bearing.
             */
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('place_id')->nullable();

            // Retiring a stop hides it from the pickers without breaking the
            // rides already published against it.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stops');
    }
};

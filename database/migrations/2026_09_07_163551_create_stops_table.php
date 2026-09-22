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
             *
             * There is nothing here that points at a map. Matching is done on
             * a stop's `sequence` along a corridor and never on distance, so
             * neither coordinates nor a Google place id would be read by
             * anything; a town is a name, a district and a switch.
             */
            $table->string('name')->unique();

            /*
             * Which district it is in, purely so two same-named towns read
             * apart in a picker. Never matched on.
             *
             * `nullOnDelete` rather than `restrictOnDelete`: a district is
             * decoration on a picker label, unlike a stop on a ride, so
             * losing one must never take a town off the network with it.
             */
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();

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

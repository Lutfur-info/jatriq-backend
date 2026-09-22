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
        Schema::create('travel_route_stop', function (Blueprint $table) {
            $table->id();

            $table->foreignId('travel_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stop_id')->constrained()->cascadeOnDelete();

            /*
             * Where the stop falls along the corridor, counted in ONE
             * canonical direction: outbound from Dhaka, so Dhaka is always
             * the lowest sequence on every route. A ride toward Dhaka simply
             * has a destination_sequence below its origin_sequence, which is
             * what tells the two directions apart at search time.
             *
             * Seeded in tens (10, 20, 30 ...) so a stop can be inserted
             * between two others without renumbering the route - renumbering
             * would silently invalidate the sequences already copied onto
             * published rides. See the rides migration.
             */
            $table->unsignedSmallInteger('sequence');

            $table->timestamps();

            // A stop appears at most once on a corridor, and two stops never
            // share a position on it.
            $table->unique(['travel_route_id', 'stop_id']);
            $table->unique(['travel_route_id', 'sequence']);

            // "Which corridors is this stop on?" - the first half of every
            // passenger search.
            $table->index('stop_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_route_stop');
    }
};

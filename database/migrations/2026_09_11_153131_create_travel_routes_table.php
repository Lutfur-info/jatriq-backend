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
        /*
         * Named `travel_routes` rather than `routes`: the model would
         * otherwise collide with Illuminate\Support\Facades\Route in every
         * file that touches both. The API still calls it a route - the
         * resource renames it on the way out.
         */
        Schema::create('travel_routes', function (Blueprint $table) {
            $table->id();

            // "Dhaka - Noakhali". Corridors, not divisions: Laksam and
            // Sonaimuri are on the Noakhali branch, which is not the
            // Dhaka - Chattogram division highway.
            $table->string('name')->unique();
            $table->string('slug')->unique();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_routes');
    }
};

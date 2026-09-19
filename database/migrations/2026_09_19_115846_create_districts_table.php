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
        Schema::create('districts', function (Blueprint $table) {
            $table->id();

            /*
             * One of the country's 64 districts. Until now this was a string
             * typed into each stop, so "Cumilla", "cumilla" and "Comilla"
             * were three different districts as far as anything could tell.
             *
             * It is still only there so two same-named towns read apart in a
             * picker - nothing matches on a district, and a ride knows
             * nothing about one.
             */
            $table->string('name')->unique();

            // Retiring one keeps it off the picker without touching the
            // stops already filed under it, exactly as a stop retires.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};

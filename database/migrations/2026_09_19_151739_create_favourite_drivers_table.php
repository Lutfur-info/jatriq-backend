<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The drivers a passenger wants to ride with again.
 *
 * A pivot and nothing more: both ends are `users`, because a driver and a
 * passenger are the same table with a different `role`. There is no model -
 * `User::favouriteDrivers()` is a `belongsToMany` over this - and no payload,
 * because a favourite is the fact that it exists.
 *
 * Both sides `cascadeOnDelete`: a favourite is meaningless once either
 * account is gone, and nothing else hangs off the row.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('favourite_drivers', function (Blueprint $table) {
            $table->id();

            // The passenger doing the favouriting.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /*
             * The driver favourited. Not named `favourite_user_id`, because
             * only a Driver is ever on this end - the endpoint refuses
             * anybody else - and the column should say so.
             */
            $table->foreignId('driver_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            /*
             * Favouriting twice is the same favourite. The unique key is
             * what makes the write idempotent rather than a check the
             * service has to remember, and it is the backstop against two
             * simultaneous taps inserting two rows.
             */
            $table->unique(['user_id', 'driver_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favourite_drivers');
    }
};

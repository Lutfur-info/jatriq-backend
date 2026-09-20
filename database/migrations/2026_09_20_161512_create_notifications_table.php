<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What somebody is told happened while they were not looking.
 *
 * Laravel's own shape, unaltered: a uuid key, the notification class in
 * `type`, the recipient in the `notifiable` morph, and the whole payload as
 * JSON in `data`. It is left alone deliberately - a column per fact would
 * have to grow with every kind of notification, and the API renders each one
 * from its own payload.
 *
 * The first thing to land here is a driver's answer to a request for seats.
 * A seat is asked for, not taken, so the passenger has been waiting on
 * exactly this - and she may not have the app open when it comes.
 *
 * `read_at` is null until she has seen it, which is what the unread count on
 * the bell is. Not a second copy of "delivered": the row existing is the
 * delivery.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            /*
             * Her feed is "mine, newest first", and the bell's count is the
             * unread of those. `morphs` already indexes the two notifiable
             * columns; this is what keeps the ordering off a filesort once
             * somebody has a few hundred.
             */
            $table->index(['notifiable_type', 'notifiable_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

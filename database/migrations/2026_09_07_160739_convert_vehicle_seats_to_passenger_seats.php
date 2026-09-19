<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `vehicles.seats` counted every seat including the driver's own; it now counts
 * passenger seats only, because what a rider needs to know is how many people
 * can travel. Rows written under the old meaning therefore read one too high.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Guarded rather than unconditional: the column is unsigned, so a row
        // already at the floor must not be driven below it if this is ever
        // replayed against converted data.
        DB::table('vehicles')->where('seats', '>', 1)->decrement('seats');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('vehicles')->increment('seats');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A stop points at a district instead of spelling one out.
 *
 * The column was free text, so nothing stopped the same district being
 * written three ways - and an admin had to know how the last one was spelled
 * to match it. Every distinct value already in the table becomes a row here,
 * so a live database keeps every district it had.
 *
 * `nullOnDelete` rather than `restrictOnDelete`: a district is decoration on
 * a picker label, unlike a stop on a ride, so losing one must never take a
 * town off the network with it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stops', function (Blueprint $table) {
            $table->foreignId('district_id')
                ->nullable()
                ->after('name')
                ->constrained()
                ->nullOnDelete();
        });

        $this->promoteExistingDistricts();

        Schema::table('stops', function (Blueprint $table) {
            $table->dropColumn('district');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stops', function (Blueprint $table) {
            $table->string('district')->nullable()->after('name');
        });

        /*
         * A correlated subquery rather than a join: sqlite has no
         * UPDATE ... JOIN, and the suite runs on sqlite. MySQL is happy with
         * it too, because the subquery reads a different table.
         */
        DB::table('stops')
            ->whereNotNull('district_id')
            ->update([
                'district' => DB::raw('(select name from districts where districts.id = stops.district_id)'),
            ]);

        Schema::table('stops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('district_id');
        });
    }

    /**
     * Turn every district already written on a stop into a row of its own.
     *
     * `firstOrCreate` by name rather than a bulk insert, because the
     * districts table may already be seeded - re-pointing a stop at the row
     * that exists is the whole point of doing this by name.
     */
    private function promoteExistingDistricts(): void
    {
        $names = DB::table('stops')
            ->whereNotNull('district')
            ->where('district', '!=', '')
            ->distinct()
            ->pluck('district');

        $now = now();

        foreach ($names as $name) {
            $id = DB::table('districts')->where('name', $name)->value('id');

            if ($id === null) {
                $id = DB::table('districts')->insertGetId([
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('stops')->where('district', $name)->update(['district_id' => $id]);
        }
    }
};

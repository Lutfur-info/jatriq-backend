<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Stop;
use App\Models\TravelRoute;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The highway corridors out of Dhaka and the towns along them.
 *
 * **Corridors, not divisions.** Seven of these do run Dhaka to a divisional
 * city, but Laksam and Sonaimuri sit on the Noakhali branch and Chakaria on
 * the Cox's Bazar continuation - neither is the Dhaka - Chattogram highway,
 * so a route set of one-per-division could not express a ride from either.
 *
 * **A stop belongs to every corridor it is on.** Cumilla is seeded once and
 * attached to Chattogram, Cox's Bazar and Noakhali at three different
 * sequences; Elenga to Rangpur and Rajshahi; Bhanga to Khulna and Barishal.
 * That sharing is the whole reason a Cumilla search finds rides coming down
 * all three roads.
 *
 * **Sequence runs outbound from Dhaka**, spaced in tens so a town can be
 * inserted between two others without renumbering - a renumber would not
 * reach the sequences already copied onto published rides.
 *
 * Idempotent: re-running re-files each town under its district and re-syncs
 * the orders rather than duplicating anything. It never detaches, because the admin panel
 * places towns on corridors too and seeding must not undo that.
 */
class TravelRouteSeeder extends Seeder
{
    /**
     * Every stop the corridors below draw on, and the district it is in.
     *
     * The district is written here as a **name**, which `DistrictSeeder` has
     * already put in the `districts` table - so the spellings on the right
     * have to match that list exactly, or the town is seeded with no district
     * at all. It is only there so two same-named towns read apart in a
     * picker; nothing matches on it.
     *
     * Coordinates were dropped on 2026-09-18 and the Google place id on
     * 2026-09-19. Nothing ever matched on either - a stop is found by its
     * position along a corridor.
     *
     * @var array<string, string>
     */
    private const STOPS = [
        // Dhaka and the first towns out of it.
        'Dhaka' => 'Dhaka',
        'Kanchpur' => 'Narayanganj',
        'Gazipur' => 'Gazipur',
        'Mawa' => 'Munshiganj',

        // N1, the Chattogram road - shared by Cox's Bazar and Noakhali as
        // far as Cumilla.
        'Daudkandi' => 'Cumilla',
        'Chandina' => 'Cumilla',
        'Cumilla' => 'Cumilla',
        'Feni' => 'Feni',
        'Mirsharai' => 'Chattogram',
        'Sitakunda' => 'Chattogram',
        'Chattogram' => 'Chattogram',

        // On to Cox's Bazar.
        'Patiya' => 'Chattogram',
        'Chakaria' => "Cox's Bazar",
        "Cox's Bazar" => "Cox's Bazar",

        // The Noakhali branch, which leaves the Chattogram road at Cumilla.
        'Lalmai' => 'Cumilla',
        'Bagmara' => 'Cumilla',
        'Laksam' => 'Cumilla',
        'Khila' => 'Cumilla',
        'Natherpetua' => 'Cumilla',
        'Bipulashar' => 'Cumilla',
        'Sonaimuri' => 'Noakhali',
        'Chowmuhani' => 'Noakhali',
        'Maijdee' => 'Noakhali',

        // N2, the Sylhet road.
        'Narsingdi' => 'Narsingdi',
        'Bhairab' => 'Kishoreganj',
        'Ashuganj' => 'Brahmanbaria',
        'Brahmanbaria' => 'Brahmanbaria',
        'Sarail' => 'Brahmanbaria',
        'Madhabpur' => 'Habiganj',
        'Shayestaganj' => 'Habiganj',
        'Sreemangal' => 'Moulvibazar',
        'Moulvibazar' => 'Moulvibazar',
        'Sylhet' => 'Sylhet',

        // N3, the Mymensingh road.
        'Bhaluka' => 'Mymensingh',
        'Trishal' => 'Mymensingh',
        'Mymensingh' => 'Mymensingh',

        // The north, over the Jamuna - shared by Rangpur and Rajshahi as far
        // as the Hatikumrul junction.
        'Tangail' => 'Tangail',
        'Elenga' => 'Tangail',
        'Sirajganj' => 'Sirajganj',
        'Hatikumrul' => 'Sirajganj',
        'Bogura' => 'Bogura',
        'Gaibandha' => 'Gaibandha',
        'Rangpur' => 'Rangpur',
        'Natore' => 'Natore',
        'Rajshahi' => 'Rajshahi',

        // The south-west, over the Padma - shared by Khulna and Barishal as
        // far as Bhanga.
        'Bhanga' => 'Faridpur',
        'Faridpur' => 'Faridpur',
        'Magura' => 'Magura',
        'Jhenaidah' => 'Jhenaidah',
        'Jashore' => 'Jashore',
        'Khulna' => 'Khulna',
        'Tekerhat' => 'Madaripur',
        'Gournadi' => 'Barishal',
        'Barishal' => 'Barishal',
    ];

    /**
     * Each corridor as `town => sequence`, counted **outbound from Dhaka**.
     *
     * The numbers are written out rather than derived from the position,
     * and that is the whole point: a sequence is **copied onto every ride
     * published on the corridor** and nothing goes back to correct it, so a
     * list whose numbering shifted when a town was inserted would silently
     * mis-position every ride already out there. Spelling them out means an
     * insertion is a new line in a gap and nothing else moves.
     *
     * Hence the tens. A town added between two others takes a number in the
     * gap - Natherpetua, Bipulashar and Khila sit at 62, 65 and 68 between
     * Laksam (60) and Sonaimuri (70), and Sonaimuri did not move. Only if a
     * gap is ever exhausted does a real renumber become necessary, and that
     * means re-placing the rides on that corridor in the same migration.
     *
     * @var array<string, array<string, int>>
     */
    private const ROUTES = [
        'Dhaka - Chattogram' => [
            'Dhaka' => 10,
            'Kanchpur' => 20,
            'Daudkandi' => 30,
            'Chandina' => 40,
            'Cumilla' => 50,
            'Feni' => 60,
            'Mirsharai' => 70,
            'Sitakunda' => 80,
            'Chattogram' => 90,
        ],

        "Dhaka - Cox's Bazar" => [
            'Dhaka' => 10,
            'Kanchpur' => 20,
            'Daudkandi' => 30,
            'Chandina' => 40,
            'Cumilla' => 50,
            'Feni' => 60,
            'Mirsharai' => 70,
            'Sitakunda' => 80,
            'Chattogram' => 90,
            'Patiya' => 100,
            'Chakaria' => 110,
            "Cox's Bazar" => 120,
        ],

        /*
         * The branch the worked example lives on: a ride from Sonaimuri
         * toward Dhaka passes Laksam, Cumilla, Chandina and Daudkandi.
         *
         * Natherpetua, Bipulashar and Khila were added between Laksam and
         * Sonaimuri and took 62, 65 and 68 out of the gap - which is why
         * Sonaimuri is still 70 and every ride already published on this
         * road still sits where it did.
         */
        'Dhaka - Noakhali' => [
            'Dhaka' => 10,
            'Kanchpur' => 20,
            'Daudkandi' => 30,
            'Chandina' => 40,
            'Cumilla' => 50,
            'Lalmai' => 60,
            'Bagmara' => 70,
            'Laksam' => 80,
            'Khila' => 90,
            'Natherpetua' => 100,
            'Bipulashar' => 110,
            'Sonaimuri' => 120,
            'Chowmuhani' => 130,
            'Maijdee' => 140,
        ],

        'Dhaka - Sylhet' => [
            'Dhaka' => 10,
            'Kanchpur' => 20,
            'Narsingdi' => 30,
            'Bhairab' => 40,
            'Ashuganj' => 50,
            'Brahmanbaria' => 60,
            'Sarail' => 70,
            'Madhabpur' => 80,
            'Shayestaganj' => 90,
            'Sreemangal' => 100,
            'Moulvibazar' => 110,
            'Sylhet' => 120,
        ],

        'Dhaka - Mymensingh' => [
            'Dhaka' => 10,
            'Gazipur' => 20,
            'Bhaluka' => 30,
            'Trishal' => 40,
            'Mymensingh' => 50,
        ],

        'Dhaka - Rangpur' => [
            'Dhaka' => 10,
            'Gazipur' => 20,
            'Tangail' => 30,
            'Elenga' => 40,
            'Sirajganj' => 50,
            'Hatikumrul' => 60,
            'Bogura' => 70,
            'Gaibandha' => 80,
            'Rangpur' => 90,
        ],

        'Dhaka - Rajshahi' => [
            'Dhaka' => 10,
            'Gazipur' => 20,
            'Tangail' => 30,
            'Elenga' => 40,
            'Sirajganj' => 50,
            'Hatikumrul' => 60,
            'Natore' => 70,
            'Rajshahi' => 80,
        ],

        'Dhaka - Khulna' => [
            'Dhaka' => 10,
            'Mawa' => 20,
            'Bhanga' => 30,
            'Faridpur' => 40,
            'Magura' => 50,
            'Jhenaidah' => 60,
            'Jashore' => 70,
            'Khulna' => 80,
        ],

        'Dhaka - Barishal' => [
            'Dhaka' => 10,
            'Mawa' => 20,
            'Bhanga' => 30,
            'Tekerhat' => 40,
            'Gournadi' => 50,
            'Barishal' => 60,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stops = $this->seedStops();

        foreach (self::ROUTES as $name => $order) {
            $route = TravelRoute::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );

            /*
             * The sequences come from the map, never from the position in
             * it - see the note on ROUTES for why deriving them would break
             * every ride already published on the corridor.
             *
             * syncWithoutDetaching() rather than attach(): re-running the
             * seeder after adding a town must add that row and leave the
             * rest alone, not collide on the unique
             * (travel_route_id, sequence) index.
             *
             * Without detaching, because this map is no longer the only
             * author of the pivot - an admin places towns on corridors from
             * /admin (StopResource), and a plain sync() would take every one
             * of them off the corridor the next time anybody seeded. The
             * cost is that deleting a line from ROUTES no longer detaches
             * that town on a re-run; do it in the panel, or retire the stop
             * with is_active, which is how a town is withdrawn anyway.
             */
            $pivot = [];

            foreach ($order as $stopName => $sequence) {
                $pivot[$stops[$stopName]->id] = ['sequence' => $sequence];
            }

            $route->stops()->syncWithoutDetaching($pivot);
        }
    }

    /**
     * Write every stop the corridors draw on, keyed by name.
     *
     * @return array<string, Stop>
     */
    private function seedStops(): array
    {
        $districts = District::query()->pluck('id', 'name');
        $stops = [];

        foreach (self::STOPS as $name => $district) {
            $stops[$name] = Stop::query()->updateOrCreate(
                ['name' => $name],
                [
                    /*
                     * Null rather than a row created on the spot: the 64
                     * districts are `DistrictSeeder`'s to define, and a
                     * misspelling here creating a 65th is exactly what
                     * moving the district into a table was meant to stop.
                     */
                    'district_id' => $districts[$district] ?? null,
                    'is_active' => true,
                ],
            );
        }

        return $stops;
    }
}

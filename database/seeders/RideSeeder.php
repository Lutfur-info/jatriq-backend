<?php

namespace Database\Seeders;

use App\enum\CabinClass;
use App\enum\VehicleModel;
use App\enum\VerificationStatus;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\Stop;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RideService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * A board worth searching: a driver who can actually publish, a passenger who
 * can actually book, and rides laid out to demonstrate every rule the
 * corridor search has.
 *
 * **Development data, not reference data.** TravelRouteSeeder seeds the roads
 * themselves and runs everywhere; this seeds traffic on them and belongs
 * beside the test accounts in DatabaseSeeder.
 *
 * Two things it fixes that otherwise make the feature untestable by hand:
 *
 * - the seeded driver has **no vehicle and no badge**, so `POST /driver/rides`
 *   refuses them - one with a 422 on `vehicle`, the other with a 403 from
 *   `verified.identity`;
 * - the seeded passenger has **no badge**, so booking a seat is refused the
 *   same way.
 *
 * Rides go through `RideService::offer()` rather than straight into the
 * table, so each one is placed on its corridor by the same code the API uses
 * - a ride seeded onto a road its stops do not share would be a fixture
 * proving nothing.
 *
 * Safe to re-run: it skips entirely once the driver has rides.
 */
class RideSeeder extends Seeder
{
    /**
     * The rides to publish: start, destination, and how far ahead it leaves.
     *
     * Chosen to make every rule visible from the app. On the Noakhali branch
     * the order outbound from Dhaka is Cumilla 50, Laksam 60, Natherpetua 62,
     * Bipulashar 65, Khila 68, Sonaimuri 70, Chowmuhani 80, Maijdee 90.
     *
     * @var array<int, array{string, string, int, string}>
     */
    private const RIDES = [
        // The worked example: found by a passenger boarding at Laksam,
        // Natherpetua, Bipulashar, Cumilla, Chandina or Daudkandi.
        ['Sonaimuri', 'Dhaka', 4, '650.00'],

        // The one that surprises people. It starts PAST Khila, so a
        // Khila -> Laksam search does not return it - while the Sonaimuri
        // ride above does.
        ['Bipulashar', 'Dhaka', 6, '400.00'],

        // Furthest out on the branch, so it serves every town below it.
        ['Maijdee', 'Dhaka', 26, '700.00'],

        // The same road the other way, so a Dhaka-bound search never sees it
        // and an outbound one sees nothing else.
        ['Dhaka', 'Maijdee', 50, '700.00'],

        // A different corridor sharing the road out of Dhaka as far as
        // Cumilla - so a Cumilla -> Dhaka search returns this one AND the
        // Noakhali ones, which is the whole point of sharing a stop.
        ['Chattogram', 'Dhaka', 8, '900.00'],
        ['Cumilla', 'Dhaka', 10, '350.00'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $driver = User::query()->where('msisdn', '1700000002')->first();
        $passenger = User::query()->where('msisdn', '1700000001')->first();

        if ($driver === null || $passenger === null) {
            $this->command?->warn('RideSeeder: seed the accounts first.');

            return;
        }

        if ($driver->rides()->exists()) {
            $this->command?->info('RideSeeder: rides already seeded, skipping.');

            return;
        }

        $this->readyToRide($driver, $passenger);

        $rides = $this->publish($driver);

        $this->fillOne($rides, $passenger);
        $this->sendOneOnItsWay($driver);

        $this->explain();
    }

    /**
     * Give the driver a car and both riders the identity badge.
     *
     * The badge is normally *derived* from reviewed documents by
     * VerificationService, never assigned - see `.ai/rules/services.md`. It is
     * assigned here because the alternative is seeding document files and an
     * admin review just to reach a screen, and this seeder never runs in
     * production.
     */
    private function readyToRide(User $driver, User $passenger): void
    {
        foreach ([$driver, $passenger] as $rider) {
            $rider->verification_status = VerificationStatus::Verified;
            $rider->verified_at = now();
            $rider->save();
        }

        if ($driver->vehicle === null) {
            $vehicle = new Vehicle;

            $vehicle->fill([
                'registration_number' => 'DHAKA METRO-GA-77-4242',
                'model' => VehicleModel::HiAce,
                'cabin_class' => CabinClass::Ac,
                'seats' => 11,
            ]);

            $vehicle->user()->associate($driver);
            $vehicle->save();

            $driver->refresh();
        }
    }

    /**
     * Publish the board, each ride placed by the service the API uses.
     *
     * @return array<string, Ride> keyed "Origin->Destination"
     */
    private function publish(User $driver): array
    {
        $rides = app(RideService::class);
        $published = [];

        foreach (self::RIDES as [$from, $to, $hours, $price]) {
            $published["{$from}->{$to}"] = $rides->offer($driver, [
                'origin_stop_id' => $this->stop($from),
                'destination_stop_id' => $this->stop($to),
                'departs_at' => Carbon::now()->addHours($hours),
                'seat_price' => $price,
                'seats_offered' => 4,
            ]);
        }

        return $published;
    }

    /**
     * Sell every seat on one ride, so the board has a case that is upcoming
     * and on the right road and *still* must not be offered.
     *
     * @param  array<string, Ride>  $rides
     */
    private function fillOne(array $rides, User $passenger): void
    {
        $ride = $rides['Cumilla->Dhaka'];

        $ride->seats_offered = 2;
        $ride->save();

        // Confirmed, not pending: this ride is here to demonstrate "full",
        // and a driver's queue is a different lesson. There is a pending one
        // on the Sonaimuri ride for that.
        Booking::factory()->for($ride)->ofSeats(2)->confirmed()->create([
            'user_id' => $passenger->id,
        ]);

        /*
         * One request nobody has answered, so the driver's queue has
         * something in it and the seat it holds is visibly off the market.
         */
        Booking::factory()->for($rides['Sonaimuri->Dhaka'])->ofSeats(1)->create([
            'user_id' => $passenger->id,
        ]);
    }

    /**
     * One ride that has already left, for the other half of the same rule.
     *
     * Written straight to the column rather than offered in the past, because
     * a departure behind the lead time is exactly what the API refuses.
     */
    private function sendOneOnItsWay(User $driver): void
    {
        $rides = app(RideService::class);

        $gone = $rides->offer($driver, [
            'origin_stop_id' => $this->stop('Chowmuhani'),
            'destination_stop_id' => $this->stop('Dhaka'),
            'departs_at' => Carbon::now()->addHour(),
            'seat_price' => '500.00',
            'seats_offered' => 4,
        ]);

        $gone->departs_at = Carbon::now()->subHours(2);
        $gone->save();
    }

    private function stop(string $name): int
    {
        return (int) Stop::query()->where('name', $name)->value('id');
    }

    /**
     * Print what to try, because a board of nine rides says nothing on its
     * own about which rule each one is there to show.
     */
    private function explain(): void
    {
        $this->command?->newLine();
        $this->command?->info(
            'Seeded 8 rides (6 bookable: Cumilla->Dhaka is full, '
            .'Chowmuhani->Dhaka has left). Try these on GET /api/rides:'
        );

        $this->command?->table(
            ['from -> to', 'expect'],
            [
                ['(no parameters)', '6 - the full one and the departed one are out'],
                ['Laksam -> Dhaka', '3: Sonaimuri, Bipulashar, Maijdee'],
                ['Khila -> Laksam', '2: Sonaimuri, Maijdee - NOT Bipulashar, which'],
                ['', '   starts past Khila and never goes there'],
                ['Natherpetua -> Laksam', '3: adds Bipulashar, which does pass it'],
                ['Cumilla -> Dhaka', '4: three Noakhali + Chattogram, one shared road'],
                ['Dhaka -> Laksam', '1: Dhaka->Maijdee - the only outbound ride'],
                ['Maijdee -> Dhaka', '1: itself - nothing starts further out'],
                ['Chowmuhani -> Dhaka', '1: Maijdee - the Chowmuhani ride has left'],
                ['Laksam -> Sylhet', '0 - no road runs between them'],
            ],
        );
    }
}

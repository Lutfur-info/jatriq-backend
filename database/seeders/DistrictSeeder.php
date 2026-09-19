<?php

namespace Database\Seeders;

use App\Models\District;
use Illuminate\Database\Seeder;

/**
 * The 64 districts of Bangladesh.
 *
 * Reference data, like the corridors: it runs on every environment and is
 * safe to re-run on a live one, because it matches on the name and only ever
 * creates what is missing. It never retires or renames a district - a live
 * spelling is the admin panel's to change, and a seeder that "corrected" one
 * would undo that on the next deploy.
 *
 * The list is the whole country and not only the districts the corridors
 * pass through, because an admin adding a town anywhere has to find its
 * district in the picker. That is the point of the table.
 *
 * Spellings are the current official ones - Cumilla, Chattogram, Bogura,
 * Jashore, Barishal - which are what `TravelRouteSeeder` files its towns
 * under. Seeding an older spelling as well would put the same district in
 * the picker twice, which is what this table exists to stop.
 */
class DistrictSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const DISTRICTS = [
        'Bagerhat',
        'Bandarban',
        'Barguna',
        'Barishal',
        'Bhola',
        'Bogura',
        'Brahmanbaria',
        'Chandpur',
        'Chapai Nawabganj',
        'Chattogram',
        'Chuadanga',
        "Cox's Bazar",
        'Cumilla',
        'Dhaka',
        'Dinajpur',
        'Faridpur',
        'Feni',
        'Gaibandha',
        'Gazipur',
        'Gopalganj',
        'Habiganj',
        'Jamalpur',
        'Jashore',
        'Jhalokathi',
        'Jhenaidah',
        'Joypurhat',
        'Khagrachhari',
        'Khulna',
        'Kishoreganj',
        'Kurigram',
        'Kushtia',
        'Lakshmipur',
        'Lalmonirhat',
        'Madaripur',
        'Magura',
        'Manikganj',
        'Meherpur',
        'Moulvibazar',
        'Munshiganj',
        'Mymensingh',
        'Naogaon',
        'Narail',
        'Narayanganj',
        'Narsingdi',
        'Natore',
        'Netrokona',
        'Nilphamari',
        'Noakhali',
        'Pabna',
        'Panchagarh',
        'Patuakhali',
        'Pirojpur',
        'Rajbari',
        'Rajshahi',
        'Rangamati',
        'Rangpur',
        'Satkhira',
        'Shariatpur',
        'Sherpur',
        'Sirajganj',
        'Sunamganj',
        'Sylhet',
        'Tangail',
        'Thakurgaon',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::DISTRICTS as $name) {
            District::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}

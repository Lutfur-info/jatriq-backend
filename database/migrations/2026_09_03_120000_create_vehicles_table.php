<?php

use App\enum\CabinClass;
use App\enum\VehicleModel;
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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            // One vehicle per driver: the unique key is what enforces it, so
            // the repository can create-or-replace without a race.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Two drivers cannot claim the same plate.
            $table->string('registration_number', 40)->unique();

            $table->enum('model', VehicleModel::names());
            $table->enum('cabin_class', CabinClass::names());

            // Passenger seats only - the driver's own seat is not counted,
            // because what a rider needs to know is how many people can
            // travel. A ride's `seats_offered` is bounded by this.
            $table->unsignedTinyInteger('seats');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

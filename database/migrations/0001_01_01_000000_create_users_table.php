<?php

use App\enum\Gender;
use App\enum\Role;
use App\enum\VerificationStatus;
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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('dial_code', 10)->default('00880');
            $table->string('msisdn', 15)->unique();
            $table->timestamp('msisdn_verified_at')->nullable();
            $table->enum('gender', Gender::names())->default(Gender::Male->name);
            $table->string('date_of_birth')->nullable();
            $table->enum('role', Role::names())->default(Role::Passenger->name)->index();
            $table->boolean('is_active')->default(false);

            /*
             * The badge is a fourth, separate flag: "msisdn_verified_at" says
             * the user owns the number, "is_active" says the account may be
             * used, and this says their identity documents have been reviewed
             * and accepted.
             */
            $table->enum('verification_status', VerificationStatus::names())
                ->default(VerificationStatus::Unverified->name)
                ->index();

            $table->timestamp('verified_at')->nullable();

            /*
             * The driver's score, **denormalised on purpose**. The public ride
             * board shows a rating on every card and is the one read in the
             * API that takes no token, so it cannot afford to average a join
             * across `bookings` per row. Both are read with a correlated
             * subselect that never loads the driver at all, which is also what
             * keeps a name off a public response.
             *
             * Like the verification badge, they are **derived and never
             * assigned**: `RatingService::refreshFor()` recomputes both from
             * the ratings themselves after every write. Anything that sets
             * them by hand will drift.
             *
             * 3,2 holds 0.00 to 9.99, so a mean out of five fits with a digit
             * to spare and no float rounding anywhere near it. It is null
             * until somebody rates, which is not the same as zero - "no
             * ratings yet" and "rated badly" must not read alike.
             */
            $table->decimal('rating_average', 3, 2)->nullable();

            // How many trips the mean is over. A 5.00 from one passenger and a
            // 4.6 from two hundred are not the same claim, so a client is
            // always given both.
            $table->unsignedInteger('ratings_count')->default(0);

            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('msisdn')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};

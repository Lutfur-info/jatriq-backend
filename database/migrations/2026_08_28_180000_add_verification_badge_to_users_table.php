<?php

use App\enum\VerificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The badge is a fourth, separate flag: "msisdn_verified_at" says the user
     * owns the number, "is_active" says the account may be used, and this says
     * their identity documents have been reviewed and accepted.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('verification_status', VerificationStatus::names())
                ->default(VerificationStatus::Unverified->name)
                ->index()
                ->after('is_active');

            $table->timestamp('verified_at')->nullable()->after('verification_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['verification_status', 'verified_at']);
        });
    }
};

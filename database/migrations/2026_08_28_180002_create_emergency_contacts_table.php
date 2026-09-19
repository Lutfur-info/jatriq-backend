<?php

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
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('relation', 50);
            $table->string('dial_code', 10)->default('00880');
            $table->string('msisdn', 15);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            // The same number may not be listed twice by one user.
            $table->unique(['user_id', 'msisdn']);
            $table->index(['user_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_contacts');
    }
};

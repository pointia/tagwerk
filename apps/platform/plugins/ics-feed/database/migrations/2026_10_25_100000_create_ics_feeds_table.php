<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ics_feeds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            // Null: the whole family's calendar.
            $table->foreignId('person_id')->nullable()->constrained('people')->cascadeOnDelete();
            $table->string('name');
            // sha256 of the secret in the address; null once the household export cleared it.
            $table->string('token_hash', 64)->nullable()->unique();
            $table->boolean('include_closures')->default(false);
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ics_feeds');
    }
};

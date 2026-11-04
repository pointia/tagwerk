<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            // The provider's id for the person, stable while the address may change.
            $table->string('subject');
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'subject']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_identities');
    }
};

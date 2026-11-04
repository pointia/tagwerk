<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 2048);
            // Encrypted; null once the household export cleared it.
            $table->text('secret')->nullable();
            $table->json('events');
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            // Stays the same over retries; the receiver can drop duplicates by it.
            $table->uuid('uuid')->unique();
            $table->string('event');
            $table->string('status', 16);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestampsTz();

            $table->index(['webhook_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};

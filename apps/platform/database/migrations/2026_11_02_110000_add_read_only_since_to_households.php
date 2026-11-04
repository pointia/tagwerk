<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A household that may still read but no longer write — an operator's
 * state like `suspended_at`, not a setting the family chooses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table): void {
            $table->timestamp('read_only_since')->nullable();
        });
    }
};

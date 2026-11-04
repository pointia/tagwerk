<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an account belongs is its memberships. The old columns were kept in
 * step for a release that no installation runs any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['household_id']);
            $table->dropForeign(['person_id']);
            $table->dropIndex(['household_id']);
            $table->dropColumn(['household_id', 'person_id']);
        });
    }
};

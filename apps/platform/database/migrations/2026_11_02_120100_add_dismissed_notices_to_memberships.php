<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The notice keys an account closed in this household. Per membership:
 * what one household tells is not closed in another.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->jsonb('dismissed_notices')->nullable();
        });
    }
};

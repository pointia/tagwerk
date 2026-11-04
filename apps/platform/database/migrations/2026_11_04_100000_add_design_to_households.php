<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The household's design pack (`vendor/name`); null is Tagwerk's own. A key
 * whose package is gone falls back to it as well.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table): void {
            $table->string('design')->nullable();
        });
    }
};

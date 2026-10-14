<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Origin, author and licence of archive images, so every published file can be traced back to its source. */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->json('provenance')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn('provenance');
        });
    }
};

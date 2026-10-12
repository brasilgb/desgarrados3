<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('story_submissions', function (Blueprint $table) {
            $table->boolean('publication_consent')->default(false);
            $table->boolean('display_name_consent')->default(false);
            $table->string('photo_path')->nullable();
            $table->string('photo_credit', 180)->nullable();
            $table->boolean('photo_publication_consent')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('story_submissions', function (Blueprint $table) {
            $table->dropColumn(['publication_consent', 'display_name_consent', 'photo_path', 'photo_credit', 'photo_publication_consent']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('story_submissions', function (Blueprint $table) {
            $table->string('photo_original_name', 180)->nullable();
            $table->timestamp('moderation_approved_at')->nullable();
        });

        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('source_submission_id')->nullable()->unique()->constrained('story_submissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_submission_id');
        });

        Schema::table('story_submissions', function (Blueprint $table) {
            $table->dropColumn(['photo_original_name', 'moderation_approved_at']);
        });
    }
};

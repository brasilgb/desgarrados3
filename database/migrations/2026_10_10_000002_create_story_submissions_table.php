<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->longText('story');
            $table->string('contributor_name', 120)->nullable();
            $table->string('origin_city', 120)->nullable();
            $table->string('origin_state', 2)->nullable();
            $table->boolean('rights_confirmed')->default(false);
            $table->string('status')->default('pending')->index();
            $table->text('moderation_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('publication_id')->nullable()->constrained('publications')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_submissions');
    }
};

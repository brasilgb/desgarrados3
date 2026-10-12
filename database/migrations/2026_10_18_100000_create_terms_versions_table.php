<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable snapshots of each text of the submission terms shown to contributors.
        Schema::create('terms_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('document', 60);
            $table->char('content_hash', 64);
            $table->string('label', 40);
            $table->string('source', 20);
            $table->string('title', 180);
            $table->longText('body');
            $table->timestamp('created_at')->nullable();
            $table->unique(['document', 'content_hash']);
        });

        Schema::table('story_submissions', function (Blueprint $table): void {
            $table->foreignId('terms_version_id')->nullable()->after('terms_version')->constrained('terms_versions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('story_submissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('terms_version_id');
        });

        Schema::dropIfExists('terms_versions');
    }
};

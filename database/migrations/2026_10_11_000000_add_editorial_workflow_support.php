<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->unsignedInteger('lock_version')->default(0);
            $table->index(['type', 'status', 'published_at'], 'publications_public_listing_index');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
        });

        Schema::create('publication_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restored_from_revision_id')->nullable()->constrained('publication_revisions')->nullOnDelete();
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['publication_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_revisions');

        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['width', 'height']);
        });

        Schema::table('publications', function (Blueprint $table) {
            $table->dropIndex('publications_public_listing_index');
            $table->dropColumn('lock_version');
        });
    }
};

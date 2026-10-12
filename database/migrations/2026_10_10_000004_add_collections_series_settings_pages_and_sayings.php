<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        Schema::create('publication_collection', function (Blueprint $table) {
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->primary(['publication_id', 'collection_id']);
        });

        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('publications', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->unsignedInteger('series_position')->nullable();
            $table->unique(['series_id', 'series_position']);
        });

        Schema::create('sayings', function (Blueprint $table) {
            $table->id();
            $table->text('phrase');
            $table->text('meaning');
            $table->string('context', 500)->nullable();
            $table->string('source_note', 255)->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('portal_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 500)->nullable();
            $table->longText('body');
            $table->string('status')->default('published')->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('story_submissions', function (Blueprint $table) {
            $table->string('contact_email')->nullable();
            $table->boolean('contact_consent')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('story_submissions', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'contact_consent']);
        });
        Schema::dropIfExists('site_pages');
        Schema::dropIfExists('portal_settings');
        Schema::dropIfExists('sayings');
        Schema::table('publications', function (Blueprint $table) {
            $table->dropUnique(['series_id', 'series_position']);
            $table->dropConstrainedForeignId('series_id');
            $table->dropColumn('series_position');
        });
        Schema::dropIfExists('series');
        Schema::dropIfExists('publication_collection');
        Schema::dropIfExists('collections');
    }
};

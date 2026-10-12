<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regional_integrations', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('name');
            $table->string('data_type');
            $table->string('source_url', 2048);
            $table->string('license')->nullable();
            $table->string('availability')->default('available');
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('records_count')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('next_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('territorial_units', function (Blueprint $table) {
            $table->unsignedBigInteger('ibge_code')->primary();
            $table->string('unit_type', 16);
            $table->string('name');
            $table->char('state_code', 2)->nullable()->index();
            $table->string('state_name')->nullable();
            $table->string('region_name')->nullable();
            $table->timestamps();
            $table->index(['unit_type', 'state_code', 'name']);
        });

        Schema::create('regional_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('provider_event_key', 64)->unique();
            $table->string('name');
            $table->date('date');
            $table->string('scope', 16);
            $table->char('state_code', 2)->nullable()->index();
            $table->boolean('optional')->nullable();
            $table->unsignedSmallInteger('reference_year')->index();
            $table->string('source_url', 2048);
            $table->timestamp('last_synced_at');
            $table->timestamps();
            $table->index(['date', 'state_code']);
        });

        Schema::create('zarc_records', function (Blueprint $table) {
            $table->id();
            $table->string('dataset_key', 80);
            $table->string('row_hash', 64);
            $table->unsignedSmallInteger('season_start')->index();
            $table->unsignedSmallInteger('season_end');
            $table->string('culture');
            $table->string('state_code', 2)->index();
            $table->string('municipality_code', 12)->index();
            $table->string('municipality');
            $table->json('source_row');
            $table->timestamps();
            $table->unique(['dataset_key', 'row_hash']);
            $table->index(['season_start', 'season_end', 'state_code', 'municipality', 'culture'], 'zarc_lookup_idx');
        });

        Schema::table('production_guides', function (Blueprint $table) {
            $table->char('state_code', 2)->nullable()->after('region')->index();
        });
    }

    public function down(): void
    {
        Schema::table('production_guides', function (Blueprint $table) {
            $table->dropColumn('state_code');
        });
        Schema::dropIfExists('zarc_records');
        Schema::dropIfExists('regional_holidays');
        Schema::dropIfExists('territorial_units');
        Schema::dropIfExists('regional_integrations');
    }
};

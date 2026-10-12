<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rural_calendar_entries', function (Blueprint $table) {
            $table->id();
            $table->string('activity');
            $table->string('category');
            $table->string('activity_type')->default('agriculture');
            $table->string('frequency')->default('seasonal');
            $table->string('region');
            $table->char('state', 2);
            $table->string('municipality')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reference_period')->nullable();
            $table->string('source_name');
            $table->string('source_url', 2048);
            $table->date('verified_at');
            $table->text('notes')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['state', 'municipality', 'starts_on', 'ends_on'],
                'rural_calendar_location_dates_idx');
            $table->index(['category', 'activity_type', 'is_published']);
        });

        Schema::create('regional_events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('municipality');
            $table->char('state', 2);
            $table->string('venue')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('organizer')->nullable();
            $table->string('source_name');
            $table->string('source_url', 2048);
            $table->date('verified_at');
            $table->string('additional_url', 2048)->nullable();
            $table->string('status')->default('draft')->index();
            $table->boolean('is_featured')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['state', 'municipality', 'starts_on', 'status']);
            $table->index(['category', 'status', 'is_featured']);
        });

        Schema::create('production_guides', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category');
            $table->string('region')->nullable();
            $table->string('summary', 500)->nullable();
            $table->longText('body');
            $table->string('source_name')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->date('reference_date')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_guides');
        Schema::dropIfExists('regional_events');
        Schema::dropIfExists('rural_calendar_entries');
    }
};

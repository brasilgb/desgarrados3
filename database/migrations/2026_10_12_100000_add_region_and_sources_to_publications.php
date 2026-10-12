<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->string('origin_scope', 16)->default('regional')->index();
            $table->unsignedBigInteger('municipality_code')->nullable()->index();
            $table->json('source_references')->nullable();
            $table->foreign('municipality_code')->references('ibge_code')->on('territorial_units')->nullOnDelete();
        });
        DB::table('publications')->whereNotNull('category_id')->orderBy('id')->get(['id', 'category_id'])->each(fn ($publication) => DB::table('publication_category')->insertOrIgnore(['publication_id' => $publication->id, 'category_id' => $publication->category_id]));
        DB::table('publications')->whereNotNull('origin_city')->whereNotNull('origin_state')->orderBy('id')->get(['id', 'origin_city', 'origin_state'])->each(function ($publication): void {
            $municipality = DB::table('territorial_units')->where('unit_type', 'municipality')->where('state_code', strtoupper($publication->origin_state))->whereRaw('LOWER(name) = ?', [mb_strtolower($publication->origin_city)])->first(['ibge_code']);
            if ($municipality) {
                DB::table('publications')->where('id', $publication->id)->update(['origin_scope' => 'municipality', 'municipality_code' => $municipality->ibge_code]);
            }
        });
        DB::table('publications')->whereNull('origin_city')->whereNotNull('origin_state')->update(['origin_scope' => 'state']);
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropForeign(['municipality_code']);
            $table->dropColumn(['origin_scope', 'municipality_code', 'source_references']);
        });
    }
};

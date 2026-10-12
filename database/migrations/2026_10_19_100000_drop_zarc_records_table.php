<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('zarc_records')) {
            if (DB::table('zarc_records')->exists() && ! config('database.zarc_cleanup_authorized')) {
                throw new RuntimeException('A tabela zarc_records contém dados. Faça backup e defina DB_ZARC_CLEANUP_AUTHORIZED=true para autorizar a remoção.');
            }
            Schema::drop('zarc_records');
        }

        if (Schema::hasTable('regional_integrations')) {
            DB::table('regional_integrations')->where('key', 'mapa_zarc')->delete();
        }

        if (Schema::hasTable('cache')) {
            DB::table('cache')->where('key', 'like', '%rural-calendar:zarc:%')->orWhere('key', 'like', '%my-region:zarc-cultures:%')->delete();
        }
    }

    public function down(): void
    {
        // Only the empty structure is restored; the ZARC data is consulted on the official MAPA panel.
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
    }
};

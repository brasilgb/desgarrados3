<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProductionGuide;
use App\Models\Publication;
use App\Models\RegionalEvent;
use App\Models\RegionalHoliday;
use App\Models\RegionalIntegration;
use App\Models\RuralCalendarEntry;
use App\Models\TerritorialUnit;
use App\Models\User;
use App\Models\ZarcRecord;
use Database\Seeders\CulturalArchiveSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyRegionTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_selector_uses_official_catalogue_and_exposes_no_inferred_data(): void
    {
        $this->catalogue();

        $this->get('/minha-regiao')->assertOk()->assertInertia(fn ($page) => $page
            ->component('portal/my-region')
            ->where('filters.state', '')
            ->where('regionData', null)
            ->has('states', 2)
            ->where('states.0.code', 'RS'));

        $this->get('/minha-regiao?state=RS')->assertOk()->assertInertia(fn ($page) => $page
            ->where('municipalities.0.code', '4300406')
            ->where('municipalities.0.name', 'Alegrete'));

        $this->get('/minha-regiao?state=SC&municipality_code=4300406')->assertNotFound();
    }

    public function test_selected_region_only_shows_verified_local_and_state_records(): void
    {
        $this->catalogue();
        RegionalIntegration::create(['key' => 'mapa_zarc', 'name' => 'ZARC', 'data_type' => 'dataset', 'source_url' => 'https://www.gov.br/agricultura', 'last_synced_at' => now()]);
        ZarcRecord::create(['dataset_key' => 'safra-2026', 'row_hash' => str_repeat('a', 64), 'season_start' => 2026, 'season_end' => 2027, 'culture' => 'Soja', 'state_code' => 'RS', 'municipality_code' => '4300406', 'municipality' => 'Alegrete', 'source_row' => ['Portaria' => 'Portaria 1']]);
        RuralCalendarEntry::create(['activity' => 'Arroz irrigado', 'category' => 'Arroz', 'activity_type' => 'agriculture', 'frequency' => 'seasonal', 'region' => 'Fronteira Oeste', 'state' => 'RS', 'municipality' => 'Alegrete', 'starts_on' => today()->addDays(4), 'ends_on' => today()->addDays(12), 'reference_period' => 'Safra 2026/2027', 'source_name' => 'MAPA', 'source_url' => 'https://www.gov.br/agricultura', 'verified_at' => today(), 'is_published' => true]);
        RegionalEvent::create(['name' => 'Feira de Alegrete', 'slug' => 'feira-alegrete', 'category' => 'Feira', 'municipality' => 'Alegrete', 'state' => 'RS', 'starts_on' => today()->addDays(10), 'source_name' => 'Prefeitura', 'source_url' => 'https://example.org', 'verified_at' => today(), 'status' => 'published']);
        RegionalEvent::create(['name' => 'Evento de outro município', 'slug' => 'evento-bage', 'category' => 'Feira', 'municipality' => 'Bagé', 'state' => 'RS', 'starts_on' => today()->addDays(10), 'source_name' => 'Prefeitura', 'source_url' => 'https://example.org', 'verified_at' => today(), 'status' => 'published']);
        RegionalHoliday::create(['provider' => 'brasilapi', 'provider_event_key' => str_repeat('b', 64), 'name' => 'Feriado nacional', 'date' => today()->addDays(20), 'scope' => 'national', 'reference_year' => today()->year, 'source_url' => 'https://brasilapi.com.br', 'last_synced_at' => now()]);
        RegionalHoliday::create(['provider' => 'brasilapi', 'provider_event_key' => str_repeat('c', 64), 'name' => 'Feriado de SC', 'date' => today()->addDays(20), 'scope' => 'state', 'state_code' => 'SC', 'reference_year' => today()->year, 'source_url' => 'https://brasilapi.com.br', 'last_synced_at' => now()]);
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Histórias', 'slug' => 'historias']);
        Publication::create(['user_id' => $user->id, 'category_id' => $category->id, 'title' => 'Memória de Alegrete', 'slug' => 'memoria-alegrete', 'body' => '<p>Memória local</p>', 'type' => 'historia', 'origin_city' => 'Alegrete', 'origin_state' => 'RS', 'status' => 'published', 'published_at' => now()]);
        Publication::create(['user_id' => $user->id, 'title' => 'Memória de Bagé', 'slug' => 'memoria-bage', 'body' => '<p>Outra localidade</p>', 'type' => 'historia', 'origin_city' => 'Bagé', 'origin_state' => 'RS', 'status' => 'published', 'published_at' => now()]);
        Publication::create(['user_id' => $user->id, 'title' => 'Memória estadual', 'slug' => 'memoria-estadual', 'body' => '<p>Referência estadual</p>', 'type' => 'literatura', 'origin_scope' => 'state', 'origin_state' => 'RS', 'status' => 'published', 'published_at' => now()]);
        Publication::create(['user_id' => $user->id, 'title' => 'Memória regional', 'slug' => 'memoria-regional', 'body' => '<p>Referência regional</p>', 'type' => 'literatura', 'origin_scope' => 'regional', 'status' => 'published', 'published_at' => now()]);
        ProductionGuide::create(['title' => 'Orientação estadual', 'slug' => 'orientacao-estadual', 'category' => 'Agricultura', 'body' => '<p>Conteúdo</p>', 'state_code' => 'RS', 'status' => 'published']);

        $this->get('/minha-regiao?state=RS&municipality_code=4300406')->assertOk()->assertInertia(fn ($page) => $page
            ->where('regionData.municipality.name', 'Alegrete')
            ->where('regionData.cultures', ['Soja'])
            ->has('regionData.activities', 1)->where('regionData.activities.0.activity', 'Arroz irrigado')
            ->has('regionData.events', 1)->where('regionData.events.0.name', 'Feira de Alegrete')
            ->has('regionData.holidays', 1)->where('regionData.holidays.0.name', 'Feriado nacional')
            ->has('regionData.stories', 1)->where('regionData.stories.0.title', 'Memória de Alegrete')
            ->has('regionData.stateStories', 1)->where('regionData.stateStories.0.title', 'Memória estadual')
            ->has('regionData.regionalStories', 1)->where('regionData.regionalStories.0.title', 'Memória regional')
            ->has('regionData.stateGuides', 1)->where('regionData.stateGuides.0.title', 'Orientação estadual'));
    }

    public function test_cultural_archive_drafts_have_institutional_sources_and_are_not_public(): void
    {
        $this->seed(CulturalArchiveSeeder::class);
        $drafts = Publication::query()->whereIn('slug', [
            'sao-miguel-arcanjo-vestigios-memoria-guarani',
            'santa-catarina-diversidade-patrimonio-protegido',
            'caminhos-historicos-fandango-caicara-parana',
        ])->get();

        $this->assertCount(3, $drafts);
        foreach ($drafts as $draft) {
            $this->assertSame('draft', $draft->status);
            $this->assertNotEmpty($draft->source_references);
            $this->assertNull($draft->published_at);
            $this->get('/publicacao/'.$draft->slug)->assertNotFound();
        }
    }

    private function catalogue(): void
    {
        TerritorialUnit::create(['ibge_code' => 43, 'unit_type' => 'state', 'name' => 'Rio Grande do Sul', 'state_code' => 'RS', 'state_name' => 'Rio Grande do Sul', 'region_name' => 'Sul']);
        TerritorialUnit::create(['ibge_code' => 42, 'unit_type' => 'state', 'name' => 'Santa Catarina', 'state_code' => 'SC', 'state_name' => 'Santa Catarina', 'region_name' => 'Sul']);
        TerritorialUnit::create(['ibge_code' => 4300406, 'unit_type' => 'municipality', 'name' => 'Alegrete', 'state_code' => 'RS', 'state_name' => 'Rio Grande do Sul', 'region_name' => 'Sul']);
    }
}

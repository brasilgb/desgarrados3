<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ProductionGuide;
use App\Models\RegionalEvent;
use App\Models\RuralCalendarEntry;
use App\Models\TerritorialUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegionalCalendarAndAgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_zarc_detail_cards_keep_light_surfaces_dark_text_and_wrap_long_values(): void
    {
        $page = file_get_contents(resource_path('js/pages/portal/rural-calendar.tsx'));

        $this->assertIsString($page);
        $this->assertStringContainsString('bg-[#fbf8f1] text-[#28271f]', $page);
        $this->assertStringContainsString('bg-white p-3 text-sm', $page);
        $this->assertStringContainsString('text-[#625f55]', $page);
        $this->assertStringContainsString('block break-all font-medium text-[#28271f]', $page);
        $this->assertStringContainsString('Identificadores técnicos e códigos do MAPA', $page);
        $this->assertStringContainsString('<details className="overflow-hidden rounded-xl', $page);
    }

    public function test_home_uses_shared_pagination_with_translated_previous_and_next_labels(): void
    {
        $home = file_get_contents(resource_path('js/pages/portal/home.tsx'));
        $pagination = file_get_contents(resource_path('js/components/admin-pagination.tsx'));

        $this->assertIsString($home);
        $this->assertIsString($pagination);
        $this->assertStringContainsString('<AdminPagination links={publications.links} label="Paginação das histórias" />', $home);
        $this->assertStringContainsString("return 'Anterior'", $pagination);
        $this->assertStringContainsString("return 'Próxima'", $pagination);
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user->refresh();
    }

    public function test_public_calendar_filters_by_month_and_omits_unpublished_records(): void
    {
        RuralCalendarEntry::create(['activity' => 'Arroz irrigado', 'category' => 'Arroz', 'activity_type' => 'agriculture', 'frequency' => 'seasonal', 'region' => 'Fronteira Oeste', 'state' => 'RS', 'municipality' => 'Alegrete', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-20', 'reference_period' => 'Safra 2026/2027', 'source_name' => 'MAPA', 'source_url' => 'https://www.gov.br/mapa', 'verified_at' => '2026-10-01', 'is_published' => true]);
        RuralCalendarEntry::create(['activity' => 'Soja interna', 'category' => 'Soja', 'activity_type' => 'agriculture', 'frequency' => 'seasonal', 'region' => 'Missões', 'state' => 'RS', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-20', 'source_name' => 'MAPA', 'source_url' => 'https://www.gov.br/mapa', 'verified_at' => '2026-10-01', 'is_published' => false]);

        $this->get('/calendario-rural?month=2026-10&municipality=Alegrete')->assertOk()->assertInertia(fn ($page) => $page->component('portal/rural-calendar')->has('entries', 1)->where('entries.0.activity', 'Arroz irrigado'));
        $this->get('/calendario-rural?month=2026-11')->assertOk()->assertInertia(fn ($page) => $page->has('entries', 0));
        $this->get('/calendario-rural?month=2026-13')->assertSessionHasErrors('month');
    }

    public function test_public_calendar_defaults_to_porto_alegre_and_filters_zarc_location(): void
    {
        TerritorialUnit::create(['ibge_code' => 43, 'unit_type' => 'state', 'name' => 'Rio Grande do Sul', 'state_code' => 'RS', 'state_name' => 'Rio Grande do Sul']);
        TerritorialUnit::create(['ibge_code' => 4314902, 'unit_type' => 'municipality', 'name' => 'Porto Alegre', 'state_code' => 'RS', 'state_name' => 'Rio Grande do Sul']);
        RuralCalendarEntry::create(['activity' => 'Horta urbana', 'category' => 'Hortaliças', 'activity_type' => 'agriculture', 'frequency' => 'seasonal', 'region' => 'Metropolitana', 'state' => 'RS', 'municipality' => 'Porto Alegre', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-20', 'source_name' => 'Emater', 'source_url' => 'https://example.org', 'verified_at' => '2026-10-01', 'is_published' => true]);
        RuralCalendarEntry::create(['activity' => 'Arroz', 'category' => 'Arroz', 'activity_type' => 'agriculture', 'frequency' => 'seasonal', 'region' => 'Fronteira', 'state' => 'RS', 'municipality' => 'Alegrete', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-20', 'source_name' => 'Emater', 'source_url' => 'https://example.org', 'verified_at' => '2026-10-01', 'is_published' => true]);

        $this->get('/calendario-rural?month=2026-10')->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.state', 'RS')->where('filters.municipality_code', '4314902')
            ->where('selectedMunicipality.name', 'Porto Alegre')->has('entries', 1)->where('entries.0.activity', 'Horta urbana'));
    }

    public function test_reverse_geocoder_is_only_called_after_explicit_location_submission_and_returns_catalogued_place(): void
    {
        config(['services.reverse_geocoder.url' => 'https://geocoder.example/reverse']);
        TerritorialUnit::create(['ibge_code' => 43, 'unit_type' => 'state', 'name' => 'Rio Grande do Sul', 'state_code' => 'RS', 'state_name' => 'Rio Grande do Sul']);
        TerritorialUnit::create(['ibge_code' => 4314902, 'unit_type' => 'municipality', 'name' => 'Porto Alegre', 'state_code' => 'RS', 'state_name' => 'Rio Grande do Sul']);
        Http::fake(['geocoder.example/*' => Http::response(['address' => ['city' => 'Porto Alegre', 'state_code' => 'RS']], 200)]);

        $this->postJson('/calendario-rural/localizacao', ['latitude' => -30.03, 'longitude' => -51.23])
            ->assertOk()->assertExactJson(['code' => '4314902', 'name' => 'Porto Alegre', 'state' => 'RS']);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://geocoder.example/reverse?') && (float) $request['lat'] === -30.03 && (float) $request['lon'] === -51.23);
    }

    public function test_public_agenda_and_guides_hide_drafts_and_require_source_fields_to_publish(): void
    {
        $event = RegionalEvent::create(['name' => 'Feira pública', 'slug' => 'feira-publica', 'category' => 'Feira agropecuária', 'municipality' => 'Bagé', 'state' => 'RS', 'starts_on' => '2026-10-20', 'source_name' => 'Organização', 'source_url' => 'https://example.org', 'verified_at' => '2026-10-01', 'status' => 'published']);
        RegionalEvent::create(['name' => 'Evento privado', 'slug' => 'evento-privado', 'category' => 'Evento cultural', 'municipality' => 'Bagé', 'state' => 'RS', 'starts_on' => '2026-10-21', 'source_name' => 'Organização', 'source_url' => 'https://example.org', 'verified_at' => '2026-10-01', 'status' => 'draft']);
        $draft = ProductionGuide::create(['title' => 'Guia privado', 'slug' => 'guia-privado', 'category' => 'Agricultura', 'body' => '<p>Rascunho</p>', 'status' => 'draft']);

        $this->get('/agenda-regional?month=2026-10&state=RS')->assertOk()->assertInertia(fn ($page) => $page->has('events.data', 1)->where('events.data.0.name', 'Feira pública'));
        $this->get('/agenda-regional/evento-privado')->assertNotFound();
        $this->get('/nossa-terra/producao')->assertOk()->assertInertia(fn ($page) => $page->has('guides.data', 0));
        $this->get('/nossa-terra/producao/'.$draft->slug)->assertNotFound();
        $this->assertSame('feira-publica', $event->slug);
    }

    public function test_public_agenda_pagination_preserves_month_and_state_filters(): void
    {
        foreach (range(1, 19) as $index) {
            RegionalEvent::create([
                'name' => "Evento {$index}", 'slug' => "evento-{$index}", 'category' => 'Feira',
                'municipality' => 'Bagé', 'state' => 'RS', 'starts_on' => '2026-10-20',
                'source_name' => 'Organização', 'source_url' => 'https://example.org',
                'verified_at' => '2026-10-01', 'status' => 'published',
            ]);
        }

        $this->get('/agenda-regional?month=2026-10&state=RS&page=2')->assertOk()->assertInertia(fn ($page) => $page
            ->where('events.current_page', 2)
            ->has('events.data', 1)
            ->where('events.data.0.name', 'Evento 19')
            ->where('events.links.0.url', fn ($url) => str_contains($url, 'month=2026-10') && str_contains($url, 'state=RS') && str_contains($url, 'page=1')));
    }

    public function test_admin_can_duplicate_event_as_draft_and_invalid_period_is_rejected(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $event = RegionalEvent::create(['name' => 'Festa da colheita', 'slug' => 'festa-colheita', 'category' => 'Festa municipal', 'municipality' => 'Caxias do Sul', 'state' => 'RS', 'starts_on' => '2026-10-20', 'source_name' => 'Prefeitura', 'source_url' => 'https://example.org', 'verified_at' => '2026-10-01', 'status' => 'published', 'is_featured' => true]);

        $this->actingAs($admin)->post('/admin/regional-events/'.$event->id.'/duplicate')->assertRedirect();
        $this->assertDatabaseHas('regional_events', ['name' => 'Festa da colheita (nova edição)', 'status' => 'draft', 'is_featured' => false]);
        $this->actingAs($admin)->post('/admin/rural-calendar', ['activity' => 'Trigo', 'category' => 'Trigo', 'activity_type' => 'agriculture', 'frequency' => 'seasonal', 'region' => 'RS', 'state' => 'RS', 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-01', 'source_name' => 'MAPA', 'source_url' => 'https://www.gov.br/mapa', 'verified_at' => '2026-10-01', 'is_published' => true])->assertSessionHasErrors('ends_on');
        $this->assertDatabaseCount('rural_calendar_entries', 0);
    }

    public function test_published_guides_require_a_reference_and_sanitize_html(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $payload = ['title' => 'Manejo de pomar', 'category' => 'Fruticultura', 'body' => '<p>Informação <script>alert(1)</script></p>', 'status' => 'published'];
        $this->actingAs($admin)->post('/admin/production-guides', $payload)->assertSessionHasErrors(['source_name', 'source_url', 'reference_date']);

        $this->post('/admin/production-guides', [...$payload, 'source_name' => 'Embrapa', 'source_url' => 'https://www.embrapa.br', 'reference_date' => '2026-10-01'])->assertRedirect();
        $this->assertDatabaseHas('production_guides', ['slug' => 'manejo-de-pomar', 'status' => 'published', 'body' => '<p>Informação </p>']);
    }
}

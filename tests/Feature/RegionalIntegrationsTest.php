<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ProductionGuide;
use App\Models\RegionalIntegration;
use App\Models\TerritorialUnit;
use App\Models\User;
use App\Services\RegionalIntegrations\IntegrationCatalog;
use App\Services\RegionalIntegrations\RegionalIntegrationSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class RegionalIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user->refresh();
    }

    public function test_ibge_sync_validates_and_persists_official_states_and_municipalities(): void
    {
        $states = [];
        for ($index = 1; $index <= 27; $index++) {
            $states[] = ['id' => $index, 'sigla' => 'A'.chr(64 + $index), 'nome' => 'Estado '.$index, 'regiao' => ['nome' => 'Sul']];
        }
        $states[26] = ['id' => 43, 'sigla' => 'RS', 'nome' => 'Rio Grande do Sul', 'regiao' => ['nome' => 'Sul']];
        $cities = [];
        for ($index = 1; $index <= 5000; $index++) {
            $cities[] = ['id' => 4300000 + $index, 'nome' => 'Município '.$index, 'microrregiao' => ['mesorregiao' => ['UF' => ['id' => 43]]]];
        }
        Http::fake([
            'servicodados.ibge.gov.br/api/v1/localidades/estados*' => Http::response($states),
            'servicodados.ibge.gov.br/api/v1/localidades/municipios*' => Http::response($cities),
        ]);

        $result = app(RegionalIntegrationSync::class)->run('ibge_locations');

        $this->assertSame(5027, $result['count']);
        $this->assertDatabaseHas('territorial_units', ['ibge_code' => 43, 'unit_type' => 'state', 'state_code' => 'RS']);
        $this->assertDatabaseHas('territorial_units', ['ibge_code' => 4300001, 'unit_type' => 'municipality', 'name' => 'Município 1', 'state_code' => 'RS']);
        $this->assertDatabaseHas('regional_integrations', ['key' => 'ibge_locations', 'records_count' => 5027]);
    }

    public function test_ibge_failure_keeps_last_valid_locality_catalogue(): void
    {
        app(IntegrationCatalog::class)->ensure();
        TerritorialUnit::create(['ibge_code' => 43, 'unit_type' => 'state', 'name' => 'Rio Grande do Sul', 'state_code' => 'RS', 'state_name' => 'Rio Grande do Sul']);
        Http::fake(['*localidades/estados*' => Http::response(['error' => 'temporarily unavailable'], 503)]);

        try {
            app(RegionalIntegrationSync::class)->run('ibge_locations');
            $this->fail('A resposta HTTP de erro deveria falhar a sincronização.');
        } catch (RequestException $exception) {
            $this->assertStringContainsString('503', $exception->getMessage());
        }

        $this->assertDatabaseHas('territorial_units', ['ibge_code' => 43, 'state_code' => 'RS']);
        $this->assertNotNull(RegionalIntegration::query()->whereKey('ibge_locations')->value('last_error'));
    }

    public function test_brasilapi_sync_upserts_national_and_state_holidays_without_reclassifying_unknowns(): void
    {
        Http::fake(function (Request $request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $state = $query['uf'] ?? 'RS';
            $date = str_contains($request->url(), '/2027') ? '2027-01-01' : '2026-01-01';

            return Http::response([
                ...array_map(fn ($day) => ['date' => str_replace('-01-01', '-01-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT), $date), 'name' => 'Feriado nacional '.$day, 'type' => 'national'], range(1, 8)),
                ['date' => str_replace('-01-01', '-09-20', $date), 'name' => 'Data Estadual '.$state, 'type' => 'state'],
            ]);
        });

        app(RegionalIntegrationSync::class)->run('brasilapi_holidays');
        app(RegionalIntegrationSync::class)->run('brasilapi_holidays');

        $this->assertDatabaseHas('regional_holidays', ['name' => 'Feriado nacional 1', 'scope' => 'national', 'state_code' => null, 'optional' => null]);
        $this->assertDatabaseHas('regional_holidays', ['name' => 'Data Estadual RS', 'scope' => 'state', 'state_code' => 'RS']);
        $this->assertSame(22, (int) RegionalIntegration::query()->whereKey('brasilapi_holidays')->value('records_count'));
        $this->get('/agenda-regional?month=2026-09&state=RS&category=Feriado%20estadual')->assertOk()->assertInertia(fn ($page) => $page->has('holidays', 1)->where('holidays.0.name', 'Data Estadual RS'));
    }

    public function test_zarc_import_filters_south_records_and_preserves_last_valid_data_on_format_change(): void
    {
        $downloadCount = 0;
        $catalogCount = 0;
        Http::fake(function (Request $request) use (&$downloadCount, &$catalogCount) {
            if (str_contains($request->url(), 'package_show')) {
                $catalogCount++;

                return Http::response($this->zarcCatalog($catalogCount === 1 ? '2026-10-10' : '2026-10-11'));
            }
            $downloadCount++;

            return Http::response($downloadCount === 1 ? $this->zarcCsv('Soja', 'RS', '4300034', 'Alegrete') : str_repeat('colunas-alteradas;', 10));
        });
        app(RegionalIntegrationSync::class)->run('mapa_zarc');
        $this->assertDatabaseHas('zarc_records', ['culture' => 'Soja', 'state_code' => 'RS', 'municipality' => 'Alegrete', 'season_start' => 2026]);
        $previousId = (int) DB::table('zarc_records')->value('id');

        try {
            app(RegionalIntegrationSync::class)->run('mapa_zarc');
            $this->fail('O CSV alterado deveria ter sido rejeitado.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('formato do CSV ZARC mudou', $exception->getMessage());
        }

        $this->assertDatabaseHas('zarc_records', ['id' => $previousId, 'culture' => 'Soja']);
        $this->assertDatabaseHas('regional_integrations', ['key' => 'mapa_zarc']);
        $this->assertNotNull(RegionalIntegration::query()->whereKey('mapa_zarc')->value('last_error'));
    }

    public function test_integration_settings_are_admin_only_and_unknown_credentials_are_not_requested(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $this->actingAs($admin)->get('/admin/integrations')->assertOk()->assertInertia(fn ($page) => $page->component('admin/integrations/index')->has('integrations', 6));
        $this->actingAs($admin)->put('/admin/integrations/ibge_locations', ['enabled' => false])->assertRedirect();
        $this->assertDatabaseHas('regional_integrations', ['key' => 'ibge_locations', 'enabled' => false]);
        $this->assertSame(1, RegionalIntegration::query()->whereKey('embrapa_agroapi')->where('availability', 'credentials_and_paid_tier')->count());

        $editor = $this->userWithRole(UserRole::Editor);
        $this->actingAs($editor)->get('/admin/integrations')->assertForbidden();
    }

    public function test_public_production_guides_filter_by_official_state_catalogue(): void
    {
        ProductionGuide::create(['title' => 'Guia RS', 'slug' => 'guia-rs', 'category' => 'Agricultura', 'state_code' => 'RS', 'body' => '<p>RS</p>', 'status' => 'published']);
        ProductionGuide::create(['title' => 'Guia SC', 'slug' => 'guia-sc', 'category' => 'Agricultura', 'state_code' => 'SC', 'body' => '<p>SC</p>', 'status' => 'published']);
        app(IntegrationCatalog::class)->ensure();
        TerritorialUnit::create(['ibge_code' => 43, 'unit_type' => 'state', 'name' => 'Rio Grande do Sul', 'state_code' => 'RS', 'state_name' => 'Rio Grande do Sul']);
        TerritorialUnit::create(['ibge_code' => 42, 'unit_type' => 'state', 'name' => 'Santa Catarina', 'state_code' => 'SC', 'state_name' => 'Santa Catarina']);

        $this->get('/nossa-terra/producao?state=RS&category=Agricultura')->assertOk()->assertInertia(fn ($page) => $page->has('guides.data', 1)->where('guides.data.0.title', 'Guia RS')->where('states', ['RS', 'SC']));
    }

    /** @return array<string, mixed> */
    private function zarcCatalog(string $modified = '2026-10-10'): array
    {
        return ['success' => true, 'result' => ['resources' => [['id' => 'resource-2026', 'name' => 'Tábua de risco - Safra 2026/2027', 'format' => 'CSV', 'url' => 'https://dados.agricultura.gov.br/2026.csv', 'metadata_modified' => $modified]]]];
    }

    private function zarcCsv(string $culture, string $state, string $code, string $municipality): string
    {
        $headers = ['Nome_cultura', 'SafraIni', 'SafraFin', 'geocodigo', 'UF', 'municipio', 'Portaria', ...array_map(fn ($index) => 'dec'.$index, range(1, 36))];
        $values = [$culture, '2026', '2027', $code, $state, $municipality, 'Portaria 1', ...array_fill(0, 36, '1')];

        return implode(';', $headers)."\n".implode(';', $values)."\n";
    }
}

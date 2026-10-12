<?php

namespace App\Http\Controllers;

use App\Models\RegionalIntegration;
use App\Models\RuralCalendarEntry;
use App\Models\TerritorialUnit;
use App\Models\ZarcRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class RuralCalendarController extends Controller
{
    public function reverseGeocode(Request $request): JsonResponse
    {
        $coordinates = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $endpoint = config('services.reverse_geocoder.url');
        abort_unless(is_string($endpoint) && filter_var($endpoint, FILTER_VALIDATE_URL), 503, 'Serviço de localização indisponível.');

        try {
            $result = Http::acceptJson()->timeout(5)->get($endpoint, [
                'format' => 'jsonv2', 'lat' => $coordinates['latitude'], 'lon' => $coordinates['longitude'], 'zoom' => 10,
            ])->throw()->json();
        } catch (\Throwable) {
            return response()->json(['message' => 'Não foi possível identificar o município.'], 502);
        }

        $address = is_array($result) ? ($result['address'] ?? []) : [];
        $city = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? $address['county'] ?? null;
        $state = isset($address['state_code']) ? strtoupper(preg_replace('/^BR-/', '', $address['state_code'])) : null;
        if (! $state && ! empty($address['state'])) {
            $state = TerritorialUnit::query()->where('unit_type', 'state')->whereRaw('LOWER(state_name) = ?', [mb_strtolower($address['state'])])->value('state_code');
        }
        $municipality = $city && $state ? TerritorialUnit::query()->where('unit_type', 'municipality')->where('state_code', $state)->whereRaw('LOWER(name) = ?', [mb_strtolower($city)])->first(['ibge_code', 'name', 'state_code']) : null;

        return $municipality
            ? response()->json(['code' => (string) $municipality->ibge_code, 'name' => $municipality->name, 'state' => $municipality->state_code])
            : response()->json(['message' => 'Município não identificado com segurança.'], 422);
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'state' => ['nullable', 'string', 'size:2'],
            'scope' => ['nullable', 'in:municipality,state,regional'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'municipality_code' => ['nullable', 'regex:/^\d{7}$/'],
            'region' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'activity_type' => ['nullable', 'in:agriculture,livestock,production'],
            'season' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'zarc_soil' => ['nullable', 'string', 'max:20'],
            'zarc_cycle' => ['nullable', 'string', 'max:20'],
            'zarc_risk' => ['nullable', 'string', 'max:20'],
        ]);
        // A first visit is local by default. Broader scopes require an explicit choice.
        if (! isset($filters['scope']) && ! isset($filters['state']) && ! isset($filters['municipality']) && ! isset($filters['municipality_code'])) {
            $defaultMunicipality = TerritorialUnit::query()->where('unit_type', 'municipality')->where('ibge_code', '4314902')->first(['ibge_code', 'state_code']);
            if ($defaultMunicipality) {
                $filters['state'] = $defaultMunicipality->state_code;
                $filters['municipality_code'] = (string) $defaultMunicipality->ibge_code;
                $filters['scope'] = 'municipality';
            }
        }
        if (($filters['scope'] ?? null) === 'regional') {
            unset($filters['state'], $filters['municipality'], $filters['municipality_code']);
        } elseif (($filters['scope'] ?? null) === 'state') {
            unset($filters['municipality'], $filters['municipality_code']);
        }
        $month = Carbon::createFromFormat('!Y-m-d', ($filters['month'] ?? now()->format('Y-m')).'-01');
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();
        $municipalityName = $filters['municipality'] ?? null;
        if (! $municipalityName && isset($filters['municipality_code'])) {
            $municipalityName = TerritorialUnit::query()->where('unit_type', 'municipality')->where('ibge_code', $filters['municipality_code'])->value('name');
        }
        $selectedMunicipality = isset($filters['municipality_code'])
            ? TerritorialUnit::query()->where('unit_type', 'municipality')->where('ibge_code', $filters['municipality_code'])->first(['ibge_code', 'name', 'state_code'])
            : null;

        $entries = RuralCalendarEntry::query()->where('is_published', true)
            ->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start)
            ->when($filters['state'] ?? null, fn ($query, $state) => $query->where('state', $state))
            ->when($municipalityName, fn ($query, $municipality) => $query->where('municipality', $municipality))
            ->when($filters['region'] ?? null, fn ($query, $region) => $query->where('region', $region))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['activity_type'] ?? null, fn ($query, $type) => $query->where('activity_type', $type))
            ->orderBy('starts_on')->orderBy('activity')->get();

        $seasonalReferences = RuralCalendarEntry::query()->where('is_published', true)
            ->whereDate('ends_on', '>=', today())->whereDate('starts_on', '<=', today()->addYear())
            ->when($filters['state'] ?? null, fn ($query, $state) => $query->where('state', $state))
            ->when($municipalityName, fn ($query, $municipality) => $query->where('municipality', $municipality))
            ->when($filters['region'] ?? null, fn ($query, $region) => $query->where('region', $region))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['activity_type'] ?? null, fn ($query, $type) => $query->where('activity_type', $type))
            ->orderBy('starts_on')->orderBy('activity')->limit(100)->get();

        $zarcVersion = RegionalIntegration::query()->whereKey('mapa_zarc')->value('last_synced_at') ?? 'never';
        $zarcCacheKey = 'rural-calendar:zarc:v3:'.hash('sha256', json_encode([$filters, $request->integer('page', 1), $zarcVersion], JSON_THROW_ON_ERROR));
        $page = $request->integer('page', 1);
        $zarcPage = Cache::remember($zarcCacheKey, now()->addMinutes(15), function () use ($filters, $municipalityName, $page): array {
            $query = ZarcRecord::query()
                ->when($filters['state'] ?? null, fn ($query, $state) => $query->where('state_code', $state))
                ->when($municipalityName, fn ($query, $municipality) => $query->where('municipality', $municipality))
                ->when($filters['category'] ?? null, fn ($query, $culture) => $query->where('culture', $culture))
                ->when($filters['season'] ?? null, function ($query, $season): void {
                    [$start, $end] = array_map('intval', explode('-', $season));
                    $query->where('season_start', $start)->where('season_end', $end);
                })
                ->when($filters['zarc_soil'] ?? null, fn ($query, $value) => $this->whereSourceValue($query, 'Cod_Solo', $value))
                ->when($filters['zarc_cycle'] ?? null, fn ($query, $value) => $this->whereSourceValue($query, 'Cod_Ciclo', $value))
                ->when($filters['zarc_risk'] ?? null, fn ($query, $value) => $this->whereSourceValue($query, 'Cod_NM', $value));
            $total = (clone $query)->count();
            $data = $query->orderByDesc('season_start')->orderBy('culture')->orderBy('municipality')->forPage($page, 20)->get()->toArray();

            return ['data' => $data, 'total' => $total];
        });
        $zarcRecords = new LengthAwarePaginator($zarcPage['data'], $zarcPage['total'], 20, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $catalogueExists = TerritorialUnit::query()->where('unit_type', 'municipality')->exists();
        $catalogueStates = TerritorialUnit::query()->where('unit_type', 'state')
            ->orderByRaw("CASE state_code WHEN 'RS' THEN 0 WHEN 'SC' THEN 1 WHEN 'PR' THEN 2 ELSE 3 END")
            ->orderBy('name')->pluck('state_code')->all();
        $catalogueMunicipalities = TerritorialUnit::query()->where('unit_type', 'municipality')->when($filters['state'] ?? null, fn ($query, $state) => $query->where('state_code', $state))->orderBy('name')->get(['ibge_code', 'name'])->map(fn ($unit) => ['code' => (string) $unit->ibge_code, 'name' => $unit->name])->all();
        $categories = RuralCalendarEntry::query()->where('is_published', true)->distinct()->orderBy('category')->pluck('category')->merge(ZarcRecord::query()->distinct()->orderBy('culture')->pluck('culture'))->unique()->values();
        $seasons = ZarcRecord::query()->selectRaw('season_start, season_end')->distinct()->orderByDesc('season_start')->get()->map(fn ($record) => $record->season_start.'-'.$record->season_end)->values();

        return Inertia::render('portal/rural-calendar', [
            'month' => $month->format('Y-m'),
            'entries' => $entries,
            'seasonalReferences' => $seasonalReferences,
            'zarcRecords' => $zarcRecords,
            'zarcSyncedAt' => RegionalIntegration::query()->whereKey('mapa_zarc')->value('last_synced_at'),
            'zarcLastError' => RegionalIntegration::query()->whereKey('mapa_zarc')->value('last_error'),
            'seasons' => $seasons,
            'filters' => $filters,
            'selectedMunicipality' => $selectedMunicipality ? ['code' => (string) $selectedMunicipality->ibge_code, 'name' => $selectedMunicipality->name, 'state' => $selectedMunicipality->state_code] : null,
            'geolocationAvailable' => filled(config('services.reverse_geocoder.url')),
            'geolocationProvider' => parse_url((string) config('services.reverse_geocoder.url'), PHP_URL_HOST) ?: null,
            'states' => $catalogueExists ? $catalogueStates : RuralCalendarEntry::query()->where('is_published', true)->distinct()->orderBy('state')->pluck('state'),
            'municipalities' => $catalogueMunicipalities,
            'regions' => RuralCalendarEntry::query()->where('is_published', true)->distinct()->orderBy('region')->pluck('region'),
            'categories' => $categories,
            'officialSources' => [
                ['name' => 'MAPA · ZARC do Rio Grande do Sul', 'url' => 'https://www.gov.br/agricultura/pt-br/assuntos/riscos-seguro/programa-nacional-de-zoneamento-agricola-de-risco-climatico/portarias/safra-vigente/rio-grande-do-sul', 'updated_at' => '2026-09-22', 'description' => 'Portarias da safra vigente; consultar município, ciclo, solo e nível de risco.'],
                ['name' => 'Painel oficial de indicação de riscos do ZARC', 'url' => 'https://mapa-indicadores.agricultura.gov.br/publico/extensions/Zarc/Zarc.html', 'updated_at' => null, 'description' => 'Consulta oficial das indicações por cultura e localidade.'],
            ],
        ]);
    }

    /** @param Builder<ZarcRecord> $query */
    private function whereSourceValue(Builder $query, string $key, string $value): void
    {
        $path = '$."'.$key.'"';
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $query->whereRaw('json_extract(source_row, ?) = ?', [$path, $value]);
        } else {
            $query->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(source_row, ?)) = ?', [$path, $value]);
        }
    }
}

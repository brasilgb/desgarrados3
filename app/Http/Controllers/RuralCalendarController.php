<?php

namespace App\Http\Controllers;

use App\Models\RuralCalendarEntry;
use App\Models\TerritorialUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class RuralCalendarController extends Controller
{
    public function municipalities(Request $request): JsonResponse
    {
        $filters = $request->validate(['state' => ['nullable', 'string', 'size:2']]);
        $states = TerritorialUnit::query()->where('unit_type', 'state')->orderBy('name')->get(['state_code', 'name']);
        $municipalities = isset($filters['state'])
            ? TerritorialUnit::query()->where('unit_type', 'municipality')->where('state_code', strtoupper($filters['state']))
                ->orderBy('name')->get(['ibge_code', 'name', 'state_code'])
            : collect();

        return response()->json([
            'states' => $states->map(fn ($state) => ['code' => $state->state_code, 'name' => $state->name]),
            'municipalities' => $municipalities->map(fn ($unit) => ['code' => (string) $unit->ibge_code, 'name' => $unit->name, 'state' => $unit->state_code]),
        ]);
    }

    public function reverseGeocode(Request $request): JsonResponse
    {
        $coordinates = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $endpoint = config('services.reverse_geocoder.url');
        abort_unless(is_string($endpoint) && filter_var($endpoint, FILTER_VALIDATE_URL), 503, 'Serviço de localização indisponível.');

        try {
            $result = Http::acceptJson()->withUserAgent(config('app.name').' location lookup ('.config('app.url').')')->timeout(8)->get($endpoint, [
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

        $catalogueExists = TerritorialUnit::query()->where('unit_type', 'municipality')->exists();
        $catalogueStates = TerritorialUnit::query()->where('unit_type', 'state')
            ->orderByRaw("CASE state_code WHEN 'RS' THEN 0 WHEN 'SC' THEN 1 WHEN 'PR' THEN 2 ELSE 3 END")
            ->orderBy('name')->pluck('state_code')->all();
        $catalogueMunicipalities = TerritorialUnit::query()->where('unit_type', 'municipality')->when($filters['state'] ?? null, fn ($query, $state) => $query->where('state_code', $state))->orderBy('name')->get(['ibge_code', 'name'])->map(fn ($unit) => ['code' => (string) $unit->ibge_code, 'name' => $unit->name])->all();
        $categories = RuralCalendarEntry::query()->where('is_published', true)->distinct()->orderBy('category')->pluck('category');

        return Inertia::render('portal/rural-calendar', [
            'month' => $month->format('Y-m'),
            'entries' => $entries,
            'seasonalReferences' => $seasonalReferences,
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
                ['name' => 'Painel oficial de indicação de riscos do ZARC', 'url' => 'https://www.gov.br/agricultura/pt-br/assuntos/riscos-seguro/programa-nacional-de-zoneamento-agricola-de-risco-climatico/painel-de-indicacao-de-riscos-1', 'updated_at' => null, 'description' => 'Consulta oficial das indicações por cultura e localidade.'],
            ],
        ]);
    }
}

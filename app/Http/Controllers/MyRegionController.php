<?php

namespace App\Http\Controllers;

use App\Models\ProductionGuide;
use App\Models\Publication;
use App\Models\RegionalEvent;
use App\Models\RegionalHoliday;
use App\Models\RegionalIntegration;
use App\Models\RuralCalendarEntry;
use App\Models\TerritorialUnit;
use App\Models\ZarcRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class MyRegionController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'state' => ['nullable', 'string', 'size:2'],
            'municipality_code' => ['nullable', 'regex:/^\d{7}$/'],
        ]);
        if (! isset($filters['state']) && ! isset($filters['municipality_code'])) {
            $defaultMunicipality = TerritorialUnit::query()->where('unit_type', 'municipality')->where('ibge_code', '4314902')->first(['ibge_code', 'state_code']);
            if ($defaultMunicipality) {
                $filters['state'] = $defaultMunicipality->state_code;
                $filters['municipality_code'] = (string) $defaultMunicipality->ibge_code;
            }
        }
        $stateCode = strtoupper($filters['state'] ?? '');
        $municipality = null;
        if (isset($filters['municipality_code'])) {
            $municipality = TerritorialUnit::query()
                ->where('unit_type', 'municipality')
                ->where('ibge_code', $filters['municipality_code'])
                ->when($stateCode !== '', fn ($query) => $query->where('state_code', $stateCode))
                ->first();
            abort_unless($municipality !== null, 404);
            $stateCode = $municipality->state_code;
        }

        $states = TerritorialUnit::query()->where('unit_type', 'state')
            ->orderByRaw("CASE state_code WHEN 'RS' THEN 0 WHEN 'SC' THEN 1 WHEN 'PR' THEN 2 ELSE 3 END")
            ->orderBy('name')->get(['state_code', 'name'])->map(fn ($state) => ['code' => $state->state_code, 'name' => $state->name])->all();
        $municipalities = $stateCode === '' ? [] : TerritorialUnit::query()
            ->where('unit_type', 'municipality')->where('state_code', $stateCode)->orderBy('name')
            ->get(['ibge_code', 'name'])->map(fn ($unit) => ['code' => (string) $unit->ibge_code, 'name' => $unit->name])->all();

        $data = null;
        if ($municipality) {
            $code = (string) $municipality->ibge_code;
            $name = $municipality->name;
            $from = today()->toDateString();
            $until = today()->addYear()->toDateString();
            $zarcSyncedAt = RegionalIntegration::query()->whereKey('mapa_zarc')->value('last_synced_at');
            $cultures = Cache::remember('my-region:zarc-cultures:'.$code.':'.($zarcSyncedAt ?? 'never'), now()->addMinutes(30), fn () => ZarcRecord::query()
                ->where('state_code', $stateCode)->where('municipality_code', $code)->distinct()->orderBy('culture')->pluck('culture')->all());

            $data = [
                'municipality' => ['name' => $name, 'code' => $code, 'state_code' => $stateCode, 'state_name' => $municipality->state_name, 'region_name' => $municipality->region_name],
                'cultures' => $cultures,
                'zarcSyncedAt' => $zarcSyncedAt,
                'zarcLastError' => RegionalIntegration::query()->whereKey('mapa_zarc')->value('last_error'),
                'activities' => RuralCalendarEntry::query()->where('is_published', true)->where('state', $stateCode)->where('municipality', $name)->whereDate('ends_on', '>=', $from)->orderBy('starts_on')->limit(12)->get(),
                'events' => RegionalEvent::query()->where('status', 'published')->where('state', $stateCode)->where('municipality', $name)->whereRaw('COALESCE(ends_on, starts_on) >= ?', [$from])->orderBy('starts_on')->limit(12)->get(),
                'holidays' => RegionalHoliday::query()->whereBetween('date', [$from, $until])->where(fn ($query) => $query->whereNull('state_code')->orWhere('state_code', $stateCode))->orderBy('date')->limit(24)->get(),
                'stories' => Publication::query()->with(['author:id,name', 'category:id,name,slug', 'coverMedia:id,path,alt_text,credit,width,height'])->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now())->where(fn ($query) => $query->where('municipality_code', $code)->orWhere(fn ($legacy) => $legacy->whereNull('municipality_code')->whereRaw('LOWER(origin_city) = ?', [mb_strtolower($name)])->whereRaw('UPPER(origin_state) = ?', [$stateCode])))->latest('published_at')->limit(6)->get(['id', 'title', 'slug', 'excerpt', 'type', 'origin_scope', 'origin_city', 'origin_state', 'municipality_code', 'published_at', 'user_id', 'category_id', 'cover_path']),
                'stateStories' => Publication::query()->with(['author:id,name', 'category:id,name,slug', 'coverMedia:id,path,alt_text,credit,width,height'])->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now())->where('origin_state', $stateCode)->whereNull('origin_city')->whereNull('municipality_code')->latest('published_at')->limit(6)->get(['id', 'title', 'slug', 'excerpt', 'type', 'origin_scope', 'origin_city', 'origin_state', 'municipality_code', 'published_at', 'user_id', 'category_id', 'cover_path']),
                'regionalStories' => Publication::query()->with(['author:id,name', 'category:id,name,slug', 'coverMedia:id,path,alt_text,credit,width,height'])->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now())->where('origin_scope', 'regional')->whereNull('origin_state')->whereNull('municipality_code')->latest('published_at')->limit(6)->get(['id', 'title', 'slug', 'excerpt', 'type', 'origin_scope', 'origin_city', 'origin_state', 'municipality_code', 'published_at', 'user_id', 'category_id', 'cover_path']),
                'stateGuides' => ProductionGuide::query()->where('status', 'published')->where(fn ($query) => $query->whereNull('state_code')->orWhere('state_code', $stateCode))->orderBy('category')->orderBy('title')->limit(8)->get(['id', 'title', 'slug', 'category', 'state_code', 'summary', 'source_name', 'source_url', 'reference_date']),
            ];
        }

        return Inertia::render('portal/my-region', [
            'states' => $states,
            'municipalities' => $municipalities,
            'filters' => ['state' => $stateCode, 'municipality_code' => $filters['municipality_code'] ?? ''],
            'regionData' => $data,
        ]);
    }
}

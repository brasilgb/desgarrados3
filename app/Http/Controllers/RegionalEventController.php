<?php

namespace App\Http\Controllers;

use App\Models\RegionalEvent;
use App\Models\RegionalHoliday;
use App\Models\TerritorialUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class RegionalEventController extends Controller
{
    public function show(RegionalEvent $event): Response
    {
        abort_unless($event->status === 'published', 404);

        return Inertia::render('portal/regional-event', ['event' => $event]);
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'month' => ['nullable', 'date_format:Y-m'],
            'scope' => ['nullable', 'in:municipality,state,regional'],
            'state' => ['nullable', 'string', 'size:2'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'municipality_code' => ['nullable', 'regex:/^\d{7}$/'],
            'category' => ['nullable', 'string', 'max:80'],
        ]);
        if (isset($filters['municipality_code'])) {
            $unit = TerritorialUnit::query()->where('unit_type', 'municipality')->where('ibge_code', $filters['municipality_code'])->first(['name', 'state_code']);
            if ($unit) {
                $filters['municipality'] = $unit->name;
                $filters['state'] = $unit->state_code;
            }
        }
        if (($filters['scope'] ?? null) === 'regional') {
            unset($filters['state'], $filters['municipality'], $filters['municipality_code']);
        } elseif (($filters['scope'] ?? null) === 'state') {
            unset($filters['municipality'], $filters['municipality_code']);
        } elseif (! isset($filters['scope']) && ! isset($filters['state']) && ! isset($filters['municipality']) && ! isset($filters['municipality_code'])) {
            $defaultMunicipality = TerritorialUnit::query()->where('unit_type', 'municipality')->where('ibge_code', '4314902')->first(['name', 'state_code']);
            if ($defaultMunicipality !== null) {
                $filters['state'] = $defaultMunicipality->state_code;
                $filters['municipality'] = $defaultMunicipality->name;
            } else {
                $filters['state'] = 'RS';
                $filters['municipality'] = 'Porto Alegre';
            }
            $filters['municipality_code'] = '4314902';
            $filters['scope'] = 'municipality';
        }
        $month = $filters['month'] ?? now()->format('Y-m');
        $start = $month.'-01';
        $end = Carbon::parse($start)->endOfMonth()->toDateString();
        $holidayCategory = $filters['category'] ?? null;
        $events = RegionalEvent::query()->where('status', 'published')
            ->whereDate('starts_on', '<=', $end)->whereRaw('COALESCE(ends_on, starts_on) >= ?', [$start])
            ->when(in_array($holidayCategory, ['Feriado nacional', 'Feriado estadual'], true), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($subquery) => $subquery->where('name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")))
            ->when($filters['state'] ?? null, fn ($query, $state) => $query->where('state', $state))
            ->when($filters['municipality'] ?? null, fn ($query, $municipality) => $query->where('municipality', $municipality))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->orderBy('starts_on')->paginate(18)->withQueryString();

        $holidays = RegionalHoliday::query()->whereBetween('date', [$start, $end])
            ->when($filters['state'] ?? null, fn ($query, $state) => $query->where(fn ($subquery) => $subquery->whereNull('state_code')->orWhere('state_code', $state)))
            ->when($holidayCategory === 'Feriado nacional', fn ($query) => $query->where('scope', 'national'))
            ->when($holidayCategory === 'Feriado estadual', fn ($query) => $query->where('scope', 'state'))
            ->when($holidayCategory && ! in_array($holidayCategory, ['Feriado nacional', 'Feriado estadual'], true), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('name', 'like', "%{$term}%"))
            ->orderBy('date')->orderBy('name')->get();
        $catalogueExists = TerritorialUnit::query()->where('unit_type', 'municipality')->exists();
        $catalogueStates = TerritorialUnit::query()->where('unit_type', 'state')->orderBy('name')->pluck('state_code')->all();
        $catalogueMunicipalities = TerritorialUnit::query()->where('unit_type', 'municipality')->when($filters['state'] ?? null, fn ($query, $state) => $query->where('state_code', $state))->orderBy('name')->get(['ibge_code', 'name'])->map(fn ($unit) => ['code' => (string) $unit->ibge_code, 'name' => $unit->name])->all();
        $selectedMunicipalityCode = $filters['municipality_code'] ?? (isset($filters['municipality'], $filters['state']) ? TerritorialUnit::query()->where('unit_type', 'municipality')->where('state_code', $filters['state'])->where('name', $filters['municipality'])->value('ibge_code') : null);
        $categories = RegionalEvent::query()->where('status', 'published')->distinct()->orderBy('category')->pluck('category')->merge(['Feriado nacional', 'Feriado estadual'])->unique()->values();

        return Inertia::render('portal/regional-events', [
            'events' => $events,
            'holidays' => $holidays,
            'month' => $month,
            'filters' => $filters,
            'municipalities' => $catalogueExists ? $catalogueMunicipalities : RegionalEvent::query()->where('status', 'published')->distinct()->orderBy('municipality')->pluck('municipality')->map(fn ($name) => ['code' => null, 'name' => $name]),
            'selectedMunicipalityCode' => $selectedMunicipalityCode ? (string) $selectedMunicipalityCode : '',
            'states' => $catalogueExists ? $catalogueStates : RegionalEvent::query()->where('status', 'published')->distinct()->orderBy('state')->pluck('state'),
            'categories' => $categories,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RuralCalendarEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RuralCalendarEntryController extends Controller
{
    private const STATES = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    public function index(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'state' => ['nullable', Rule::in(self::STATES)], 'municipality' => ['nullable', 'string', 'max:120'], 'category' => ['nullable', 'string', 'max:80'], 'visibility' => ['nullable', 'in:published,draft']]);
        $entries = RuralCalendarEntry::query()
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('activity', 'like', "%{$term}%"))
            ->when($filters['state'] ?? null, fn ($query, $state) => $query->where('state', $state))
            ->when($filters['municipality'] ?? null, fn ($query, $city) => $query->where('municipality', $city))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['visibility'] ?? null, fn ($query, $visibility) => $query->where('is_published', $visibility === 'published'))
            ->latest('starts_on')->paginate(20)->withQueryString();

        return Inertia::render('admin/rural-calendar/index', [
            'entries' => $entries,
            'filters' => $filters,
            'categories' => RuralCalendarEntry::query()->distinct()->orderBy('category')->pluck('category'),
            'municipalities' => RuralCalendarEntry::query()->when($filters['state'] ?? null, fn ($query, $state) => $query->where('state', $state))->whereNotNull('municipality')->distinct()->orderBy('municipality')->pluck('municipality'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/rural-calendar/form', ['entry' => null, 'states' => self::STATES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEntry($request);
        $data['updated_by'] = $request->user()->id;
        $entry = RuralCalendarEntry::create($data);

        return to_route('admin.rural-calendar.edit', $entry)->with('success', 'Atividade adicionada ao calendário.');
    }

    public function edit(RuralCalendarEntry $entry): Response
    {
        return Inertia::render('admin/rural-calendar/form', ['entry' => $entry, 'states' => self::STATES]);
    }

    public function update(Request $request, RuralCalendarEntry $entry): RedirectResponse
    {
        $entry->update([...$this->validateEntry($request), 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Atividade atualizada.');
    }

    public function destroy(RuralCalendarEntry $entry): RedirectResponse
    {
        $entry->delete();

        return to_route('admin.rural-calendar.index')->with('success', 'Registro removido do calendário.');
    }

    /** @return array<string, mixed> */
    private function validateEntry(Request $request): array
    {
        $data = $request->validate([
            'activity' => ['required', 'string', 'max:180'],
            'category' => ['required', 'string', 'max:80'],
            'activity_type' => ['required', 'in:agriculture,livestock,production'],
            'frequency' => ['required', 'in:seasonal,continuous'],
            'region' => ['required', 'string', 'max:120'],
            'state' => ['required', Rule::in(self::STATES)],
            'municipality' => ['nullable', 'string', 'max:120'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reference_period' => ['nullable', 'string', 'max:80'],
            'source_name' => ['required', 'string', 'max:180'],
            'source_url' => ['required', 'url', 'max:2048', 'regex:/^https?:\/\//i'],
            'verified_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_published' => ['required', 'boolean'],
        ]);

        $data['is_published'] = (bool) $data['is_published'];

        return $data;
    }
}

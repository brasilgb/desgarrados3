<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RegionalEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RegionalEventAdminController extends Controller
{
    private const STATES = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    private const CATEGORIES = ['Festa municipal', 'Festa da uva e vitivinicultura', 'Feira agropecuária', 'Exposição rural', 'Rodeio e tradicionalismo', 'Evento cultural', 'Festa comunitária', 'Feriado'];

    public function index(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'state' => ['nullable', Rule::in(self::STATES)], 'municipality' => ['nullable', 'string', 'max:120'], 'category' => ['nullable', Rule::in(self::CATEGORIES)], 'status' => ['nullable', 'in:draft,published,unconfirmed']]);
        $events = RegionalEvent::query()
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('name', 'like', "%{$term}%"))
            ->when($filters['state'] ?? null, fn ($query, $state) => $query->where('state', $state))
            ->when($filters['municipality'] ?? null, fn ($query, $city) => $query->where('municipality', $city))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('starts_on')->paginate(20)->withQueryString();

        return Inertia::render('admin/regional-events/index', ['events' => $events, 'filters' => $filters, 'states' => self::STATES, 'categories' => self::CATEGORIES]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/regional-events/form', ['event' => null, 'states' => self::STATES, 'categories' => self::CATEGORIES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->slug($data['name']);
        $data['updated_by'] = $request->user()->id;
        $event = RegionalEvent::create($data);

        return to_route('admin.regional-events.edit', $event)->with('success', 'Evento criado. Confira fonte e datas antes de publicar.');
    }

    public function edit(RegionalEvent $event): Response
    {
        return Inertia::render('admin/regional-events/form', ['event' => $event, 'states' => self::STATES, 'categories' => self::CATEGORIES]);
    }

    public function update(Request $request, RegionalEvent $event): RedirectResponse
    {
        $data = $this->validated($request, $event);
        $data['slug'] = $this->slug($data['name'], $event->id);
        $event->update([...$data, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Evento atualizado.');
    }

    public function duplicate(RegionalEvent $event): RedirectResponse
    {
        $copy = $event->replicate();
        $copy->forceFill(['name' => Str::limit($event->name.' (nova edição)', 180, ''), 'slug' => $this->slug($event->name.' nova edicao'), 'status' => 'draft', 'is_featured' => false])->save();

        return to_route('admin.regional-events.edit', $copy)->with('success', 'Cópia criada como rascunho. Revise datas, fonte e local antes de publicar.');
    }

    public function destroy(RegionalEvent $event): RedirectResponse
    {
        $event->delete();

        return to_route('admin.regional-events.index')->with('success', 'Evento removido.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?RegionalEvent $event = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'municipality' => ['required', 'string', 'max:120'],
            'state' => ['required', Rule::in(self::STATES)],
            'venue' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'organizer' => ['nullable', 'string', 'max:180'],
            'source_name' => ['required', 'string', 'max:180'],
            'source_url' => ['required', 'url', 'max:2048', 'regex:/^https?:\/\//i'],
            'verified_at' => ['required', 'date', 'before_or_equal:today'],
            'additional_url' => ['nullable', 'url', 'max:2048', 'regex:/^https?:\/\//i'],
            'status' => ['required', 'in:draft,published,unconfirmed'],
            'is_featured' => ['required', 'boolean'],
        ]);
        $data['is_featured'] = (bool) $data['is_featured'];

        return $data;
    }

    private function slug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        for ($suffix = 2; RegionalEvent::query()->where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore))->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}

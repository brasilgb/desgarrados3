<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductionGuide;
use App\Models\TerritorialUnit;
use App\Support\HtmlContentSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductionGuideAdminController extends Controller
{
    private const CATEGORIES = ['Agricultura', 'Bovinocultura', 'Ovinocultura e lã', 'Viticultura', 'Avicultura', 'Suinocultura', 'Fruticultura', 'Produção leiteira'];

    public function index(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'category' => ['nullable', Rule::in(self::CATEGORIES)], 'state_code' => ['nullable', Rule::in($this->states())], 'status' => ['nullable', 'in:draft,published']]);
        $guides = ProductionGuide::query()->when($filters['q'] ?? null, fn ($query, $term) => $query->where('title', 'like', "%{$term}%"))->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))->when($filters['state_code'] ?? null, fn ($query, $state) => $query->where('state_code', $state))->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->orderBy('category')->orderByDesc('updated_at')->paginate(20)->withQueryString();

        return Inertia::render('admin/production-guides/index', ['guides' => $guides, 'filters' => $filters, 'categories' => self::CATEGORIES, 'states' => $this->states()]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/production-guides/form', ['guide' => null, 'categories' => self::CATEGORIES, 'states' => $this->states()]);
    }

    public function store(Request $request, HtmlContentSanitizer $sanitizer): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->slug(($data['slug'] ?? null) ?: $data['title']);
        $data['body'] = $sanitizer->sanitize($data['body']);
        $data['updated_by'] = $request->user()->id;
        $guide = ProductionGuide::create($data);

        return to_route('admin.production-guides.edit', $guide)->with('success', 'Conteúdo produtivo salvo.');
    }

    public function edit(ProductionGuide $guide): Response
    {
        return Inertia::render('admin/production-guides/form', ['guide' => $guide, 'categories' => self::CATEGORIES, 'states' => $this->states()]);
    }

    public function update(Request $request, ProductionGuide $guide, HtmlContentSanitizer $sanitizer): RedirectResponse
    {
        $data = $this->validated($request, $guide);
        $data['slug'] = $this->slug(($data['slug'] ?? null) ?: $data['title'], $guide->id);
        $data['body'] = $sanitizer->sanitize($data['body']);
        $guide->update([...$data, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Conteúdo produtivo atualizado.');
    }

    public function destroy(ProductionGuide $guide): RedirectResponse
    {
        $guide->delete();

        return to_route('admin.production-guides.index')->with('success', 'Conteúdo removido.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ProductionGuide $guide = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('production_guides', 'slug')->ignore($guide?->id)],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'region' => ['nullable', 'string', 'max:120'],
            'state_code' => ['nullable', Rule::in($this->states())],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:30000'],
            'source_name' => ['required_if:status,published', 'nullable', 'string', 'max:180'],
            'source_url' => ['required_if:status,published', 'nullable', 'url', 'max:2048', 'regex:/^https?:\/\//i'],
            'reference_date' => ['required_if:status,published', 'nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', 'in:draft,published'],
        ]);

        return $data;
    }

    private function slug(string $title, ?int $ignore = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        for ($suffix = 2; ProductionGuide::query()->where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore))->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /** @return list<string> */
    private function states(): array
    {
        $states = array_values(array_map('strval', TerritorialUnit::query()->where('unit_type', 'state')->orderBy('state_code')->pluck('state_code')->all()));

        return $states !== [] ? $states : ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
    }
}

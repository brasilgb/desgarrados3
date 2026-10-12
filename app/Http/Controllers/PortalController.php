<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\PortalSetting;
use App\Models\Publication;
use App\Models\Saying;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public const NOTEBOOKS = [
        'historias' => ['type' => 'historia', 'title' => 'Histórias de vida', 'intro' => 'Caminhos, partidas, chegadas e lembranças contadas por quem viveu cada travessia.'],
        'causos' => ['type' => 'causo', 'title' => 'Causos de todo pago', 'intro' => 'Prosa comprida, memória boa e aqueles causos que atravessam gerações.'],
        'anedotas' => ['type' => 'anedota', 'title' => 'Humor e anedotas', 'intro' => 'Risos compartilhados e pequenas histórias bem-humoradas da vida cotidiana.'],
        'poesia' => ['type' => 'poema', 'title' => 'Poesia e palavra', 'intro' => 'Versos e palavras que encontram a casa da saudade, da paisagem e do pertencimento.'],
        'literatura' => ['type' => 'literatura', 'title' => 'Literatura', 'intro' => 'Obras, leituras e conversas literárias, com autoria e direitos respeitados.'],
        'musica' => ['type' => 'musica', 'title' => 'Música e patrimônio', 'intro' => 'Memórias sonoras, artistas e tradições musicais contadas sem reproduzir obras sem autorização.'],
        'tradicoes' => ['type' => 'tradição', 'title' => 'Tradições vivas', 'intro' => 'Costumes, saberes e encontros que seguem vivos em cada comunidade.'],
    ];

    public function index(Request $request): Response
    {
        $query = $this->publishedQuery($request);
        $featured = (clone $query)->where('is_featured', true)->latest('published_at')->first() ?? (clone $query)->latest('published_at')->first();
        $counts = Publication::query()->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now())->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');
        $notebooks = collect(self::NOTEBOOKS)->map(fn (array $notebook, string $slug) => [...$notebook, 'slug' => $slug, 'count' => $counts[$notebook['type']] ?? 0])->values();

        $photographicMemories = Publication::query()->with(['author:id,name', 'category:id,name', 'coverMedia:id,path,alt_text,credit,mime_type,width,height'])
            ->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now())->whereNotNull('cover_path')
            ->whereHas('coverMedia', fn ($media) => $media->where('mime_type', 'like', 'image/%'))
            ->latest('published_at')->limit(8)
            ->get(['id', 'title', 'slug', 'excerpt', 'type', 'origin_city', 'origin_state', 'municipality_code', 'published_at', 'user_id', 'category_id', 'cover_path']);

        return Inertia::render('portal/home', [
            'featured' => $featured,
            'publications' => $query->latest('published_at')->paginate(8)->withQueryString(),
            'photographicMemories' => $photographicMemories,
            'notebooks' => $notebooks,
            'categories' => $this->optionalAreaEnabled('show_categories') ? Category::orderBy('name')->get(['name', 'slug']) : collect(),
            'collections' => $this->optionalAreaEnabled('show_collections') ? Collection::whereHas('publications', fn ($publication) => $publication->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now()))->withCount(['publications' => fn ($publication) => $publication->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now())])->orderByDesc('is_featured')->orderBy('name')->limit(4)->get(['id', 'name', 'slug', 'description']) : collect(),
            'saying' => $this->optionalAreaEnabled('show_sayings') ? Saying::where('status', 'published')->inRandomOrder()->first(['phrase', 'meaning', 'source_note']) : null,
            'query' => $request->query('q'),
            'controller' => $this->confirmedController(),
        ]);
    }

    public function notebook(Request $request, string $slug): Response
    {
        abort_unless(isset(self::NOTEBOOKS[$slug]), 404);
        $notebook = self::NOTEBOOKS[$slug];
        $query = $this->publishedQuery($request)->where('type', $notebook['type']);
        if ($request->filled('assunto')) {
            $query->where(fn ($builder) => $builder->whereHas('category', fn ($category) => $category->where('slug', $request->query('assunto')))->orWhereHas('categories', fn ($category) => $category->where('slug', $request->query('assunto'))));
        }

        return Inertia::render('portal/notebook', [
            'notebook' => [...$notebook, 'slug' => $slug],
            'publications' => $query->latest('published_at')->paginate(12)->withQueryString(),
            'categories' => Category::where(fn ($query) => $query->whereHas('publications', fn ($publication) => $publication->where('type', $notebook['type'])->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now()))->orWhereHas('categorizedPublications', fn ($publication) => $publication->where('type', $notebook['type'])->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now())))->orderBy('name')->get(['name', 'slug']),
            'filters' => $request->only('q', 'assunto'),
        ]);
    }

    public function show(string $slug): Response
    {
        $publication = Publication::with(['author:id,name', 'category:id,name,slug', 'categories:id,name,slug', 'coverMedia:id,path,alt_text,credit,width,height', 'series', 'collections:id,name,slug', 'municipality:ibge_code,name,state_code'])->where('slug', $slug)->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now())->firstOrFail();
        $related = Publication::with(['author:id,name', 'category:id,name,slug'])->where('id', '!=', $publication->id)->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now())
            ->where(fn ($query) => $query->where('type', $publication->type)->when($publication->category_id, fn ($query) => $query->orWhere('category_id', $publication->category_id)))
            ->latest('published_at')->limit(3)->get();

        $chapters = $publication->series?->publications()->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now())->get(['id', 'title', 'slug', 'series_position']) ?? collect();

        return Inertia::render('portal/show', ['publication' => $publication, 'related' => $related, 'chapters' => $chapters]);
    }

    public function contribute(Request $request): Response
    {
        return Inertia::render('portal/contribute', ['origin' => $request->only(['origin_city', 'origin_state'])]);
    }

    /** @return array{name: string, email: string}|null */
    private function confirmedController(): ?array
    {
        $settings = PortalSetting::query()->whereIn('key', [
            'legal_controller_type', 'legal_controller_name', 'legal_controller_identity_verified',
            'legal_controller_public_identity_authorized', 'contact_email',
        ])->pluck('value', 'key');
        $legal = config('legal.controller');
        $type = $settings->get('legal_controller_type', $legal['type'] ?? 'individual');
        $name = $settings->get('legal_controller_name', $legal['name'] ?? '');
        $contact = filled($settings->get('contact_email')) ? $settings->get('contact_email') : ($legal['contact'] ?? '');
        $identityVerified = $settings->has('legal_controller_identity_verified')
            ? $settings->get('legal_controller_identity_verified') === '1'
            : (bool) ($legal['identity_verified'] ?? false);
        $publicIdentityAuthorized = $settings->has('legal_controller_public_identity_authorized')
            ? $settings->get('legal_controller_public_identity_authorized') === '1'
            : (bool) ($legal['public_identity_authorized'] ?? false);

        if ($type !== 'individual' || ! $identityVerified || ! $publicIdentityAuthorized || blank($name) || ! filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return ['name' => $name, 'email' => $contact];
    }

    /** Optional home areas are on until an administrator turns them off, matching the panel and the seeder defaults. */
    private function optionalAreaEnabled(string $key): bool
    {
        return (PortalSetting::where('key', $key)->value('value') ?? '1') === '1';
    }

    /** @return Builder<Publication> */
    private function publishedQuery(Request $request): Builder
    {
        $query = Publication::with(['author:id,name', 'category:id,name,slug', 'coverMedia:id,path,alt_text,credit'])->publiclyVisible();
        if ($request->filled('q')) {
            $query->matching((string) $request->string('q'));
        }
        if ($request->filled('categoria')) {
            $query->where(fn ($builder) => $builder->whereHas('category', fn ($category) => $category->where('slug', $request->query('categoria')))->orWhereHas('categories', fn ($category) => $category->where('slug', $request->query('categoria'))));
        }

        return $query;
    }
}

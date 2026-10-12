<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Media;
use App\Models\Publication;
use App\Models\PublicationSeries;
use App\Models\TerritorialUnit;
use App\Models\User;
use App\Support\HtmlContentSanitizer;
use App\Support\MediaVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PublicationController extends Controller
{
    public function __construct(private readonly HtmlContentSanitizer $sanitizer, private readonly MediaVisibility $mediaVisibility) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:180'],
            'status' => ['nullable', 'in:draft,scheduled,published,archived'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'author' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', 'in:historia,causo,anedota,poema,literatura,musica,tradição'],
            'place' => ['nullable', 'string', 'max:120'],
            'origin_scope' => ['nullable', 'in:municipality,state,regional'],
            'origin_state' => ['nullable', 'string', 'size:2'],
        ]);
        $query = Publication::with(['category:id,name', 'author:id,name', 'municipality:ibge_code,name,state_code'])->latest('updated_at');
        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.$request->string('q').'%');
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
        if (filled($filters['category'] ?? null)) {
            $query->where(fn ($builder) => $builder->where('category_id', $filters['category'])->orWhereHas('categories', fn ($category) => $category->whereKey($filters['category'])));
        }
        if (filled($filters['author'] ?? null)) {
            $query->where('user_id', $filters['author']);
        }
        if (filled($filters['type'] ?? null)) {
            $query->where('type', $filters['type']);
        }
        if (filled($filters['place'] ?? null)) {
            $place = '%'.addcslashes($filters['place'], '%_\\').'%';
            $query->where(fn ($builder) => $builder->where('origin_city', 'like', $place)->orWhereHas('municipality', fn ($municipality) => $municipality->where('name', 'like', $place)));
        }
        if (filled($filters['origin_scope'] ?? null)) {
            $query->where('origin_scope', $filters['origin_scope']);
        }
        if (filled($filters['origin_state'] ?? null)) {
            $query->where('origin_state', $filters['origin_state']);
        }
        if (filled($filters['from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (filled($filters['to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return Inertia::render('admin/publications/index', [
            'publications' => $query->paginate(15)->withQueryString(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'authors' => User::query()->whereIn('id', Publication::query()->select('user_id')->distinct())->orderBy('name')->get(['id', 'name']),
            'states' => TerritorialUnit::query()->where('unit_type', 'state')->orderBy('name')->get(['state_code as code', 'name']),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/publications/form', ['publication' => null, 'categories' => Category::orderBy('name')->get(['id', 'name']), 'categoryIds' => [], 'media' => $this->mediaOptions(), 'collections' => Collection::orderBy('name')->get(['id', 'name']), 'series' => PublicationSeries::orderBy('title')->get(['id', 'title']), ...$this->territorialOptions($request->query('state'))]);
    }

    public function municipalities(Request $request): JsonResponse
    {
        $state = $request->validate(['state' => ['required', 'string', 'size:2', Rule::exists('territorial_units', 'state_code')->where('unit_type', 'state')]])['state'];

        return response()->json(TerritorialUnit::query()->where('unit_type', 'municipality')->where('state_code', $state)->orderBy('name')->get(['ibge_code as code', 'name']));
    }

    public function store(Request $r): RedirectResponse
    {
        $data = $this->validateData($r);
        if (in_array($data['status'], ['published', 'scheduled'], true)) {
            $data['body'] = $this->publicMediaUrls($data['body']);
        }
        $data['body'] = $this->sanitizer->sanitize($data['body']);
        $this->normalizeTerritory($data);
        $categoryIds = $data['category_ids'] ?? (filled($data['category_id'] ?? null) ? [$data['category_id']] : []);
        $data['category_id'] = $categoryIds[0] ?? null;
        unset($data['category_ids']);
        $collectionIds = $data['collection_ids'] ?? [];
        unset($data['collection_ids']);
        $data['series_id'] = ($data['series_id'] ?? null) ?: null;
        $data['series_position'] = $data['series_id'] ? ($data['series_position'] ?? null) : null;
        $data['user_id'] = $r->user()->id;
        $data['slug'] = $this->slug($data['title']);
        $data['published_at'] = $data['status'] === 'published' ? now() : ($data['status'] === 'scheduled' ? Carbon::parse($data['published_at']) : null);
        $publication = Publication::create($data);
        $publication->categories()->sync($categoryIds);
        $publication->collections()->sync($collectionIds);
        $this->mediaVisibility->syncPublication($publication);

        return to_route('admin.publications.index')->with('success', 'Publicação salva.');
    }

    public function edit(Request $request, Publication $publication): Response
    {
        $publication->body = $this->sanitizer->sanitize($publication->body);
        foreach (Media::query()->where('is_public', false)->whereNotNull('private_path')->get(['id', 'path']) as $item) {
            $publication->body = str_replace('/storage/'.$item->path, route('admin.media.file', $item), $publication->body);
        }

        return Inertia::render('admin/publications/form', ['publication' => $publication, 'categories' => Category::orderBy('name')->get(['id', 'name']), 'categoryIds' => $publication->categories()->pluck('categories.id')->when($publication->category_id, fn ($ids) => $ids->push($publication->category_id))->unique()->values(), 'media' => $this->mediaOptions(), 'collections' => Collection::orderBy('name')->get(['id', 'name']), 'collectionIds' => $publication->collections()->pluck('collections.id'), 'series' => PublicationSeries::orderBy('title')->get(['id', 'title']), ...$this->territorialOptions($publication->origin_state)]);
    }

    public function update(Request $r, Publication $publication): RedirectResponse
    {
        $data = $this->validateData($r);
        if (in_array($data['status'], ['published', 'scheduled'], true)) {
            $data['body'] = $this->publicMediaUrls($data['body']);
        }
        $data['body'] = $this->sanitizer->sanitize($data['body']);
        $this->normalizeTerritory($data);
        $categoryIds = $data['category_ids'] ?? (filled($data['category_id'] ?? null) ? [$data['category_id']] : []);
        $data['category_id'] = $categoryIds[0] ?? null;
        unset($data['category_ids']);
        $collectionIds = $data['collection_ids'] ?? [];
        unset($data['collection_ids']);
        $data['series_id'] = ($data['series_id'] ?? null) ?: null;
        $data['series_position'] = $data['series_id'] ? ($data['series_position'] ?? null) : null;
        $data['slug'] = $this->slug($data['title'], $publication->id);
        $data['published_at'] = $data['status'] === 'published' ? ($publication->published_at ?? now()) : ($data['status'] === 'scheduled' ? Carbon::parse($data['published_at']) : null);
        $previousMedia = $publication->only(['cover_path', 'body']);
        $publication->recordRevision($r->user());
        $data['lock_version'] = $publication->lock_version + 1;
        $publication->forceFill($data)->save();
        $publication->categories()->sync($categoryIds);
        $publication->collections()->sync($collectionIds);
        $this->mediaVisibility->syncPublication($publication, $previousMedia);

        return $this->returnToIndex($r)->with('success', 'Publicação atualizada.');
    }

    public function autosave(Request $request, Publication $publication): JsonResponse
    {
        $version = $request->validate(['lock_version' => ['required', 'integer', 'min:0']])['lock_version'];
        abort_unless($publication->status === 'draft', 409, 'O autosave está disponível somente para rascunhos.');
        $request->merge(['status' => 'draft', 'published_at' => null]);
        $data = $this->validateData($request);
        $data['body'] = $this->sanitizer->sanitize($data['body']);
        $this->normalizeTerritory($data);
        $categoryIds = $data['category_ids'] ?? (filled($data['category_id'] ?? null) ? [$data['category_id']] : []);
        $data['category_id'] = $categoryIds[0] ?? null;
        unset($data['category_ids']);
        $collectionIds = $data['collection_ids'] ?? [];
        unset($data['collection_ids']);
        $data['series_id'] = ($data['series_id'] ?? null) ?: null;
        $data['series_position'] = $data['series_id'] ? ($data['series_position'] ?? null) : null;
        $previousMedia = null;
        $result = DB::transaction(function () use ($publication, $version, $data, $collectionIds, $categoryIds, &$previousMedia): array {
            $locked = Publication::query()->lockForUpdate()->findOrFail($publication->id);
            abort_unless($locked->status === 'draft', 409, 'O rascunho foi publicado ou agendado em outra sessão. Recarregue antes de continuar.');
            abort_unless($locked->lock_version === (int) $version, 409, 'Este rascunho mudou em outra sessão. Recarregue para evitar sobrescrever alterações.');
            $previousMedia = $locked->only(['cover_path', 'body']);
            $data['slug'] = $this->slug($data['title'], $locked->id);
            $data['published_at'] = null;
            $data['lock_version'] = $locked->lock_version + 1;
            $locked->forceFill($data)->save();
            $locked->categories()->sync($categoryIds);
            $locked->collections()->sync($collectionIds);

            return ['lock_version' => $locked->lock_version, 'updated_at' => $locked->fresh()->updated_at?->toISOString()];
        });
        $this->mediaVisibility->syncPublication($publication->fresh(), $previousMedia);

        return response()->json($result);
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:publications,id'],
            'action' => ['required', 'in:archive,unfeature,set_notebook'],
            'type' => ['required_if:action,set_notebook', 'nullable', 'in:historia,causo,anedota,poema,literatura,musica,tradição'],
        ]);
        $mediaSnapshots = [];
        $count = DB::transaction(function () use ($data, $request, &$mediaSnapshots): int {
            $publications = Publication::query()->whereIn('id', $data['ids'])->lockForUpdate()->get();
            foreach ($publications as $publication) {
                $mediaSnapshots[] = $publication->only(['cover_path', 'body']);
                $publication->recordRevision($request->user());
                $changes = match ($data['action']) {
                    'archive' => ['status' => 'archived', 'published_at' => null, 'is_featured' => false],
                    'unfeature' => ['is_featured' => false],
                    'set_notebook' => ['type' => $data['type']],
                    default => throw new \LogicException('Ação em lote inválida.'),
                };
                $publication->forceFill([...$changes, 'lock_version' => $publication->lock_version + 1])->save();
            }

            return $publications->count();
        });
        if ($data['action'] === 'archive') {
            $this->mediaVisibility->syncPaths(collect($mediaSnapshots)->flatMap(fn (array $snapshot) => [$snapshot['cover_path'] ?? null]));
            foreach (Publication::query()->whereIn('id', $data['ids'])->get() as $publication) {
                $this->mediaVisibility->syncPublication($publication);
            }
        }
        $message = match ($data['action']) {
            'archive' => "{$count} publicação(ões) arquivada(s).",
            'unfeature' => "Destaque removido de {$count} publicação(ões).",
            'set_notebook' => "Caderno atualizado em {$count} publicação(ões).",
            default => throw new \LogicException('Ação em lote inválida.'),
        };
        if ($data['action'] === 'archive') {
            $request->query->set('status', 'archived');
        } elseif ($data['action'] === 'set_notebook') {
            $request->query->set('type', $data['type']);
        }

        return $this->returnToIndex($request)->with('success', $message);
    }

    public function duplicate(Request $request, Publication $publication): RedirectResponse
    {
        $copy = $publication->replicate();
        $copy->forceFill([
            'user_id' => $request->user()->id,
            'title' => Str::limit($publication->title.' (cópia)', 180, ''),
            'slug' => $this->slug($publication->title.' copia'),
            'status' => 'draft',
            'published_at' => null,
            'is_featured' => false,
            'series_id' => null,
            'series_position' => null,
            'lock_version' => 0,
        ]);
        $copy->save();
        $copy->categories()->sync($publication->categories()->pluck('categories.id')->when($publication->category_id, fn ($ids) => $ids->push($publication->category_id))->unique());
        $copy->collections()->sync($publication->collections()->pluck('collections.id'));

        $filters = $this->queryFilters($request);

        return to_route('admin.publications.edit', ['publication' => $copy, ...$filters])->with('success', 'Cópia criada como rascunho. Revise o conteúdo antes de publicar.');
    }

    public function destroy(Publication $publication): RedirectResponse
    {
        $mediaSnapshot = $publication->only(['cover_path', 'body']);
        $publication->delete();
        $this->mediaVisibility->syncPublication($publication, $mediaSnapshot);

        return back()->with('success', 'Publicação removida.');
    }

    public function preview(Request $request): Response
    {
        $data = $this->validateData($request);
        $data['body'] = $this->sanitizer->sanitize($data['body']);
        $this->normalizeTerritory($data);
        $data['author'] = $request->user()->only('name');
        $data['category'] = Category::find($data['category_id'] ?? null)?->only('name');
        $data['categories'] = Category::query()->whereIn('id', $data['category_ids'] ?? (filled($data['category_id'] ?? null) ? [$data['category_id']] : []))->get(['id', 'name']);
        $data['cover_media'] = Media::query()->where('path', $data['cover_path'] ?? null)->first(['path', 'alt_text', 'credit']);
        $data['collections'] = Collection::query()->whereIn('id', $data['collection_ids'] ?? [])->get(['id', 'name', 'slug']);
        $data['series'] = isset($data['series_id']) ? PublicationSeries::query()->find($data['series_id'], ['id', 'title', 'slug', 'description']) : null;

        return Inertia::render('admin/publications/preview', ['publication' => $data]);
    }

    /** @return array<string, mixed> */
    private function validateData(Request $r): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:180'], 'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'], 'type' => ['required', 'in:historia,causo,anedota,poema,literatura,musica,tradição'],
            'category_id' => ['nullable', 'exists:categories,id'], 'credit' => ['nullable', 'string', 'max:180'],
            'category_ids' => ['nullable', 'array', 'max:20'], 'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'origin_state' => ['nullable', 'string', 'size:2', Rule::requiredIf($r->input('origin_scope') === 'state')], 'origin_city' => ['nullable', 'string', 'max:120'],
            'origin_scope' => ['nullable', 'in:municipality,state,regional'],
            'municipality_code' => ['nullable', Rule::requiredIf($r->input('origin_scope') === 'municipality'), Rule::exists('territorial_units', 'ibge_code')->where('unit_type', 'municipality')],
            'source_references' => ['nullable', 'array', 'max:20'],
            'source_references.*.name' => ['required', 'string', 'max:180'],
            'source_references.*.url' => ['required', 'url', 'max:2048', 'regex:/^https?:\/\//i'],
            'source_references.*.note' => ['nullable', 'string', 'max:500'],
            'cover_path' => ['nullable', 'exists:media,path'], 'status' => ['required', 'in:draft,scheduled,published,archived'],
            'published_at' => ['required_if:status,scheduled', 'nullable', 'date'], 'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:300'], 'is_featured' => ['nullable', 'boolean'],
            'collection_ids' => ['nullable', 'array'], 'collection_ids.*' => ['integer', 'exists:collections,id'],
            'series_id' => ['nullable', 'exists:series,id'], 'series_position' => ['nullable', 'required_with:series_id', 'integer', 'min:1'],
        ];
        if ($r->filled('series_id') && $r->filled('series_position')) {
            $current = $r->route('publication');
            $uniquePosition = Rule::unique('publications', 'series_position')->where('series_id', $r->input('series_id'));
            if ($current instanceof Publication) {
                $uniquePosition->ignore($current->id);
            }
            $rules['series_position'][] = $uniquePosition;
        }

        return $r->validate($rules);
    }

    /** @return array<string, mixed> */
    private function territorialOptions(?string $state): array
    {
        return [
            'states' => TerritorialUnit::query()->where('unit_type', 'state')->orderBy('name')->get(['state_code as code', 'name']),
            'municipalities' => $state ? TerritorialUnit::query()->where('unit_type', 'municipality')->where('state_code', $state)->orderBy('name')->get(['ibge_code as code', 'name']) : [],
        ];
    }

    /** @param array<string, mixed> $data */
    private function normalizeTerritory(array &$data): void
    {
        $scope = $data['origin_scope'] ?? (filled($data['municipality_code'] ?? null) ? 'municipality' : (filled($data['origin_state'] ?? null) ? 'state' : 'regional'));
        $data['origin_scope'] = $scope;
        if ($scope === 'municipality') {
            if (filled($data['municipality_code'] ?? null)) {
                $place = TerritorialUnit::query()->where('unit_type', 'municipality')->where('ibge_code', $data['municipality_code'])->firstOrFail();
                $data['origin_city'] = $place->name;
                $data['origin_state'] = $place->state_code;
            }
        } elseif ($scope === 'regional') {
            $data['municipality_code'] = null;
            $data['origin_city'] = null;
            $data['origin_state'] = null;
        } else {
            $data['municipality_code'] = null;
            $data['origin_city'] = null;
        }
        $data['source_references'] = array_values($data['source_references'] ?? []);
    }

    private function slug(string $title, ?int $id = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $n = 2;
        while (Publication::where('slug', $slug)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    private function returnToIndex(Request $request): RedirectResponse
    {
        $filters = $this->queryFilters($request);

        return to_route('admin.publications.index', $filters);
    }

    /** @return array<string, mixed> */
    private function queryFilters(Request $request): array
    {
        $allowed = ['q', 'status', 'category', 'author', 'from', 'to', 'type', 'place', 'origin_scope', 'origin_state'];
        $filters = array_intersect_key($request->query->all(), array_flip($allowed));

        return array_filter($filters, static fn ($value): bool => filled($value));
    }

    /** @return \Illuminate\Support\Collection<int, array{id: int<0, max>, path: string, original_name: string, alt_text: ?string, credit: ?string, preview_url: string}> */
    private function mediaOptions(): \Illuminate\Support\Collection
    {
        return Media::latest()->get(['id', 'path', 'original_name', 'alt_text', 'credit'])
            ->map(fn (Media $item) => [
                'id' => $item->id,
                'path' => $item->path,
                'original_name' => $item->original_name,
                'alt_text' => $item->alt_text,
                'credit' => $item->credit,
                'preview_url' => route('admin.media.file', $item),
            ]);
    }

    private function publicMediaUrls(string $body): string
    {
        return preg_replace_callback('~(?:https?://[^/]+)?/admin/media/(\d+)/file~', function (array $match): string {
            $media = Media::query()->find($match[1]);

            return $media ? '/storage/'.$media->path : $match[0];
        }, $body) ?? $body;
    }
}

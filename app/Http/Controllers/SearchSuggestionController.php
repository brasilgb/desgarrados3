<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\ProductionGuide;
use App\Models\Publication;
use App\Models\RegionalEvent;
use App\Models\Saying;
use App\Models\SitePage;
use App\Support\InstitutionalPages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Small public endpoint behind the header search dialog.
 *
 * It applies the same visibility rules as the public pages and returns a few
 * items per content type; the full result list stays at "/?q=".
 */
class SearchSuggestionController extends Controller
{
    public const PER_GROUP = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim((string) $request->query('q', ''));
        $groups = [];
        $morePublications = false;

        if (mb_strlen($term) >= 2) {
            $like = '%'.addcslashes($term, '\\%_').'%';
            $notebooks = collect(PortalController::NOTEBOOKS)->mapWithKeys(fn (array $notebook) => [$notebook['type'] => $notebook['title']]);

            $publications = Publication::query()->publiclyVisible()->matching($term)
                ->latest('published_at')->limit(self::PER_GROUP + 1)->get(['id', 'title', 'slug', 'excerpt', 'type']);
            $morePublications = $publications->count() > self::PER_GROUP;
            $groups[] = $this->group('publications', 'Publicações', $publications->take(self::PER_GROUP), fn (Publication $item) => [
                'title' => $item->title, 'section' => $notebooks[$item->type] ?? 'Publicação', 'description' => $item->excerpt, 'url' => "/publicacao/{$item->slug}",
            ]);

            $collections = Collection::query()
                ->whereHas('publications', fn (Builder $publication) => $publication->publiclyVisible())
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('description', 'like', $like))
                ->orderBy('name')->limit(self::PER_GROUP)->get(['id', 'name', 'slug', 'description']);
            $groups[] = $this->group('collections', 'Coleções', $collections, fn (Collection $item) => [
                'title' => $item->name, 'section' => 'Acervo cultural', 'description' => $item->description, 'url' => "/colecoes/{$item->slug}",
            ]);

            $sayings = Saying::query()->where('status', 'published')
                ->where(fn (Builder $query) => $query->where('phrase', 'like', $like)->orWhere('meaning', 'like', $like))
                ->orderBy('phrase')->limit(self::PER_GROUP)->get(['id', 'phrase', 'meaning']);
            $groups[] = $this->group('sayings', 'Ditados e expressões', $sayings, fn (Saying $item) => [
                'title' => $item->phrase, 'section' => 'Ditados', 'description' => $item->meaning, 'url' => "/ditados#ditado-{$item->id}",
            ]);

            $events = RegionalEvent::query()->where('status', 'published')
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('description', 'like', $like)->orWhere('municipality', 'like', $like))
                ->orderByDesc('starts_on')->limit(self::PER_GROUP)->get(['id', 'name', 'slug', 'municipality', 'state', 'starts_on']);
            $groups[] = $this->group('events', 'Agenda regional', $events, fn (RegionalEvent $item) => [
                'title' => $item->name, 'section' => 'Agenda regional', 'description' => collect([$item->municipality, $item->state])->filter()->implode('/'), 'url' => "/agenda-regional/{$item->slug}",
            ]);

            $guides = ProductionGuide::query()->where('status', 'published')
                ->where(fn (Builder $query) => $query->where('title', 'like', $like)->orWhere('summary', 'like', $like)->orWhere('category', 'like', $like))
                ->orderBy('title')->limit(self::PER_GROUP)->get(['id', 'title', 'slug', 'summary', 'category']);
            $groups[] = $this->group('guides', 'Produção e informações', $guides, fn (ProductionGuide $item) => [
                'title' => $item->title, 'section' => $item->category ?: 'Nossa Terra', 'description' => $item->summary, 'url' => "/nossa-terra/producao/{$item->slug}",
            ]);

            $groups[] = $this->group('pages', 'Páginas do portal', $this->institutionalPages($term), fn (array $page) => $page);
        }

        return response()
            ->json([
                'query' => $term,
                'groups' => array_values(array_filter($groups, fn (array $group) => $group['items'] !== [])),
                'hasMorePublications' => $morePublications,
                'tools' => RegionalHubController::publicTools(),
            ])
            ->header('X-Robots-Tag', 'noindex')
            ->header('Cache-Control', 'private, max-age=60');
    }

    /**
     * @template TItem
     *
     * @param  iterable<TItem>  $items
     * @param  callable(TItem): array<string, mixed>  $map
     * @return array{key: string, label: string, items: list<array<string, mixed>>}
     */
    private function group(string $key, string $label, iterable $items, callable $map): array
    {
        $rows = [];
        foreach ($items as $item) {
            $row = $map($item);
            $row['description'] = filled($row['description'] ?? null) ? Str::limit(trim(strip_tags((string) $row['description'])), 120) : null;
            $rows[] = $row;
        }

        return ['key' => $key, 'label' => $label, 'items' => $rows];
    }

    /**
     * Institutional pages that have a public route: the saved, published version, or the default text.
     *
     * @return list<array{title: string, section: string, description: ?string, url: string}>
     */
    private function institutionalPages(string $term): array
    {
        $saved = SitePage::query()->whereIn('slug', array_keys(InstitutionalPages::defaults()))->get(['slug', 'title', 'summary', 'status'])->keyBy('slug');
        $needle = Str::lower(Str::ascii($term));
        $pages = [];

        foreach (InstitutionalPages::defaults() as $slug => $default) {
            $page = $saved->get($slug);
            if ($page && $page->status !== 'published') {
                continue;
            }
            $title = $page->title ?? $default['title'];
            $summary = $page->summary ?? $default['summary'];
            if (str_contains(Str::lower(Str::ascii("{$title} {$summary}")), $needle)) {
                $pages[] = ['title' => $title, 'section' => 'Desgarrados', 'description' => $summary, 'url' => "/{$slug}"];
            }
        }

        return array_slice($pages, 0, self::PER_GROUP);
    }
}

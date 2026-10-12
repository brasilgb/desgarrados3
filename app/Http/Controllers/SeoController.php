<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\ProductionGuide;
use App\Models\Publication;
use App\Models\RegionalEvent;
use App\Models\SitePage;
use App\Support\InstitutionalPages;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    /** Public sections that are indexable without query parameters. */
    private const SECTIONS = ['/', '/nossa-terra', '/minha-regiao', '/calendario-rural', '/agenda-regional', '/nossa-terra/producao', '/ditados', '/contribua', '/termos-de-uso', '/politica-de-privacidade'];

    /** Canonical, published and indexable public pages only; unpublished content drops out automatically. */
    public function sitemap(): Response
    {
        $urls = collect(self::SECTIONS)->map(fn (string $path) => ['loc' => $path]);
        $urls = $urls->merge(collect(array_keys(PortalController::NOTEBOOKS))->map(fn (string $slug) => ['loc' => "/cadernos/{$slug}"]));
        $urls = $urls->merge($this->institutionalPages());

        $urls = $urls->merge(Publication::query()->publiclyVisible()->orderByDesc('published_at')->get(['slug', 'updated_at'])
            ->map(fn (Publication $publication) => ['loc' => "/publicacao/{$publication->slug}", 'lastmod' => $publication->updated_at]));
        $urls = $urls->merge(Collection::query()->whereHas('publications', fn (Builder $publication) => $publication->publiclyVisible())->orderBy('slug')->get(['slug', 'updated_at'])
            ->map(fn (Collection $collection) => ['loc' => "/colecoes/{$collection->slug}", 'lastmod' => $collection->updated_at]));
        $urls = $urls->merge(RegionalEvent::query()->where('status', 'published')->orderBy('slug')->get(['slug', 'updated_at'])
            ->map(fn (RegionalEvent $event) => ['loc' => "/agenda-regional/{$event->slug}", 'lastmod' => $event->updated_at]));
        $urls = $urls->merge(ProductionGuide::query()->where('status', 'published')->orderBy('slug')->get(['slug', 'updated_at'])
            ->map(fn (ProductionGuide $guide) => ['loc' => "/nossa-terra/producao/{$guide->slug}", 'lastmod' => $guide->updated_at]));

        $base = rtrim((string) config('app.url'), '/');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls->unique('loc') as $url) {
            $xml .= '  <url><loc>'.e($base.$url['loc']).'</loc>';
            if (($url['lastmod'] ?? null) instanceof CarbonInterface) {
                $xml .= '<lastmod>'.$url['lastmod']->toAtomString().'</lastmod>';
            }
            $xml .= "</url>\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** Production allows crawling and points to the sitemap; any other environment is kept out of search engines. */
    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /settings', 'Disallow: /busca/', '', 'Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml']
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** @return list<array{loc: string, lastmod?: CarbonInterface|null}> */
    private function institutionalPages(): array
    {
        $saved = SitePage::query()->whereIn('slug', array_keys(InstitutionalPages::defaults()))->get(['slug', 'body', 'status', 'updated_at'])->keyBy('slug');
        $pages = [];

        foreach (InstitutionalPages::defaults() as $slug => $default) {
            $page = $saved->get($slug);
            $body = $page->body ?? $default['body'];
            // Same rules as the public page: drafts are hidden and empty pages are not indexable.
            if (($page && $page->status !== 'published') || trim(html_entity_decode(strip_tags($body))) === '') {
                continue;
            }
            $pages[] = ['loc' => "/{$slug}", 'lastmod' => $page?->updated_at];
        }

        return $pages;
    }
}

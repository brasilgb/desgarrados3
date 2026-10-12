<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Saying;
use App\Models\SitePage;
use App\Support\HtmlContentSanitizer;
use App\Support\InstitutionalPages;
use Inertia\Inertia;
use Inertia\Response;

class SitePageController extends Controller
{
    public function show(string $slug, HtmlContentSanitizer $sanitizer): Response
    {
        $page = SitePage::where('slug', $slug)->first();

        // Keep the footer pages available on installations where the portal content
        // seeder has not been run yet. A saved page, even unpublished, takes precedence.
        if (! $page && ($default = InstitutionalPages::default($slug))) {
            $page = new SitePage([...$default, 'slug' => $slug, 'status' => 'published']);
        }

        abort_unless($page && $page->status === 'published', 404);
        $page->body = $sanitizer->sanitize($page->body);

        return Inertia::render('portal/institutional', [
            'page' => $page->only(['title', 'slug', 'summary', 'body']),
            // Never let search engines index a page whose body has no readable text.
            'indexable' => trim(html_entity_decode(strip_tags($page->body))) !== '',
        ]);
    }

    public function collection(string $slug): Response
    {
        $collection = Collection::where('slug', $slug)->firstOrFail();
        $publications = $collection->publications()->with(['author:id,name', 'category:id,name'])->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now())->latest('published_at')->paginate(12);

        return Inertia::render('portal/collection', ['collection' => $collection, 'publications' => $publications]);
    }

    public function sayings(): Response
    {
        return Inertia::render('portal/sayings', ['sayings' => Saying::where('status', 'published')->latest()->get(['id', 'phrase', 'meaning', 'context', 'source_note'])]);
    }

    public function notFound(): \Symfony\Component\HttpFoundation\Response
    {
        return Inertia::render('portal/not-found')->toResponse(request())->setStatusCode(404);
    }

    public function adminNotFound(): \Symfony\Component\HttpFoundation\Response
    {
        return Inertia::render('admin/not-found')->toResponse(request())->setStatusCode(404);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SitePage;
use App\Support\HtmlContentSanitizer;
use App\Support\InstitutionalPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SitePageController extends Controller
{
    public function index(): Response
    {
        $pages = SitePage::orderBy('title')->get(['id', 'title', 'slug', 'status', 'updated_at']);
        $missing = collect(InstitutionalPages::defaults())
            ->except($pages->pluck('slug')->all())
            ->map(fn (array $page, string $slug) => ['slug' => $slug, 'title' => $page['title']])
            ->values();

        return Inertia::render('admin/site-pages/index', ['pages' => $pages, 'defaultPages' => $missing]);
    }

    public function create(Request $request): Response
    {
        // "Personalizar" starts from the default copy the portal is already showing for that address.
        $slug = (string) $request->query('modelo', '');
        $default = SitePage::where('slug', $slug)->exists() ? null : InstitutionalPages::default($slug);

        return Inertia::render('admin/site-pages/form', [
            'page' => null,
            'template' => $default ? [...$default, 'slug' => $slug, 'status' => 'published'] : null,
            'termsDefaultBody' => $slug === InstitutionalPages::TERMS_SLUG ? InstitutionalPages::default($slug)['body'] : null,
        ]);
    }

    public function store(Request $request, HtmlContentSanitizer $sanitizer): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['slug'] ?: $data['title']);
        $data['body'] = $sanitizer->sanitize($data['body']);
        $data['updated_by'] = $request->user()->id;
        unset($data['terms_comparison_confirmed']);
        $page = DB::transaction(function () use ($data): SitePage {
            $page = SitePage::create($data);
            $this->recordPublishedTermsVersion($page);

            return $page;
        });

        return to_route('admin.site-pages.edit', $page)->with('success', 'Página institucional criada.');
    }

    public function edit(SitePage $sitePage): Response
    {
        return Inertia::render('admin/site-pages/form', [
            'page' => $sitePage,
            'termsDefaultBody' => $sitePage->slug === InstitutionalPages::TERMS_SLUG ? InstitutionalPages::default($sitePage->slug)['body'] : null,
        ]);
    }

    public function update(Request $request, SitePage $sitePage, HtmlContentSanitizer $sanitizer): RedirectResponse
    {
        $data = $this->validated($request, $sitePage);
        $data['slug'] = Str::slug($data['slug'] ?: $data['title']);
        $data['body'] = $sanitizer->sanitize($data['body']);
        $data['updated_by'] = $request->user()->id;
        unset($data['terms_comparison_confirmed']);
        DB::transaction(function () use ($sitePage, $data): void {
            $sitePage->update($data);
            $this->recordPublishedTermsVersion($sitePage->refresh());
        });

        return back()->with('success', 'Página institucional atualizada.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?SitePage $page = null): array
    {
        $confirmTerms = ($request->input('slug') === InstitutionalPages::TERMS_SLUG || $page?->slug === InstitutionalPages::TERMS_SLUG)
            && $request->input('status') === 'published';

        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('site_pages', 'slug')->ignore($page?->id)],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'status' => ['required', 'in:draft,published'],
            'terms_comparison_confirmed' => $confirmTerms ? ['required', 'accepted'] : ['nullable', 'boolean'],
        ]);
    }

    private function recordPublishedTermsVersion(SitePage $page): void
    {
        if ($page->slug === InstitutionalPages::TERMS_SLUG && $page->status === 'published') {
            InstitutionalPages::currentTerms();
        }
    }
}

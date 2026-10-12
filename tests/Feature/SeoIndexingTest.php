<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Collection;
use App\Models\ProductionGuide;
use App\Models\Publication;
use App\Models\RegionalEvent;
use App\Models\SitePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SeoIndexingTest extends TestCase
{
    use RefreshDatabase;

    private function publication(string $slug, array $attributes = []): Publication
    {
        return Publication::create([
            'user_id' => User::factory()->create()->id, 'title' => ucfirst(str_replace('-', ' ', $slug)), 'slug' => $slug, 'body' => '<p>Texto.</p>',
            'type' => 'causo', 'status' => 'published', 'published_at' => now()->subDay(), ...$attributes,
        ]);
    }

    private function event(string $slug, string $status = 'published'): RegionalEvent
    {
        return RegionalEvent::create(['name' => "Evento {$slug}", 'slug' => $slug, 'category' => 'Festa', 'municipality' => 'Bagé', 'state' => 'RS', 'starts_on' => today(), 'source_name' => 'Prefeitura', 'source_url' => 'https://example.org', 'verified_at' => today(), 'status' => $status]);
    }

    /** @return list<string> */
    private function sitemapPaths(): array
    {
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'Sitemap must be valid XML.');
        $base = rtrim(config('app.url'), '/');

        $paths = [];
        foreach ($xml->url as $url) {
            $paths[] = str_replace($base, '', (string) $url->loc);
        }

        return $paths;
    }

    public function test_sitemap_lists_only_canonical_published_public_pages(): void
    {
        $visible = $this->publication('causo-publicado');
        $this->publication('causo-rascunho', ['status' => 'draft', 'published_at' => null]);
        $this->publication('causo-futuro', ['status' => 'scheduled', 'published_at' => now()->addWeek()]);
        Collection::create(['name' => 'Coleção viva', 'slug' => 'colecao-viva'])->publications()->attach($visible);
        Collection::create(['name' => 'Coleção vazia', 'slug' => 'colecao-vazia']);
        $this->event('festa-publicada');
        $this->event('festa-rascunho', 'draft');
        ProductionGuide::create(['title' => 'Guia', 'slug' => 'guia-publicado', 'category' => 'Agricultura', 'body' => '<p>Conteúdo</p>', 'status' => 'published']);
        ProductionGuide::create(['title' => 'Guia rascunho', 'slug' => 'guia-rascunho', 'category' => 'Agricultura', 'body' => '<p>Conteúdo</p>', 'status' => 'draft']);
        SitePage::create(['title' => 'Contato em revisão', 'slug' => 'contato', 'body' => '<p>Rascunho.</p>', 'status' => 'draft']);

        $paths = $this->sitemapPaths();

        foreach (['/', '/nossa-terra', '/cadernos/historias', '/cadernos/tradicoes', '/ditados', '/sobre', '/proposta-cultural', '/termos-de-contribuicao', '/politica-de-privacidade', '/publicacao/causo-publicado', '/colecoes/colecao-viva', '/agenda-regional/festa-publicada', '/nossa-terra/producao/guia-publicado'] as $expected) {
            $this->assertContains($expected, $paths);
        }
        foreach (['/publicacao/causo-rascunho', '/publicacao/causo-futuro', '/colecoes/colecao-vazia', '/agenda-regional/festa-rascunho', '/nossa-terra/producao/guia-rascunho', '/contato', '/privacidade', '/contribua/obrigado', '/login', '/admin', '/dashboard'] as $excluded) {
            $this->assertNotContains($excluded, $paths);
        }
        $this->assertSame([], array_values(array_filter($paths, fn (string $path) => str_contains($path, '?') || str_starts_with($path, '/admin'))));
        $this->assertSame(count($paths), count(array_unique($paths)));
        $this->assertStringContainsString('<lastmod>', $this->get('/sitemap.xml')->getContent());
    }

    public function test_unpublished_content_leaves_the_sitemap_and_returns_404(): void
    {
        $publication = $this->publication('causo-temporario');
        $event = $this->event('festa-temporaria');
        $this->assertContains('/publicacao/causo-temporario', $this->sitemapPaths());

        $publication->update(['status' => 'draft']);
        $event->update(['status' => 'draft']);

        $paths = $this->sitemapPaths();
        $this->assertNotContains('/publicacao/causo-temporario', $paths);
        $this->assertNotContains('/agenda-regional/festa-temporaria', $paths);
        $this->get('/publicacao/causo-temporario')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->get('/agenda-regional/festa-temporaria')->assertNotFound();
    }

    public function test_robots_blocks_everything_outside_production(): void
    {
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertSeeText("User-agent: *\nDisallow: /", false);
    }

    public function test_robots_in_production_points_to_the_sitemap_and_hides_private_areas(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $content = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Disallow: /admin', $content);
        $this->assertStringContainsString('Sitemap: '.rtrim(config('app.url'), '/').'/sitemap.xml', $content);
        $this->assertStringNotContainsString("Disallow: /\n", $content);
        $this->assertFileDoesNotExist(public_path('robots.txt'), 'A static robots.txt would shadow the dynamic one.');
    }

    public function test_private_areas_internal_search_and_filters_are_not_indexed(): void
    {
        foreach (['/login', '/forgot-password', '/contribua/obrigado'] as $path) {
            $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
        // Guests are only redirected to the login; signed-in pages carry the header themselves.
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::Admin, 'is_active' => true, 'email_verified_at' => now()])->save();
        foreach (['/admin', '/admin/publications', '/settings/profile'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
        $this->actingAs($admin)->get('/dashboard')->assertRedirect()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        auth()->logout();
        $this->getJson('/busca/sugestoes?q=causo')->assertHeader('X-Robots-Tag', 'noindex');

        foreach (['/?q=causo', '/calendario-rural?state=RS', '/agenda-regional?scope=state&state=RS', '/minha-regiao?state=RS&municipality_code=4314902', '/cadernos/historias?assunto=campo', '/nossa-terra?ferramenta=arroba'] as $path) {
            $this->get($path)->assertHeader('X-Robots-Tag', 'noindex, follow');
        }

        foreach (['/', '/sobre', '/nossa-terra', '/cadernos/historias', '/cadernos/historias?page=2'] as $path) {
            $this->get($path)->assertOk()->assertHeaderMissing('X-Robots-Tag');
        }
        $this->get('/pagina-que-nao-existe')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, follow');
    }

    public function test_html_metadata_rules_are_in_the_shared_components(): void
    {
        $seo = File::get(resource_path('js/components/portal-seo.tsx'));
        foreach (['rel="canonical"', 'property="og:image"', 'name="twitter:card"', 'name="robots"', 'canonicalPath(path ?? pageUrl)', 'DEFAULT_SOCIAL_IMAGE'] as $needle) {
            $this->assertStringContainsString($needle, $seo);
        }
        $this->assertFileExists(public_path('images/brand/og-default.jpg'));
        $this->assertSame([1200, 630], array_slice(getimagesize(public_path('images/brand/og-default.jpg')), 0, 2));

        // Every public page declares its own metadata through PortalSeo; none falls back to a bare <Head title>.
        foreach (File::files(resource_path('js/pages/portal')) as $file) {
            $this->assertStringContainsString('<PortalSeo', $file->getContents(), $file->getFilename().' has no PortalSeo');
            $this->assertDoesNotMatchRegularExpression('/<Head title=/', $file->getContents(), $file->getFilename().' still sets a bare title');
        }

        $this->assertStringContainsString('content="noindex, nofollow"', File::get(resource_path('js/pages/admin/publications/preview.tsx')));
        $this->assertStringContainsString('content="noindex, nofollow"', File::get(resource_path('js/layouts/auth-layout.tsx')));
        $this->assertStringContainsString('content="noindex, nofollow"', File::get(resource_path('js/layouts/admin-layout.tsx')));
    }

    public function test_structured_data_uses_only_real_content(): void
    {
        $article = File::get(resource_path('js/pages/portal/show.tsx'));
        $this->assertStringContainsString("'@type': 'Article'", $article);
        $this->assertStringContainsString("author: { '@type': 'Person', name: p.author.name }", $article);
        $this->assertStringNotContainsString('aggregateRating', $article);

        $event = File::get(resource_path('js/pages/portal/regional-event.tsx'));
        $this->assertStringContainsString("'@type': 'Event'", $event);
        $this->assertStringContainsString("organizer: event.organizer ? { '@type': 'Organization', name: event.organizer } : null", $event);
        $this->assertStringNotContainsString('eventStatus', $event);

        $this->assertStringContainsString("'@type': 'BreadcrumbList'", File::get(resource_path('js/components/portal-breadcrumb.tsx')));
        $jsonLd = File::get(resource_path('js/components/json-ld.tsx'));
        $this->assertStringContainsString(".replace(/</g, '\\\\u003c')", $jsonLd);
        $this->assertStringContainsString("item !== ''", $jsonLd);
    }

    public function test_person_schema_requires_confirmed_and_authorized_controller_identity(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('controller', null));

        config(['legal.controller' => ['type' => 'individual', 'name' => 'Pessoa Exemplo', 'document' => '', 'address' => '', 'contact' => 'contato@example.test', 'identity_verified' => true, 'public_identity_authorized' => false, 'personal_details_public' => false]]);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('controller', null));

        config(['legal.controller.public_identity_authorized' => true]);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('controller', ['name' => 'Pessoa Exemplo', 'email' => 'contato@example.test']));

        config(['legal.controller.identity_verified' => false]);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('controller', null));
    }

    public function test_article_page_preserves_seo_fields_for_metadata(): void
    {
        $this->publication('causo-com-seo', ['seo_title' => 'Título SEO', 'seo_description' => 'Descrição SEO']);

        $this->get('/publicacao/causo-com-seo')->assertOk()->assertHeaderMissing('X-Robots-Tag')->assertInertia(fn (Assert $page) => $page
            ->where('publication.seo_title', 'Título SEO')
            ->where('publication.seo_description', 'Descrição SEO')
            ->has('publication.updated_at')
            ->has('publication.published_at'));
    }
}

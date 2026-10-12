<?php

namespace Tests\Feature;

use App\Http\Controllers\SearchSuggestionController;
use App\Models\Collection;
use App\Models\ProductionGuide;
use App\Models\Publication;
use App\Models\RegionalEvent;
use App\Models\Saying;
use App\Models\SitePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SearchSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create();
    }

    private function publication(string $title, array $attributes = []): Publication
    {
        return Publication::create([
            'user_id' => $this->author->id, 'title' => $title, 'slug' => str($title)->slug()->value(), 'body' => '<p>Texto.</p>',
            'excerpt' => 'Resumo da lembrança.', 'type' => 'causo', 'status' => 'published', 'published_at' => now()->subDay(), ...$attributes,
        ]);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function suggestionGroups(string $term): array
    {
        $response = $this->getJson('/busca/sugestoes?q='.urlencode($term))->assertOk();

        return collect($response->json('groups'))->mapWithKeys(fn (array $group) => [$group['key'] => $group['items']])->all();
    }

    public function test_suggestions_are_public_json_and_not_indexable(): void
    {
        $this->getJson('/busca/sugestoes?q=causo')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertJsonStructure(['query', 'groups', 'hasMorePublications', 'tools' => ['enabled', 'titles']]);
    }

    public function test_short_terms_do_not_search(): void
    {
        $this->publication('A estrada velha');

        $this->getJson('/busca/sugestoes?q=a')->assertOk()->assertJsonPath('groups', []);
        $this->getJson('/busca/sugestoes?q='.str_repeat('x', 101))->assertUnprocessable();
    }

    public function test_only_public_content_is_returned(): void
    {
        $this->publication('Causo do tropeiro publicado');
        $this->publication('Causo do tropeiro rascunho', ['status' => 'draft', 'published_at' => null]);
        $this->publication('Causo do tropeiro agendado', ['status' => 'scheduled', 'published_at' => now()->addDay()]);
        $this->publication('Causo do tropeiro arquivado', ['status' => 'archived']);
        RegionalEvent::create(['name' => 'Festa do tropeiro', 'slug' => 'festa-tropeiro', 'category' => 'Festa', 'municipality' => 'Bagé', 'state' => 'RS', 'starts_on' => today(), 'source_name' => 'Prefeitura', 'source_url' => 'https://example.org', 'verified_at' => today(), 'status' => 'draft']);
        ProductionGuide::create(['title' => 'Guia do tropeiro', 'slug' => 'guia-tropeiro', 'category' => 'Pecuária', 'body' => '<p>Conteúdo</p>', 'status' => 'draft']);
        Saying::create(['phrase' => 'Tropeiro não descansa.', 'meaning' => 'Rascunho.', 'status' => 'draft']);

        $groups = $this->suggestionGroups('tropeiro');

        $this->assertSame(['Causo do tropeiro publicado'], array_column($groups['publications'], 'title'));
        $this->assertArrayNotHasKey('events', $groups);
        $this->assertArrayNotHasKey('guides', $groups);
        $this->assertArrayNotHasKey('sayings', $groups);
    }

    public function test_relevant_results_from_each_public_content_type(): void
    {
        $publication = $this->publication('Chimarrão na estação', ['type' => 'historia']);
        $this->publication('Outra lembrança sem relação');
        $collection = Collection::create(['name' => 'Rodas de chimarrão', 'slug' => 'rodas-de-chimarrao', 'description' => 'Encontros.']);
        $collection->publications()->attach($publication);
        Saying::create(['phrase' => 'Chimarrão se toma em roda.', 'meaning' => 'Partilha.', 'status' => 'published']);
        RegionalEvent::create(['name' => 'Festival do chimarrão', 'slug' => 'festival-chimarrao', 'category' => 'Festa', 'municipality' => 'Venâncio Aires', 'state' => 'RS', 'starts_on' => today(), 'source_name' => 'Prefeitura', 'source_url' => 'https://example.org', 'verified_at' => today(), 'status' => 'published']);
        ProductionGuide::create(['title' => 'Erva-mate para chimarrão', 'slug' => 'erva-mate', 'category' => 'Agricultura', 'body' => '<p>Conteúdo</p>', 'status' => 'published']);

        $groups = $this->suggestionGroups('chimarrão');

        $this->assertSame('Chimarrão na estação', $groups['publications'][0]['title']);
        $this->assertSame('Histórias de vida', $groups['publications'][0]['section']);
        $this->assertCount(1, $groups['publications']);
        $this->assertSame('/colecoes/rodas-de-chimarrao', $groups['collections'][0]['url']);
        $this->assertStringStartsWith('/ditados#ditado-', $groups['sayings'][0]['url']);
        $this->assertSame('/agenda-regional/festival-chimarrao', $groups['events'][0]['url']);
        $this->assertSame('/nossa-terra/producao/erva-mate', $groups['guides'][0]['url']);
    }

    public function test_suggestions_are_limited_and_signal_more_results(): void
    {
        foreach (range(1, SearchSuggestionController::PER_GROUP + 3) as $index) {
            $this->publication("Causo da coxilha {$index}");
        }

        $response = $this->getJson('/busca/sugestoes?q=coxilha')->assertOk()->assertJsonPath('hasMorePublications', true);
        $this->assertCount(SearchSuggestionController::PER_GROUP, $response->json('groups.0.items'));
    }

    public function test_wildcards_typed_by_the_reader_do_not_match_everything(): void
    {
        $this->publication('Causo comum');

        $this->assertArrayNotHasKey('publications', $this->suggestionGroups('%%'));
    }

    public function test_institutional_pages_use_saved_or_default_text_and_hide_drafts(): void
    {
        $this->assertSame('/termos-de-contribuicao', $this->suggestionGroups('termos')['pages'][0]['url']);

        SitePage::create(['title' => 'Termos em revisão', 'slug' => 'termos-de-contribuicao', 'body' => '<p>.</p>', 'status' => 'draft']);
        $this->assertArrayNotHasKey('pages', $this->suggestionGroups('termos'));
    }

    public function test_every_suggested_url_opens_a_public_page(): void
    {
        $publication = $this->publication('Lembrança da sanga', ['type' => 'historia']);
        Collection::create(['name' => 'Sanga e memória', 'slug' => 'sanga-e-memoria'])->publications()->attach($publication);
        RegionalEvent::create(['name' => 'Encontro da sanga', 'slug' => 'encontro-sanga', 'category' => 'Festa', 'municipality' => 'Bagé', 'state' => 'RS', 'starts_on' => today(), 'source_name' => 'Prefeitura', 'source_url' => 'https://example.org', 'verified_at' => today(), 'status' => 'published']);
        ProductionGuide::create(['title' => 'Água da sanga', 'slug' => 'agua-sanga', 'category' => 'Agricultura', 'body' => '<p>Conteúdo</p>', 'status' => 'published']);

        $urls = collect($this->suggestionGroups('sanga'))->flatten(1)->pluck('url');
        $this->assertCount(4, $urls);
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_suggestions_are_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/busca/sugestoes?q=ab')->assertOk();
        }

        $this->getJson('/busca/sugestoes?q=ab')->assertTooManyRequests();
    }

    public function test_full_results_page_still_works_and_is_not_indexed(): void
    {
        $this->publication('Causo da porteira');

        $this->get('/?q=porteira')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('query', 'porteira')
            ->where('publications.data.0.title', 'Causo da porteira'));
        $this->get('/?q=porteira')->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->assertStringContainsString('noindex={Boolean(query)}', File::get(resource_path('js/pages/portal/home.tsx')));
    }

    public function test_header_uses_the_search_dialog_with_a_single_cleaned_up_shortcut_listener(): void
    {
        $layout = File::get(resource_path('js/layouts/public-layout.tsx'));
        $search = File::get(resource_path('js/components/portal-search.tsx'));

        $this->assertStringContainsString('<PortalSearch />', $layout);
        $this->assertStringNotContainsString('role="search"', $layout);
        $this->assertSame(1, substr_count($search, "addEventListener('keydown'"));
        $this->assertStringContainsString("return () => document.removeEventListener('keydown', onKeyDown);", $search);
        $this->assertStringContainsString('(event.metaKey || event.ctrlKey)', $search);
        $this->assertStringContainsString('shouldIgnoreShortcut(event)', $search);
        $this->assertStringContainsString('new AbortController()', $search);
        $this->assertStringContainsString('DEBOUNCE_MS', $search);
        $this->assertStringContainsString('role="combobox"', $search);
        $this->assertStringContainsString('aria-activedescendant', $search);
        $this->assertStringContainsString('onCloseAutoFocus=', $search);
        $this->assertStringContainsString('returnFocusRef.current?.focus()', $search);
        $this->assertStringContainsString('role="status" aria-live="polite"', $search);
    }
}

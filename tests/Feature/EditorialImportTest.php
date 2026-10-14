<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\ProductionGuide;
use App\Models\Publication;
use App\Models\TerritorialUnit;
use App\Models\User;
use App\Services\EditorialImport\EditorialImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditorialImportTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->directory = sys_get_temp_dir().'/editorial-import-'.uniqid();
        mkdir($this->directory);
        TerritorialUnit::query()->create(['ibge_code' => 4314407, 'unit_type' => 'municipality', 'name' => 'Pelotas', 'state_code' => 'RS']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*.json') ?: []);
        rmdir($this->directory);
        parent::tearDown();
    }

    /** @param array<string, mixed> $data */
    private function write(string $name, array $data): void
    {
        file_put_contents($this->directory.'/'.$name.'.json', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed> */
    private function story(array $overrides = []): array
    {
        return [
            'slug' => 'charqueadas-de-pelotas', 'title' => 'As charqueadas de Pelotas', 'excerpt' => 'Resumo.',
            'body' => '<p>Texto com fonte.</p><script>alert(1)</script>', 'status' => 'published',
            'credit' => 'Texto editorial', 'categories' => ['vida-campeira'], 'collections' => ['fronteira-e-campanha'],
            'location' => ['scope' => 'municipality', 'state' => 'RS', 'municipality' => 'Pelotas'],
            'sources' => [['name' => 'Atlas Socioeconômico do RS', 'url' => 'https://atlassocioeconomico.rs.gov.br/ocupacao-do-territorio', 'license' => 'Uso informativo', 'accessed_at' => '2026-10-14']],
            ...$overrides,
        ];
    }

    private function taxonomy(): void
    {
        $this->write('taxonomy', [
            'categories' => [['name' => 'Vida campeira', 'slug' => 'vida-campeira']],
            'collections' => [['name' => 'Fronteira e Campanha', 'slug' => 'fronteira-e-campanha', 'is_featured' => true]],
            'series' => [['title' => 'Lendas do Sul', 'slug' => 'lendas-do-sul']],
        ]);
    }

    public function test_import_creates_sanitized_publication_with_sources_location_and_taxonomy(): void
    {
        $this->taxonomy();
        $this->write('historias', ['type' => 'historia', 'items' => [$this->story()]]);

        $report = app(EditorialImporter::class)->import($this->directory);

        $publication = Publication::query()->where('slug', 'charqueadas-de-pelotas')->firstOrFail();
        $this->assertSame('published', $publication->status);
        $this->assertNotNull($publication->published_at);
        $this->assertStringNotContainsString('<script', $publication->body);
        $this->assertSame('municipality', $publication->origin_scope);
        $this->assertSame(4314407, (int) $publication->municipality_code);
        $this->assertSame('Uso informativo', $publication->source_references[0]['license']);
        $this->assertSame(['vida-campeira'], $publication->categories()->pluck('slug')->all());
        $this->assertSame(['fronteira-e-campanha'], $publication->collections()->pluck('slug')->all());
        $this->assertSame(EditorialImporter::EDITOR_EMAIL, $publication->author->email);
        $this->assertFalse($publication->author->is_active);
        $this->assertSame(1, $report['sections']['historia']['created']);
        $this->get('/publicacao/charqueadas-de-pelotas')->assertOk();
    }

    public function test_running_the_import_again_does_not_duplicate_anything(): void
    {
        $this->taxonomy();
        $this->write('historias', ['type' => 'historia', 'items' => [$this->story()]]);
        $this->write('nossa-terra', ['guides' => [[
            'slug' => 'arroz', 'title' => 'Arroz', 'category' => 'Agricultura', 'state_code' => 'RS', 'summary' => 'Resumo', 'body' => '<p>Arroz</p>',
            'source_name' => 'Atlas', 'source_url' => 'https://atlassocioeconomico.rs.gov.br/arroz', 'reference_date' => '2026-10-14', 'status' => 'published',
        ]]]);

        app(EditorialImporter::class)->import($this->directory);
        $report = app(EditorialImporter::class)->import($this->directory);

        $this->assertSame(1, Publication::query()->count());
        $this->assertSame(1, ProductionGuide::query()->count());
        $this->assertSame(1, User::query()->where('email', EditorialImporter::EDITOR_EMAIL)->count());
        $this->assertSame(1, $report['sections']['historia']['skipped']);
        $this->assertSame(1, $report['guides']['skipped']);
    }

    public function test_update_never_overwrites_a_publication_edited_by_a_person(): void
    {
        $this->taxonomy();
        $this->write('historias', ['type' => 'historia', 'items' => [$this->story()]]);
        app(EditorialImporter::class)->import($this->directory);
        Publication::query()->where('slug', 'charqueadas-de-pelotas')->update(['title' => 'Título revisado pela editora', 'lock_version' => 1]);

        $this->write('historias', ['type' => 'historia', 'items' => [$this->story(['title' => 'Título novo do importador'])]]);
        app(EditorialImporter::class)->import($this->directory, update: true);

        $this->assertSame('Título revisado pela editora', Publication::query()->value('title'));
    }

    public function test_items_without_sources_are_never_published_and_dry_run_writes_nothing(): void
    {
        $this->write('causos', ['type' => 'causo', 'items' => [$this->story(['slug' => 'sem-fonte', 'sources' => []])]]);

        $dry = app(EditorialImporter::class)->import($this->directory, dryRun: true);
        $this->assertSame(0, Publication::query()->count());
        $this->assertSame(1, $dry['sections']['causo']['skipped']);

        $this->write('causos', ['type' => 'causo', 'items' => [$this->story(['slug' => 'rascunho-sem-fonte', 'sources' => [], 'status' => 'draft'])]]);
        app(EditorialImporter::class)->import($this->directory);
        $this->assertSame('draft', Publication::query()->where('slug', 'rascunho-sem-fonte')->value('status'));
        $this->get('/publicacao/rascunho-sem-fonte')->assertNotFound();
    }

    public function test_only_freely_licensed_commons_images_are_downloaded_with_provenance(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        Http::fake([
            'commons.wikimedia.org/*' => function ($request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $title = (string) ($query['titles'] ?? '');
                $license = str_contains($title, 'Livre') ? 'Public domain' : 'CC BY-NC-SA 4.0';

                return Http::response(['query' => ['pages' => [[
                    'title' => $title,
                    'imageinfo' => [[
                        'thumburl' => 'https://upload.wikimedia.org/foto.png', 'mime' => 'image/png', 'descriptionurl' => 'https://commons.wikimedia.org/wiki/'.$title,
                        'extmetadata' => ['LicenseShortName' => ['value' => $license], 'Artist' => ['value' => str_contains($title, 'Outra') ? 'Unknown authorUnknown author' : '<a href="#">Virgilio Calegari</a>'], 'DateTimeOriginal' => ['value' => '1908']],
                    ]],
                ]]]]);
            },
            'upload.wikimedia.org/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);
        $this->write('historias', ['type' => 'historia', 'items' => [$this->story([
            'image' => ['commons' => 'File:Foto Livre.png', 'alt' => 'Bonde em 1908', 'caption' => 'Bondes, 1908'],
            'gallery' => [
                ['commons' => 'File:Outra Livre.png', 'alt' => 'Praça', 'caption' => 'Praça, 1910'],
                ['commons' => 'File:Restrita.png', 'alt' => 'Não usar'],
            ],
        ])]]);

        $report = app(EditorialImporter::class)->import($this->directory, withImages: true);

        $publication = Publication::query()->firstOrFail();
        $this->assertSame('media/acervo/charqueadas-de-pelotas.png', $publication->cover_path);
        $cover = Media::query()->where('path', $publication->cover_path)->firstOrFail();
        $this->assertSame('Public domain', $cover->provenance['license']);
        $this->assertSame('Virgilio Calegari', $cover->provenance['author']);
        $this->assertSame('Virgilio Calegari · Domínio público · Wikimedia Commons', $cover->credit);
        $this->assertSame('Autor desconhecido · Domínio público · Wikimedia Commons', Media::query()->where('path', 'media/acervo/charqueadas-de-pelotas-1.png')->value('credit'));
        $this->assertStringContainsString('<figcaption>Praça, 1910', $publication->body);
        $this->assertStringContainsString('/storage/media/acervo/charqueadas-de-pelotas-1.png', $publication->body);
        $this->assertStringNotContainsString('Não usar', $publication->body);
        $this->assertCount(1, $report['images']['refused']);
        $this->assertSame(2, Media::query()->count());
        Storage::disk('public')->assertExists('media/acervo/charqueadas-de-pelotas.png');

        app(EditorialImporter::class)->import($this->directory, withImages: true, update: true);
        $this->assertSame(2, Media::query()->count());
    }

    public function test_command_reports_the_import(): void
    {
        $this->taxonomy();
        $this->write('historias', ['type' => 'historia', 'items' => [$this->story()]]);

        $this->artisan('desgarrados:editorial-import', ['--path' => $this->directory, '--dry-run' => true])
            ->expectsOutputToContain('Simulação concluída')
            ->assertSuccessful();
        $this->assertSame(0, Publication::query()->count());
    }

    public function test_bundled_dataset_is_valid_and_every_published_item_has_a_non_wikipedia_source(): void
    {
        foreach (glob(database_path('data/editorial/*.json')) ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            foreach ($data['items'] ?? [] as $item) {
                $this->assertContains($data['type'], EditorialImporter::TYPES, $item['slug']);
                if ($item['status'] === 'published') {
                    $this->assertNotEmpty(array_filter($item['sources'], fn (array $source) => ! str_contains($source['url'], 'wikipedia.org')), $item['slug']);
                }
            }
        }

        $report = app(EditorialImporter::class)->import(database_path('data/editorial'), dryRun: true);
        $this->assertSame([], array_values(array_filter($report['warnings'], fn (string $warning) => ! str_contains($warning, 'não encontrado no catálogo IBGE'))));
        $this->assertSame(0, Publication::query()->count());
    }
}

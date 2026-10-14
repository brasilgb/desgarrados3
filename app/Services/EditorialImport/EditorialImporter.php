<?php

namespace App\Services\EditorialImport;

use App\Http\Controllers\Admin\ProductionGuideAdminController;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Media;
use App\Models\ProductionGuide;
use App\Models\Publication;
use App\Models\PublicationSeries;
use App\Models\TerritorialUnit;
use App\Models\User;
use App\Support\HtmlContentSanitizer;
use App\Support\MediaVisibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Loads the researched editorial archive from JSON files into the portal.
 *
 * Idempotent: items are matched by slug. A publication that already exists is never duplicated,
 * and one touched by a person in the admin panel is never overwritten, even with $update.
 */
class EditorialImporter
{
    public const EDITOR_EMAIL = 'acervo-editorial@desgarrados.invalid';

    public const TYPES = ['historia', 'causo', 'anedota', 'poema', 'literatura', 'musica', 'tradição'];

    /** @var array<string, mixed> */
    private array $report = [];

    private int $position = 0;

    public function __construct(
        private readonly HtmlContentSanitizer $sanitizer,
        private readonly MediaVisibility $mediaVisibility,
        private readonly CommonsImageFetcher $images,
    ) {}

    /** @return array<string, mixed> */
    public function import(string $directory, bool $withImages = false, bool $update = false, bool $dryRun = false): array
    {
        $this->report = ['sections' => [], 'guides' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'published' => 0, 'drafts' => 0], 'images' => ['added' => 0, 'reused' => 0, 'refused' => []], 'warnings' => []];
        $this->position = 0;
        $files = glob(rtrim($directory, '/').'/*.json') ?: [];
        sort($files);

        DB::beginTransaction();
        try {
            $editor = $this->editor();
            $taxonomy = collect($files)->first(fn (string $file) => basename($file) === 'taxonomy.json');
            if ($taxonomy) {
                $this->importTaxonomy($this->read($taxonomy));
            }
            foreach ($files as $file) {
                $data = $this->read($file);
                foreach ($data['items'] ?? [] as $item) {
                    $this->importPublication($editor, $item + ['type' => $data['type'] ?? null], $withImages && ! $dryRun, $update);
                }
                foreach ($data['guides'] ?? [] as $guide) {
                    $this->importGuide($editor, $guide, $update);
                }
            }
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return $this->report;
    }

    private function editor(): User
    {
        $editor = User::query()->firstOrNew(['email' => self::EDITOR_EMAIL]);
        if (! $editor->exists) {
            // Institutional byline only: random password, no verified address, cannot sign in.
            $editor->forceFill(['name' => 'Acervo Desgarrados', 'password' => Str::random(64), 'is_active' => false])->save();
        }

        return $editor;
    }

    /** @param array<string, mixed> $data */
    private function importTaxonomy(array $data): void
    {
        foreach ($data['categories'] ?? [] as $category) {
            Category::query()->firstOrCreate(['slug' => $category['slug']], ['name' => $category['name'], 'description' => $category['description'] ?? null]);
        }
        foreach ($data['collections'] ?? [] as $collection) {
            Collection::query()->firstOrCreate(['slug' => $collection['slug']], ['name' => $collection['name'], 'description' => $collection['description'] ?? null, 'is_featured' => (bool) ($collection['is_featured'] ?? false)]);
        }
        foreach ($data['series'] ?? [] as $series) {
            PublicationSeries::query()->firstOrCreate(['slug' => $series['slug']], ['title' => $series['title'], 'description' => $series['description'] ?? null]);
        }
    }

    /** @param array<string, mixed> $item */
    private function importPublication(User $editor, array $item, bool $withImages, bool $update): void
    {
        $type = (string) ($item['type'] ?? '');
        $section = &$this->section($type);
        $errors = $this->validatePublication($item);
        if ($errors) {
            $section['skipped']++;
            $this->report['warnings'][] = ($item['slug'] ?? '(sem slug)').': '.implode(' ', $errors);

            return;
        }

        $existing = Publication::query()->where('slug', $item['slug'])->first();
        if ($existing && (! $update || ! $this->untouchedByPeople($existing, $editor))) {
            $section['skipped']++;

            return;
        }

        $status = $item['status'];
        $location = $this->location($item['location'] ?? null, $item['slug']);
        $coverPath = $withImages && isset($item['image']['commons']) ? $this->image($editor, $item['image'], $item['slug'], $item['slug']) : $existing?->cover_path;
        $body = $item['body'].($withImages ? $this->gallery($editor, $item) : '');
        if (isset($item['image']['commons']) && ! $coverPath && $withImages && ($item['image']['required'] ?? false)) {
            $status = 'draft';
            $this->report['warnings'][] = $item['slug'].': imagem obrigatória recusada; publicação mantida como rascunho.';
        }
        $categories = $this->ids(Category::class, $item['categories'] ?? []);

        $publication = $existing ?? new Publication;
        $previousMedia = $existing?->only(['cover_path', 'body']);
        $publication->forceFill([
            'user_id' => $editor->id,
            'category_id' => $categories[0] ?? null,
            'title' => $item['title'],
            'slug' => $item['slug'],
            'excerpt' => $item['excerpt'] ?? null,
            'body' => $this->sanitizer->sanitize($body),
            'type' => $type,
            'credit' => $item['credit'] ?? null,
            'source_references' => $item['sources'] ?? [],
            'cover_path' => $coverPath,
            'status' => $status,
            'published_at' => $status === 'published' ? ($existing->published_at ?? now()->subMinutes($this->position++)) : null,
            'seo_title' => $item['seo_title'] ?? null,
            'seo_description' => $item['seo_description'] ?? null,
            'is_featured' => (bool) ($item['featured'] ?? false),
            'series_id' => isset($item['series']) ? PublicationSeries::query()->where('slug', $item['series']['slug'])->value('id') : null,
            'series_position' => $item['series']['position'] ?? null,
            ...$location,
        ])->save();

        $publication->categories()->sync($categories);
        $publication->collections()->sync($this->ids(Collection::class, $item['collections'] ?? []));
        $publication->recordRevision($editor);
        $this->mediaVisibility->syncPublication($publication, $previousMedia);

        $section[$existing ? 'updated' : 'created']++;
        $section[$status === 'published' ? 'published' : 'drafts']++;
    }

    /** @param array<string, mixed> $guide */
    private function importGuide(User $editor, array $guide, bool $update): void
    {
        $validator = Validator::make($guide, [
            'slug' => ['required', 'string', 'max:180'], 'title' => ['required', 'string', 'max:180'],
            'category' => ['required', 'in:'.implode(',', ProductionGuideAdminController::CATEGORIES)],
            'region' => ['nullable', 'string', 'max:120'], 'state_code' => ['nullable', 'string', 'size:2'],
            'summary' => ['nullable', 'string', 'max:500'], 'body' => ['required', 'string', 'max:30000'],
            'source_name' => ['required', 'string', 'max:180'], 'source_url' => ['required', 'url', 'regex:/^https:\/\//i'],
            'reference_date' => ['required', 'date', 'before_or_equal:today'], 'status' => ['required', 'in:draft,published'],
        ]);
        if ($validator->fails()) {
            $this->report['guides']['skipped']++;
            $this->report['warnings'][] = ($guide['slug'] ?? '(sem slug)').': '.implode(' ', $validator->errors()->all());

            return;
        }

        $existing = ProductionGuide::query()->where('slug', $guide['slug'])->first();
        if ($existing && (! $update || $existing->updated_by !== $editor->id)) {
            $this->report['guides']['skipped']++;

            return;
        }

        ($existing ?? new ProductionGuide)->forceFill([
            ...collect($guide)->only(['slug', 'title', 'category', 'region', 'state_code', 'summary', 'source_name', 'source_url', 'reference_date', 'status'])->all(),
            'body' => $this->sanitizer->sanitize($guide['body']),
            'updated_by' => $editor->id,
        ])->save();

        $this->report['guides'][$existing ? 'updated' : 'created']++;
        $this->report['guides'][$guide['status'] === 'published' ? 'published' : 'drafts']++;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function validatePublication(array $item): array
    {
        $validator = Validator::make($item, [
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'title' => ['required', 'string', 'max:180'], 'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'], 'credit' => ['nullable', 'string', 'max:180'],
            'status' => ['required', 'in:draft,published'],
            'seo_title' => ['nullable', 'string', 'max:180'], 'seo_description' => ['nullable', 'string', 'max:300'],
            // Every published item must carry at least one researchable source.
            'sources' => ['array', 'max:20', 'required_if:status,published'],
            'sources.*.name' => ['required', 'string', 'max:180'],
            'sources.*.url' => ['required', 'url', 'max:2048', 'regex:/^https?:\/\//i'],
            'sources.*.note' => ['nullable', 'string', 'max:500'],
            'sources.*.author' => ['nullable', 'string', 'max:180'],
            'sources.*.license' => ['nullable', 'string', 'max:180'],
            'sources.*.accessed_at' => ['nullable', 'date'],
            'categories' => ['array'], 'collections' => ['array'],
        ]);

        return array_values($validator->errors()->all());
    }

    /**
     * Ids of the taxonomy entries named by slug, in the order given; unknown slugs are ignored.
     *
     * @param  class-string<Category|Collection>  $model
     * @return list<int>
     */
    private function ids(string $model, mixed $slugs): array
    {
        $ids = [];
        foreach (is_array($slugs) ? $slugs : [] as $slug) {
            $id = $model::query()->where('slug', (string) $slug)->value('id');
            if ($id !== null) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /** Only items still exactly as the importer left them may be refreshed. */
    private function untouchedByPeople(Publication $publication, User $editor): bool
    {
        return $publication->user_id === $editor->id
            && $publication->lock_version === 0
            && ! $publication->revisions()->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $editor->id))->exists();
    }

    /**
     * @param  array<string, mixed>|null  $location
     * @return array{origin_scope: string, origin_state: string|null, origin_city: string|null, municipality_code: int|null}
     */
    private function location(?array $location, string $slug): array
    {
        $state = isset($location['state']) ? strtoupper($location['state']) : null;
        if (($location['scope'] ?? null) === 'municipality' && $state && isset($location['municipality'])) {
            $municipality = TerritorialUnit::query()->where('unit_type', 'municipality')->where('state_code', $state)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($location['municipality'])])->first(['ibge_code', 'name']);
            if ($municipality) {
                return ['origin_scope' => 'municipality', 'origin_state' => $state, 'origin_city' => $municipality->name, 'municipality_code' => (int) $municipality->ibge_code];
            }
            $this->report['warnings'][] = "{$slug}: município {$location['municipality']}/{$state} não encontrado no catálogo IBGE; vinculado ao estado.";
        }
        if ($state) {
            return ['origin_scope' => 'state', 'origin_state' => $state, 'origin_city' => null, 'municipality_code' => null];
        }

        return ['origin_scope' => 'regional', 'origin_state' => null, 'origin_city' => null, 'municipality_code' => null];
    }

    /**
     * Captioned figures for every gallery photograph whose licence was accepted.
     *
     * @param  array<string, mixed>  $item
     */
    private function gallery(User $editor, array $item): string
    {
        $figures = '';
        foreach (array_values($item['gallery'] ?? []) as $index => $photo) {
            $path = $this->image($editor, $photo, $item['slug'].'-'.($index + 1), $item['slug']);
            if (! $path) {
                continue;
            }
            $credit = Media::query()->where('path', $path)->value('credit');
            $caption = collect([$photo['caption'] ?? null, $credit])->filter()->implode(' · ');
            $figures .= '<figure><img src="/storage/'.e($path).'" alt="'.e($photo['alt'] ?? '').'"><figcaption>'.e($caption).'</figcaption></figure>';
        }

        return $figures;
    }

    /** @param array<string, mixed> $spec */
    private function image(User $editor, array $spec, string $pathSlug, string $publicationSlug): ?string
    {
        $title = (string) $spec['commons'];
        $existing = Media::query()->where('provenance->title', str_starts_with($title, 'File:') ? $title : 'File:'.$title)->first();
        if ($existing) {
            $this->report['images']['reused']++;

            return $existing->path;
        }

        $inspection = $this->images->inspect($title);
        if (! $inspection['accepted'] || ! $inspection['url']) {
            $this->report['images']['refused'][] = ['file' => $title, 'publication' => $publicationSlug, 'reason' => $inspection['reason']];

            return null;
        }
        $contents = $this->images->download($inspection['url']);
        $size = $contents ? @getimagesizefromstring($contents) : false;
        if (! $contents || ! $size) {
            $this->report['images']['refused'][] = ['file' => $title, 'publication' => $publicationSlug, 'reason' => 'Download falhou ou o arquivo não é uma imagem válida.'];

            return null;
        }

        $extension = match ($size['mime']) {
            'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', default => 'jpg',
        };
        $path = 'media/acervo/'.$pathSlug.'.'.$extension;
        Storage::disk('public')->put($path, $contents);
        $provenance = $inspection['provenance'];
        $credit = Str::limit(collect([$provenance['author'] ?? null, CommonsImageFetcher::licenseLabel($provenance['license'] ?? null), 'Wikimedia Commons'])->filter()->implode(' · '), 250, '…');

        Media::query()->create([
            'user_id' => $editor->id,
            'path' => $path,
            'original_name' => Str::limit(substr($title, strlen('File:')) ?: basename($path), 250, ''),
            'alt_text' => $spec['alt'] ?? null,
            'credit' => $credit,
            'mime_type' => $size['mime'],
            'size' => strlen($contents),
            'width' => $size[0],
            'height' => $size[1],
            'is_public' => true,
            'provenance' => [...$provenance, 'caption' => $spec['caption'] ?? null, 'location' => $spec['location'] ?? null, 'year' => $spec['year'] ?? null],
        ]);
        $this->report['images']['added']++;

        return $path;
    }

    /** @return array<string, int> */
    private function &section(string $type): array
    {
        $this->report['sections'][$type] ??= ['created' => 0, 'updated' => 0, 'skipped' => 0, 'published' => 0, 'drafts' => 0];

        return $this->report['sections'][$type];
    }

    /** @return array<string, mixed> */
    private function read(string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        return is_array($data) ? $data : [];
    }
}

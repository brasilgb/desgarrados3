<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Publication;
use App\Models\PublicationRevision;
use App\Support\HtmlContentSanitizer;
use App\Support\MediaVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicationRevisionController extends Controller
{
    public function index(Publication $publication, HtmlContentSanitizer $sanitizer): Response
    {
        $revisions = $publication->revisions()->with('editor:id,name')->get()->map(function (PublicationRevision $revision) use ($sanitizer): array {
            $snapshot = $revision->snapshot;
            $snapshot['body'] = $this->privatePreviewUrls($sanitizer->sanitize((string) ($snapshot['body'] ?? '')));

            return [
                'id' => $revision->id,
                'restored_from_revision_id' => $revision->restored_from_revision_id,
                'created_at' => $revision->getAttribute('created_at')?->toISOString(),
                'editor' => $revision->editor?->only('name'),
                'snapshot' => $snapshot,
            ];
        });
        $current = array_merge($publication->only(['title', 'excerpt', 'body', 'type', 'status', 'category_id', 'credit', 'cover_path', 'origin_scope', 'origin_state', 'origin_city', 'municipality_code', 'source_references']), ['published_at' => $publication->getRawOriginal('published_at'), 'category_ids' => $publication->categories()->pluck('categories.id')->when($publication->category_id, fn ($ids) => $ids->push($publication->category_id))->unique()->values()->all()]);
        $current['body'] = $this->privatePreviewUrls($sanitizer->sanitize((string) $current['body']));

        return Inertia::render('admin/publications/revisions', [
            'publication' => $publication->only(['id', 'title', 'slug', 'updated_at']),
            'current' => $current,
            'revisions' => $revisions,
        ]);
    }

    public function restore(Publication $publication, PublicationRevision $revision, HtmlContentSanitizer $sanitizer, MediaVisibility $mediaVisibility): RedirectResponse
    {
        abort_unless($revision->publication_id === $publication->id, 404);

        $previousMedia = null;
        DB::transaction(function () use ($publication, $revision, $sanitizer, &$previousMedia): void {
            $publication = Publication::query()->lockForUpdate()->findOrFail($publication->id);
            $previousMedia = $publication->only(['cover_path', 'body']);
            $snapshot = $revision->snapshot;
            $publication->recordRevision(request()->user(), $revision);

            $title = (string) $snapshot['title'];
            $baseSlug = Str::slug($title);
            $slug = $baseSlug;
            for ($suffix = 2; Publication::query()->where('slug', $slug)->whereKeyNot($publication->id)->exists(); $suffix++) {
                $slug = $baseSlug.'-'.$suffix;
            }

            $attributes = array_diff_key($snapshot, array_flip(['collection_ids', 'category_ids', 'user_id']));
            $attributes['body'] = $sanitizer->sanitize((string) ($attributes['body'] ?? ''));
            $attributes['published_at'] = filled($attributes['published_at'] ?? null) ? Carbon::parse($attributes['published_at']) : null;
            $attributes['slug'] = $slug;
            $attributes['lock_version'] = $publication->lock_version + 1;
            $publication->forceFill($attributes)->save();
            $publication->categories()->sync($snapshot['category_ids'] ?? (filled($snapshot['category_id'] ?? null) ? [$snapshot['category_id']] : []));
            $publication->collections()->sync($snapshot['collection_ids'] ?? []);
        });
        $mediaVisibility->syncPublication($publication->fresh(), $previousMedia);

        $allowed = ['q', 'status', 'category', 'author', 'from', 'to', 'type'];
        $filters = array_filter(array_intersect_key(request()->query->all(), array_flip($allowed)), static fn ($value): bool => filled($value));

        return to_route('admin.publications.edit', ['publication' => $publication, ...$filters])->with('success', 'Versão restaurada. A autoria original da publicação foi mantida.');
    }

    private function privatePreviewUrls(string $body): string
    {
        foreach (Media::query()->where('is_public', false)->whereNotNull('private_path')->get(['id', 'path']) as $media) {
            $body = str_replace('/storage/'.$media->path, route('admin.media.file', $media), $body);
        }

        return $body;
    }
}

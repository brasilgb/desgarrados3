<?php

namespace App\Support;

use App\Models\Media;
use App\Models\Publication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaVisibility
{
    /** Reconcile media references before and after an editorial change. */
    /** @param array<string, mixed>|null $previous */
    public function syncPublication(Publication $publication, ?array $previous = null): bool
    {
        $paths = [];
        foreach ([$previous, $publication->only(['cover_path', 'body'])] as $snapshot) {
            if (! $snapshot) {
                continue;
            }
            if (filled($snapshot['cover_path'] ?? null)) {
                $paths[] = $snapshot['cover_path'];
            }
            preg_match_all('~(?:/storage/|storage/)([^"\'\s<>?]+)~', (string) ($snapshot['body'] ?? ''), $matches);
            foreach ($matches[1] as $path) {
                $paths[] = rawurldecode($path);
            }
            preg_match_all('~/admin/media/(\d+)/file~', (string) ($snapshot['body'] ?? ''), $mediaMatches);
            if (! empty($mediaMatches[1])) {
                $paths = [...$paths, ...Media::query()->whereIn('id', $mediaMatches[1])->pluck('path')->all()];
            }
        }

        $synced = $this->syncPaths($paths);
        if ($publication->status === 'scheduled') {
            DB::table('publications')->where('id', $publication->id)->update([
                'media_visibility_synced_at' => $publication->published_at && Carbon::parse($publication->published_at)->lte(now()) && $synced ? now() : null,
            ]);
        }

        return $synced;
    }

    /** @param iterable<string|null> $paths */
    public function syncPaths(iterable $paths): bool
    {
        $paths = is_array($paths) ? $paths : iterator_to_array((function () use ($paths) {
            yield from $paths;
        })());
        $synced = true;
        foreach (array_unique(array_filter($paths, 'is_string')) as $path) {
            $pathSynced = DB::transaction(function () use ($path): bool {
                $media = Media::query()->where('path', $path)->lockForUpdate()->first();
                if (! $media) {
                    return true;
                }

                $activeUse = Publication::query()
                    ->whereIn('status', ['published', 'scheduled'])
                    ->where('published_at', '<=', now()->toDateTimeString())
                    ->where(fn ($query) => $query->where('cover_path', $path)->orWhere('body', 'like', '%'.$path.'%'))
                    ->exists();

                $public = Storage::disk('public');
                $private = Storage::disk('local');
                $privatePath = $media->private_path ?: 'media-retired/'.$media->id.'/'.basename($path);
                if ($activeUse) {
                    if ($media->is_public) {
                        if ($public->exists($path)) {
                            return true;
                        }
                        if ($private->exists($privatePath) && $public->put($path, $private->get($privatePath))) {
                            return true;
                        }

                        return false;
                    }
                    if (! $private->exists($privatePath)) {
                        return false;
                    }
                    if (! $public->put($path, $private->get($privatePath))) {
                        return false;
                    }

                    return $media->forceFill(['is_public' => true, 'private_path' => $privatePath])->save();
                }

                if (! $media->is_public) {
                    if (! $public->exists($path)) {
                        return true;
                    }
                    if (! $private->exists($privatePath) && ! $private->put($privatePath, $public->get($path))) {
                        return false;
                    }
                    if (! $private->exists($privatePath)) {
                        return false;
                    }
                    if ($media->private_path !== $privatePath && ! $media->forceFill(['private_path' => $privatePath])->save()) {
                        return false;
                    }

                    return $public->delete($path);
                }
                if (! $public->exists($path)) {
                    return $media->forceFill(['is_public' => false, 'private_path' => $private->exists($privatePath) ? $privatePath : $media->private_path])->save();
                }

                if (! $private->exists($privatePath) && ! $private->put($privatePath, $public->get($path))) {
                    return false;
                }
                if ($private->exists($privatePath) && $media->forceFill(['is_public' => false, 'private_path' => $privatePath])->save()) {
                    return $public->delete($path);
                }

                return false;
            });
            $synced = $synced && $pathSynced;
        }

        return $synced;
    }
}

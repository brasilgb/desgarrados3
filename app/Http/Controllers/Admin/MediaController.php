<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:180'], 'type' => ['nullable', 'in:image,document']]);
        $query = Media::query()->with(['uploader:id,name', 'sourceSubmission:id,title'])->latest();
        if (filled($filters['q'] ?? null)) {
            $term = '%'.addcslashes((string) $filters['q'], '%_\\').'%';
            $query->where(fn (Builder $media) => $media->where('original_name', 'like', $term)->orWhere('alt_text', 'like', $term)->orWhere('credit', 'like', $term));
        }
        if (($filters['type'] ?? null) === 'image') {
            $query->where('mime_type', 'like', 'image/%');
        } elseif (($filters['type'] ?? null) === 'document') {
            $query->where('mime_type', 'not like', 'image/%');
        }

        $media = $query->paginate(24)->withQueryString();
        $pageItems = $media->getCollection();
        $paths = $pageItems->pluck('path')->all();
        $ids = $pageItems->pluck('id')->all();
        $references = Publication::query()->where(function (Builder $publications) use ($paths, $ids): void {
            $publications->whereIn('cover_path', $paths);
            foreach ($paths as $path) {
                $publications->orWhere('body', 'like', '%'.$path.'%');
            }
            foreach ($ids as $id) {
                $publications->orWhere('body', 'like', '%/admin/media/'.$id.'/file%');
            }
        })->get(['cover_path', 'body']);

        return Inertia::render('admin/media/index', [
            'media' => $media->through(fn (Media $item) => [
                ...$item->toArray(),
                'preview_url' => route('admin.media.file', $item),
                'used_in_count' => $references->filter(fn (Publication $publication): bool => $publication->cover_path === $item->path || str_contains((string) $publication->body, $item->path) || str_contains((string) $publication->body, '/admin/media/'.$item->id.'/file'))->count(),
                'source_submission' => $item->sourceSubmission ? ['id' => $item->sourceSubmission->id, 'title' => $item->sourceSubmission->title] : null,
            ]),
            'filters' => $filters,
        ]);
    }

    public function file(Media $media): StreamedResponse
    {
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($media->is_public && Storage::disk('public')->exists($media->path)) {
            return Storage::disk('public')->response($media->path, $media->original_name, $headers, 'inline');
        }
        abort_unless($media->private_path && Storage::disk('local')->exists($media->private_path), 404);

        return Storage::disk('local')->response($media->private_path, $media->original_name, $headers, 'inline');
    }

    public function store(Request $r): RedirectResponse
    {
        $d = $r->validate(['file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,gif,pdf'], 'alt_text' => 'nullable|string|max:180', 'credit' => 'nullable|string|max:180']);
        $file = $d['file'];
        $path = $file->store('media', 'public');
        if (! $path) {
            return back()->with('error', 'Não foi possível armazenar o arquivo. Tente novamente.');
        }
        try {
            $dimensions = str_starts_with((string) $file->getMimeType(), 'image/') ? (@getimagesize($file->getRealPath()) ?: null) : null;
            Media::create([
                'user_id' => $r->user()->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'alt_text' => $d['alt_text'] ?? null,
                'credit' => $d['credit'] ?? null,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'width' => $dimensions[0] ?? null,
                'height' => $dimensions[1] ?? null,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            report($exception);

            return back()->with('error', 'Não foi possível salvar o arquivo. Tente novamente.');
        }

        return back()->with('success', 'Arquivo adicionado à biblioteca.');
    }

    public function destroy(Media $media): RedirectResponse
    {
        $references = Publication::query()
            ->where('cover_path', $media->path)
            ->orWhere('body', 'like', '%'.$media->path.'%')
            ->orWhere('body', 'like', '%/admin/media/'.$media->id.'/file%')
            ->count();
        if ($references > 0) {
            return back()->with('error', 'Este arquivo está sendo usado por conteúdo existente. Remova as referências antes de excluí-lo.');
        }

        Storage::disk('public')->delete($media->path);
        if ($media->private_path) {
            Storage::disk('local')->delete($media->private_path);
        }
        $media->delete();

        return back()->with('success', 'Mídia removida.');
    }
}

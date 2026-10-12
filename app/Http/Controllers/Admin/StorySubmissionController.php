<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Media;
use App\Models\Publication;
use App\Models\StorySubmission;
use App\Models\TerritorialUnit;
use App\Support\HtmlContentSanitizer;
use App\Support\InstitutionalPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StorySubmissionController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'pending');
        abort_unless(in_array($status, ['pending', 'approved', 'rejected', 'converted'], true), 404);

        return Inertia::render('admin/submissions/index', [
            'submissions' => StorySubmission::with('reviewer:id,name')->where('status', $status)->latest()->paginate(15)->withQueryString(),
            'status' => $status,
            'counts' => StorySubmission::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(StorySubmission $submission): Response
    {
        return Inertia::render('admin/submissions/show', ['currentTermsLabel' => InstitutionalPages::currentTermsLabel(), 'submission' => $submission->load('acceptedTerms:id,label,source,created_at', 'reviewer:id,name', 'publication:id,title,slug,status,published_at', 'publicMedia:id,source_submission_id,path,credit,alt_text'), 'categories' => Category::orderBy('name')->get(['id', 'name'])]);
    }

    public function photo(StorySubmission $submission): StreamedResponse
    {
        abort_unless($submission->photo_path && Storage::disk('local')->exists($submission->photo_path), 404);

        return Storage::disk('local')->response($submission->photo_path, 'foto-da-contribuicao', [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function incorporatePhoto(Request $request, StorySubmission $submission): RedirectResponse
    {
        abort_unless($submission->moderation_approved_at && $submission->photo_path && $submission->photo_publication_consent && $submission->rights_confirmed && $submission->publication_consent, 403, 'Esta imagem não tem as autorizações necessárias ou não passou pela moderação.');
        $publishedAt = $submission->publication?->published_at;
        abort_unless($submission->status === 'converted' && $submission->publication && $submission->publication->status === 'published' && $publishedAt && Carbon::parse($publishedAt)->lte(now()), 422, 'Aprovação, conversão e publicação do relato são necessárias antes de incorporar a fotografia ao acervo público.');

        $data = $request->validate(['alt_text' => ['required', 'string', 'max:180']]);
        $existing = Media::query()->where('source_submission_id', $submission->id)->first();
        if ($existing) {
            return to_route('admin.submissions.show', $submission)->with('success', 'Esta fotografia já está na biblioteca de mídia.');
        }

        abort_unless(Storage::disk('local')->exists($submission->photo_path), 404, 'A fotografia privada não está disponível.');
        $sourcePath = $submission->photo_path;
        $mimeType = Storage::disk('local')->mimeType($sourcePath) ?: 'application/octet-stream';
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true), 422, 'O formato da fotografia não é aceito pelo acervo.');
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'
        };
        $publicPath = 'media/community/'.Str::uuid().'.'.$extension;
        $localFile = Storage::disk('local')->path($sourcePath);
        $dimensions = @getimagesize($localFile) ?: null;

        try {
            if (! Storage::disk('public')->put($publicPath, Storage::disk('local')->get($sourcePath))) {
                return back()->with('error', 'Não foi possível copiar a fotografia para a biblioteca.');
            }

            DB::transaction(function () use ($request, $submission, $data, $mimeType, $dimensions, $publicPath, $localFile): void {
                Media::create([
                    'user_id' => $request->user()->id,
                    'source_submission_id' => $submission->id,
                    'path' => $publicPath,
                    'original_name' => $submission->photo_original_name ?: basename($localFile),
                    'alt_text' => $data['alt_text'],
                    'credit' => $submission->photo_credit,
                    'mime_type' => $mimeType,
                    'size' => Storage::disk('local')->size($submission->photo_path),
                    'width' => $dimensions[0] ?? null,
                    'height' => $dimensions[1] ?? null,
                ]);
                $submission->publication()->update(['cover_path' => $publicPath]);
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($publicPath);
            report($exception);

            return back()->with('error', 'Não foi possível registrar a fotografia. Tente novamente.');
        }

        return to_route('admin.submissions.show', $submission)->with('success', 'Fotografia autorizada incorporada à biblioteca e associada à publicação.');
    }

    public function approve(Request $request, StorySubmission $submission): RedirectResponse
    {
        $this->ensureReviewable($submission);
        $data = $request->validate(['moderation_note' => ['nullable', 'string', 'max:2000']]);
        $submission->update(['status' => 'approved', 'moderation_note' => $data['moderation_note'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'moderation_approved_at' => now()]);

        return to_route('admin.submissions.show', $submission)->with('success', 'Relato aprovado para edição. Ainda não foi publicado.');
    }

    public function reject(Request $request, StorySubmission $submission): RedirectResponse
    {
        $this->ensureReviewable($submission);
        $data = $request->validate(['moderation_note' => ['required', 'string', 'max:2000']]);
        if ($submission->photo_path) {
            Storage::disk('local')->delete($submission->photo_path);
        }
        $submission->update(['status' => 'rejected', 'photo_path' => null, 'moderation_note' => $data['moderation_note'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return to_route('admin.submissions.show', $submission)->with('success', 'Relato recusado e mantido fora do portal.');
    }

    public function convert(Request $request, StorySubmission $submission, HtmlContentSanitizer $sanitizer): RedirectResponse
    {
        abort_unless($submission->status === 'approved' && $submission->moderation_approved_at, 422, 'Aprove o relato na fila de moderação antes de convertê-lo.');
        $data = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'type' => ['required', 'in:historia,causo,anedota,poema,literatura,musica,tradição'],
            'excerpt' => ['nullable', 'string', 'max:500'],
        ]);

        $publication = DB::transaction(function () use ($request, $submission, $data, $sanitizer): Publication {
            $base = Str::slug($submission->title) ?: 'relato-da-comunidade';
            $slug = $base;
            $suffix = 2;
            while (Publication::where('slug', $slug)->exists()) {
                $slug = $base.'-'.$suffix++;
            }
            $municipality = $submission->origin_city && $submission->origin_state
                ? TerritorialUnit::query()->where('unit_type', 'municipality')->where('state_code', strtoupper($submission->origin_state))->whereRaw('LOWER(name) = ?', [mb_strtolower($submission->origin_city)])->first()
                : null;
            $body = '<p>'.nl2br(e($submission->story)).'</p>';
            $publication = Publication::create([
                'user_id' => $request->user()->id,
                'category_id' => $data['category_id'] ?? null,
                'title' => $submission->title,
                'slug' => $slug,
                'excerpt' => $data['excerpt'] ?? Str::limit($submission->story, 200),
                'body' => $sanitizer->sanitize($body),
                'type' => $data['type'],
                'credit' => $submission->display_name_consent && $submission->contributor_name ? 'Relato enviado por '.$submission->contributor_name : 'Contribuição da comunidade',
                'origin_city' => $municipality?->name,
                'origin_state' => $municipality ? $municipality->state_code : $submission->origin_state,
                'origin_scope' => $municipality ? 'municipality' : ($submission->origin_state ? 'state' : 'regional'),
                'municipality_code' => $municipality?->ibge_code,
                'source_references' => [],
                'status' => 'draft',
            ]);
            if ($data['category_id'] ?? null) {
                $publication->categories()->sync([$data['category_id']]);
            }
            $submission->update(['status' => 'converted', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'publication_id' => $publication->id]);

            return $publication;
        });

        return to_route('admin.publications.edit', $publication)->with('success', 'Relato convertido em rascunho. Revise os créditos e o texto antes de publicar.');
    }

    private function ensureReviewable(StorySubmission $submission): void
    {
        abort_unless(in_array($submission->status, ['pending', 'approved'], true), 422, 'Este relato já foi encerrado.');
    }
}

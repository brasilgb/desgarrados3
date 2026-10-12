<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Media;
use App\Models\Publication;
use App\Models\StorySubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunitySubmissionsTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $editor = User::factory()->create();
        $editor->forceFill(['role' => UserRole::Editor, 'email_verified_at' => now()])->save();

        return $editor->refresh();
    }

    /** @return array<string, mixed> */
    private function story(array $overrides = []): array
    {
        return array_merge([
            'title' => 'A lembrança da estação antiga',
            'story' => 'Minha avó contava como a estação enchia de gente nas manhãs de domingo. As despedidas eram longas, mas sempre havia alguém esperando na volta.',
            'contributor_name' => 'Relato de família',
            'origin_city' => 'Bagé',
            'origin_state' => 'RS',
            'rights_confirmed' => true,
            'publication_consent' => true,
            'display_name_consent' => true,
            'terms_accepted' => true,
            'website' => '',
        ], $overrides);
    }

    public function test_visitor_story_is_validated_and_stays_private_pending_review(): void
    {
        $this->get('/contribua')->assertOk()->assertInertia(fn ($page) => $page->component('portal/contribute'));
        $this->post('/contribua', $this->story())->assertRedirect('/contribua/obrigado');

        $submission = StorySubmission::firstOrFail();
        $this->assertSame('pending', $submission->status);
        $this->assertTrue($submission->rights_confirmed);
        $this->get('/')->assertDontSee($submission->title);
        $this->get('/cadernos/historias')->assertDontSee($submission->title);
    }

    public function test_public_contribution_requires_rights_confirmation_and_honeypot_discards_bots(): void
    {
        $this->from('/contribua')->post('/contribua', $this->story(['rights_confirmed' => false]))->assertSessionHasErrors('rights_confirmed');
        $this->from('/contribua')->post('/contribua', $this->story(['publication_consent' => false]))->assertSessionHasErrors('publication_consent');
        $this->post('/contribua', $this->story(['website' => 'bot filled this']))->assertRedirect('/contribua/obrigado');
        $this->assertDatabaseCount('story_submissions', 0);
    }

    public function test_public_author_credit_requires_a_separate_opt_in_and_photo_is_kept_private(): void
    {
        Storage::fake('local');
        $payload = $this->story(['display_name_consent' => false, 'photo' => UploadedFile::fake()->image('memoria.jpg'), 'photo_credit' => 'Acervo familiar', 'photo_publication_consent' => true]);
        $this->post('/contribua', $payload)->assertRedirect('/contribua/obrigado');
        $submission = StorySubmission::firstOrFail();

        $this->assertSame('Relato de família', $submission->contributor_name);
        $this->assertFalse($submission->display_name_consent);
        $this->assertTrue($submission->publication_consent);
        $this->assertTrue($submission->photo_publication_consent);
        Storage::disk('local')->assertExists($submission->photo_path);
        $this->get('/')->assertDontSee('A lembrança da estação antiga');
        $this->get('/admin/submissions/'.$submission->id.'/photo')->assertRedirect('/login');

        $editor = $this->editor();
        $this->actingAs($editor)->get('/admin/submissions/'.$submission->id.'/photo')->assertOk();
        $this->actingAs($editor)->post('/admin/submissions/'.$submission->id.'/convert', ['type' => 'historia'])->assertStatus(422);
        $this->actingAs($editor)->post('/admin/submissions/'.$submission->id.'/approve', ['moderation_note' => 'Autorização e relato revisados.'])->assertRedirect();
        $this->actingAs($editor)->post('/admin/submissions/'.$submission->id.'/convert', ['type' => 'historia'])->assertRedirect();
        $publication = Publication::findOrFail($submission->refresh()->publication_id);
        $this->assertSame('Contribuição da comunidade', $publication->credit);
        $this->assertSame('draft', $publication->status);
    }

    public function test_photo_requires_credit_and_publication_permission_and_rejects_non_images(): void
    {
        Storage::fake('local');
        $this->from('/contribua')->post('/contribua', $this->story(['photo' => UploadedFile::fake()->create('arquivo.txt', 2, 'text/plain'), 'photo_credit' => 'Alguém', 'photo_publication_consent' => true]))->assertSessionHasErrors('photo');
        $this->from('/contribua')->post('/contribua', $this->story(['photo' => UploadedFile::fake()->image('memoria.jpg'), 'photo_credit' => 'Acervo familiar', 'photo_publication_consent' => false]))->assertSessionHasErrors('photo_publication_consent');
        $this->assertDatabaseCount('story_submissions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('story-submissions'));
    }

    public function test_authorized_photo_can_only_be_incorporated_after_its_story_is_published(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $editor = $this->editor();
        $submission = StorySubmission::create($this->story([
            'status' => 'converted', 'rights_confirmed' => true, 'publication_consent' => true,
            'moderation_approved_at' => now(),
            'photo_publication_consent' => true, 'photo_credit' => 'Acervo de Maria',
            'photo_original_name' => 'estacao.png', 'photo_path' => UploadedFile::fake()->image('estacao.png')->store('story-submissions', 'local'),
        ]));
        $publication = Publication::create(['user_id' => $editor->id, 'title' => $submission->title, 'slug' => 'lembranca-publicada', 'body' => '<p>Relato revisado.</p>', 'type' => 'historia', 'status' => 'draft']);
        $submission->update(['publication_id' => $publication->id]);

        $this->actingAs($editor)->post('/admin/submissions/'.$submission->id.'/photo/incorporate', ['alt_text' => 'Fachada da estação antiga'])->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $publication->update(['status' => 'published', 'published_at' => now()]);
        $this->post('/admin/submissions/'.$submission->id.'/photo/incorporate', ['alt_text' => 'Fachada da estação antiga'])->assertRedirect('/admin/submissions/'.$submission->id);
        $media = Media::where('source_submission_id', $submission->id)->firstOrFail();
        Storage::disk('public')->assertExists($media->path);
        Storage::disk('local')->assertExists($submission->photo_path);
        $this->assertSame('Acervo de Maria', $media->credit);
        $this->assertSame('Fachada da estação antiga', $media->alt_text);
        $this->assertSame($media->path, $publication->refresh()->cover_path);
        $this->actingAs($editor)->get('/admin/media')->assertOk()->assertInertia(fn ($page) => $page->where('media.data.0.source_submission.title', $submission->title));
        $this->get('/publicacao/'.$publication->slug)->assertOk()->assertInertia(fn ($page) => $page->where('publication.cover_media.credit', 'Acervo de Maria'));
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->has('photographicMemories', 1)->where('photographicMemories.0.cover_media.credit', 'Acervo de Maria'));
        $this->post('/admin/submissions/'.$submission->id.'/photo/incorporate', ['alt_text' => 'Outra descrição'])->assertRedirect('/admin/submissions/'.$submission->id);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_private_or_unconsented_submission_photo_cannot_be_made_public(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $editor = $this->editor();
        $submission = StorySubmission::create($this->story([
            'status' => 'converted', 'rights_confirmed' => true, 'publication_consent' => true,
            'photo_publication_consent' => false, 'photo_credit' => 'Acervo familiar',
            'photo_path' => UploadedFile::fake()->image('privada.png')->store('story-submissions', 'local'),
        ]));
        $publication = Publication::create(['user_id' => $editor->id, 'title' => $submission->title, 'slug' => 'foto-privada', 'body' => '<p>Relato.</p>', 'type' => 'historia', 'status' => 'published', 'published_at' => now()]);
        $submission->update(['publication_id' => $publication->id]);

        $this->actingAs($editor)->post('/admin/submissions/'.$submission->id.'/photo/incorporate', ['alt_text' => 'Foto privada'])->assertForbidden();
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        Storage::disk('local')->assertExists($submission->photo_path);
    }

    public function test_previously_converted_photo_without_a_recorded_moderation_approval_stays_private(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $editor = $this->editor();
        $submission = StorySubmission::create($this->story([
            'status' => 'converted', 'rights_confirmed' => true, 'publication_consent' => true,
            'photo_publication_consent' => true, 'photo_credit' => 'Acervo familiar',
            'photo_path' => UploadedFile::fake()->image('antiga.png')->store('story-submissions', 'local'),
        ]));
        $publication = Publication::create(['user_id' => $editor->id, 'title' => $submission->title, 'slug' => 'historia-anterior', 'body' => '<p>Relato.</p>', 'type' => 'historia', 'status' => 'published', 'published_at' => now()]);
        $submission->update(['publication_id' => $publication->id]);

        $this->actingAs($editor)->post('/admin/submissions/'.$submission->id.'/photo/incorporate', ['alt_text' => 'Imagem sem aprovação comprovada'])->assertForbidden();
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        Storage::disk('local')->assertExists($submission->photo_path);
    }

    public function test_public_contribution_route_is_rate_limited(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->post('/contribua', $this->story(['title' => 'Relato de limite '.$i]))->assertRedirect('/contribua/obrigado');
        }

        $this->post('/contribua', $this->story(['title' => 'Relato excedente']))->assertStatus(429);
    }

    public function test_only_editorial_roles_can_open_moderation_queue(): void
    {
        $this->get('/admin/submissions')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/submissions')->assertForbidden();
        $this->actingAs($this->editor())->get('/admin/submissions')->assertOk()->assertInertia(fn ($page) => $page->component('admin/submissions/index'));
    }

    public function test_approval_does_not_publish_and_conversion_creates_only_a_draft(): void
    {
        $submission = StorySubmission::create($this->story());
        $editor = $this->editor();

        $this->actingAs($editor)->post('/admin/submissions/'.$submission->id.'/approve', ['moderation_note' => 'Aprovado para revisão editorial.'])->assertRedirect();
        $this->assertSame('approved', $submission->refresh()->status);
        $this->get('/')->assertDontSee($submission->title);

        $this->post('/admin/submissions/'.$submission->id.'/convert', ['type' => 'historia', 'excerpt' => 'Lembrança enviada pela comunidade.'])->assertRedirect();
        $submission->refresh();
        $publication = Publication::findOrFail($submission->publication_id);
        $this->assertSame('converted', $submission->status);
        $this->assertSame('draft', $publication->status);
        $this->assertSame($editor->id, $publication->user_id);
        $this->assertSame('Relato enviado por Relato de família', $publication->credit);
        $this->assertNull($publication->published_at);
        $this->get('/publicacao/'.$publication->slug)->assertNotFound();
    }

    public function test_rejection_requires_a_note_and_keeps_the_story_private(): void
    {
        Storage::fake('local');
        $submission = StorySubmission::create($this->story());
        $submission->update(['photo_path' => UploadedFile::fake()->image('private.jpg')->store('story-submissions', 'local')]);
        $photoPath = $submission->photo_path;
        $this->actingAs($this->editor())->post('/admin/submissions/'.$submission->id.'/reject')->assertSessionHasErrors('moderation_note');
        $this->post('/admin/submissions/'.$submission->id.'/reject', ['moderation_note' => 'Falta confirmar a autorização do texto.'])->assertRedirect();
        $this->assertSame('rejected', $submission->refresh()->status);
        $this->assertNull($submission->photo_path);
        Storage::disk('local')->assertMissing($photoPath);
        $this->get('/')->assertDontSee($submission->title);
    }

    public function test_notebook_pages_filter_published_content_by_type_and_search(): void
    {
        $this->seed();
        $this->get('/cadernos/causos')->assertOk()->assertInertia(fn ($page) => $page->component('portal/notebook')->where('notebook.type', 'causo')->has('publications.data', 0));
        $this->assertDatabaseHas('publications', ['type' => 'causo', 'status' => 'draft', 'credit' => 'Causo demonstrativo']);
        $this->get('/cadernos/desconhecido')->assertNotFound();
    }
}

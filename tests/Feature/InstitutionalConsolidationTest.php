<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\PortalSetting;
use App\Models\SitePage;
use App\Models\StorySubmission;
use App\Models\TermsVersion;
use App\Models\User;
use App\Support\InstitutionalPages;
use Database\Seeders\PortalContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class InstitutionalConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user->refresh();
    }

    /** @return array<string, mixed> */
    private function story(string $title = 'A lembrança da estação antiga'): array
    {
        return [
            'title' => $title,
            'story' => str_repeat('Minha avó contava como a estação enchia de gente nas manhãs de domingo. ', 2),
            'rights_confirmed' => true, 'publication_consent' => true, 'terms_accepted' => true, 'website' => '',
        ];
    }

    private function message(): ContactMessage
    {
        return ContactMessage::create(['name' => 'Maria', 'email' => 'maria@example.test', 'subject' => 'Assunto', 'message' => 'Mensagem de teste.', 'privacy_acknowledged_at' => now()]);
    }

    // 1. Privacidade

    public function test_old_privacy_address_redirects_permanently_to_the_official_policy(): void
    {
        $this->get('/privacidade')->assertStatus(301)->assertRedirect('/politica-de-privacidade');
        $this->assertSame(url('/privacidade'), route('pages.privacy'));
        $this->get('/politica-de-privacidade')->assertOk()->assertInertia(fn (Assert $page) => $page->component('portal/legal-document')->where('document', 'privacy'));
    }

    public function test_short_privacy_page_is_no_longer_created_suggested_or_linked(): void
    {
        $this->seed(PortalContentSeeder::class);

        $this->assertDatabaseMissing('site_pages', ['slug' => 'privacidade']);
        $this->assertArrayNotHasKey('privacidade', InstitutionalPages::defaults());
        $this->assertSame([], collect($this->getJson('/busca/sugestoes?q=privacidade')->json('groups'))->where('key', 'pages')->all());

        $files = [...File::allFiles(resource_path('js/pages')), ...File::allFiles(resource_path('js/layouts')), ...File::allFiles(resource_path('js/components'))];
        foreach ($files as $file) {
            foreach (['"/privacidade', "'/privacidade", '`/privacidade'] as $link) {
                $this->assertStringNotContainsString($link, $file->getContents(), $file->getRelativePathname().' links to /privacidade');
            }
        }
        $this->assertStringContainsString("path={privacy ? '/politica-de-privacidade' : '/termos-de-uso'}", File::get(resource_path('js/pages/portal/legal-document.tsx')));
    }

    // 2. Mensagens de contato

    public function test_contact_messages_are_restricted_to_administrators(): void
    {
        $message = $this->message();

        $this->get('/admin/contact-messages')->assertRedirect('/login');
        $this->patch("/admin/contact-messages/{$message->id}/read")->assertRedirect('/login');
        $this->delete("/admin/contact-messages/{$message->id}")->assertRedirect('/login');

        $editor = $this->userWithRole(UserRole::Editor);
        $this->actingAs($editor)->patch("/admin/contact-messages/{$message->id}/read")->assertForbidden();
        $this->actingAs($editor)->delete("/admin/contact-messages/{$message->id}")->assertForbidden();
        $this->actingAs($this->userWithRole(UserRole::User))->get('/admin/contact-messages')->assertForbidden();

        $this->assertNull($message->refresh()->read_at);
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_deleting_a_contact_message_removes_it_permanently(): void
    {
        $message = $this->message();

        $this->actingAs($this->userWithRole(UserRole::Admin))->delete("/admin/contact-messages/{$message->id}")->assertRedirect();

        $this->assertNull(ContactMessage::find($message->id));
        $this->assertDatabaseMissing('contact_messages', ['email' => 'maria@example.test']);
    }

    public function test_failed_e_mail_keeps_the_message_and_logs_no_personal_data(): void
    {
        PortalSetting::create(['key' => 'contact_email', 'value' => 'redacao@example.test']);
        Mail::shouldReceive('to')->andThrow(new RuntimeException('550 rejected for maria@example.test from redacao@example.test'));
        Log::spy();

        $this->post('/contato', ['name' => 'Maria Silva', 'email' => 'maria@example.test', 'subject' => 'Assunto', 'message' => 'Mensagem longa o bastante.', 'privacy_acknowledged' => true, 'website' => ''])
            ->assertSessionHas('success');

        $this->assertNull(ContactMessage::sole()->forwarded_at);
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $text, array $context): bool {
            $logged = $text.json_encode($context);

            return ! str_contains($logged, '@') && ! str_contains($logged, 'Maria') && $context === ['contact_message_id' => ContactMessage::sole()->id, 'exception' => RuntimeException::class];
        });
    }

    public function test_successful_e_mail_still_works(): void
    {
        Mail::fake();
        PortalSetting::create(['key' => 'contact_email', 'value' => 'redacao@example.test']);

        $this->post('/contato', ['name' => 'Maria Silva', 'email' => 'maria@example.test', 'subject' => 'Café & causos', 'message' => 'Mensagem longa o bastante.', 'privacy_acknowledged' => true, 'website' => '']);

        Mail::assertSent(ContactMessageReceived::class, fn (ContactMessageReceived $mail) => str_contains($mail->render(), 'Assunto: Café & causos'));
    }

    // 3. Termos de envio

    public function test_each_acceptance_points_to_an_immutable_snapshot_of_the_text_shown(): void
    {
        $this->post('/contribua', $this->story())->assertRedirect('/contribua/obrigado');
        $this->post('/contribua', $this->story('Outra lembrança da estação'))->assertRedirect('/contribua/obrigado');

        $this->assertSame(1, TermsVersion::count());
        $version = TermsVersion::sole();
        [$first, $second] = StorySubmission::orderBy('id')->get()->all();

        $this->assertSame($version->id, $first->terms_version_id);
        $this->assertSame($version->id, $second->terms_version_id);
        $this->assertSame('termos-'.substr($version->content_hash, 0, 12), $first->terms_version);
        $this->assertSame(hash('sha256', $version->title."\n".$version->body), $version->content_hash);
        $this->assertStringContainsString('<h2>1. Materiais aceitos</h2>', $version->body);
        $this->assertNotNull($first->terms_accepted_at);
    }

    public function test_editing_the_terms_creates_a_new_version_without_touching_earlier_acceptances(): void
    {
        $this->post('/contribua', $this->story());
        $old = StorySubmission::sole();
        $acceptedAt = $old->terms_accepted_at->toIso8601String();

        $page = SitePage::create(['title' => 'Termos de envio de contribuições', 'slug' => 'termos-de-contribuicao', 'body' => '<p>Nova condição: publicação também em redes sociais.</p>', 'status' => 'published']);
        $this->post('/contribua', $this->story('Lembrança enviada depois da mudança'));
        $new = StorySubmission::latest('id')->first();

        $this->assertSame(2, TermsVersion::count());
        $this->assertNotSame($old->terms_version_id, $new->terms_version_id);
        $this->assertSame('pagina', $new->acceptedTerms->source);
        $old->refresh();
        $this->assertSame('padrao', $old->acceptedTerms->source);
        $this->assertSame($acceptedAt, $old->terms_accepted_at->toIso8601String());
        $this->assertStringContainsString('A licença abrange', $old->acceptedTerms->body);
        $this->assertStringNotContainsString('Nova condição', $old->acceptedTerms->body);

        // Saving the page without changing the text keeps the same version.
        $page->touch();
        $this->post('/contribua', $this->story('Terceira lembrança da estação'));
        $this->assertSame(2, TermsVersion::count());
        $this->assertSame($new->terms_version_id, StorySubmission::latest('id')->first()->terms_version_id);
    }

    public function test_admin_review_is_required_and_published_terms_get_an_immutable_version_immediately(): void
    {
        $page = SitePage::create([
            'title' => 'Termos de envio personalizados',
            'slug' => InstitutionalPages::TERMS_SLUG,
            'body' => '<p>Texto personalizado já aprovado.</p>',
            'status' => 'published',
        ]);
        $this->post('/contribua', $this->story());
        $oldSubmission = StorySubmission::sole();
        $oldVersion = $oldSubmission->acceptedTerms;
        $acceptedAt = $oldSubmission->terms_accepted_at->toIso8601String();
        $admin = $this->userWithRole(UserRole::Admin);

        $this->actingAs($admin)->get('/admin/site-pages/'.$page->id.'/edit')
            ->assertInertia(fn (Assert $response) => $response
                ->where('page.body', '<p>Texto personalizado já aprovado.</p>')
                ->where('termsDefaultBody', InstitutionalPages::default(InstitutionalPages::TERMS_SLUG)['body']));

        $update = [
            'title' => 'Termos de envio personalizados',
            'slug' => InstitutionalPages::TERMS_SLUG,
            'summary' => '',
            'body' => '<p>Nova redação publicada com aprovação expressa.</p>',
            'status' => 'published',
        ];
        $this->actingAs($admin)->put('/admin/site-pages/'.$page->id, $update)
            ->assertSessionHasErrors('terms_comparison_confirmed');
        $this->assertSame('<p>Texto personalizado já aprovado.</p>', $page->fresh()->body);
        $this->assertSame(1, TermsVersion::count());

        $this->actingAs($admin)->put('/admin/site-pages/'.$page->id, [...$update, 'terms_comparison_confirmed' => true])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, TermsVersion::count());
        $newVersion = TermsVersion::where('content_hash', hash('sha256', $update['title']."\n".$update['body']))->firstOrFail();
        $this->assertSame('pagina', $newVersion->source);
        $this->assertSame(hash('sha256', $newVersion->title."\n".$newVersion->body), $newVersion->content_hash);

        $oldSubmission->refresh();
        $this->assertSame($oldVersion->id, $oldSubmission->terms_version_id);
        $this->assertSame($acceptedAt, $oldSubmission->terms_accepted_at->toIso8601String());
        $this->assertSame('<p>Texto personalizado já aprovado.</p>', $oldSubmission->acceptedTerms->body);

        $this->post('/contribua', $this->story('Envio feito depois da nova publicação'));
        $newSubmission = StorySubmission::latest('id')->firstOrFail();
        $this->assertSame($newVersion->id, $newSubmission->terms_version_id);
        $this->assertSame($newVersion->content_hash, $newSubmission->acceptedTerms->content_hash);
    }

    public function test_recorded_acceptance_and_snapshots_cannot_be_rewritten(): void
    {
        $this->post('/contribua', $this->story());
        $submission = StorySubmission::sole();

        $submission->update(['status' => 'approved']);
        $this->assertSame('approved', $submission->refresh()->status);

        $this->expectException(LogicException::class);
        $submission->update(['terms_accepted_at' => now()->addDay()]);
    }

    public function test_terms_snapshots_cannot_be_updated_or_deleted(): void
    {
        $version = InstitutionalPages::currentTerms();

        try {
            $version->update(['body' => '<p>Alterado.</p>']);
            $this->fail('Snapshot was updated.');
        } catch (LogicException) {
        }
        $this->expectException(LogicException::class);
        $version->delete();
    }

    public function test_submissions_are_refused_while_the_terms_page_is_unpublished(): void
    {
        Storage::fake('local');
        SitePage::create(['title' => 'Termos em revisão', 'slug' => 'termos-de-contribuicao', 'body' => '<p>Rascunho.</p>', 'status' => 'draft']);

        $this->from('/contribua')->post('/contribua', [...$this->story(), 'photo' => UploadedFile::fake()->image('foto.jpg'), 'photo_credit' => 'Acervo', 'photo_publication_consent' => true])
            ->assertRedirect('/contribua')
            ->assertSessionHasErrors(['terms_accepted' => 'Os Termos de envio estão em revisão. Tente enviar novamente mais tarde.']);

        $this->assertDatabaseCount('story_submissions', 0);
        $this->assertDatabaseCount('terms_versions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_moderators_see_the_accepted_version_and_whether_it_predates_current_terms(): void
    {
        $this->post('/contribua', $this->story());
        $submission = StorySubmission::sole();
        $editor = $this->userWithRole(UserRole::Editor);

        $this->actingAs($editor)->get("/admin/submissions/{$submission->id}")->assertInertia(fn (Assert $page) => $page
            ->where('submission.accepted_terms.label', $submission->terms_version)
            ->where('currentTermsLabel', $submission->terms_version));

        SitePage::create(['title' => 'Termos atualizados', 'slug' => 'termos-de-contribuicao', 'body' => '<p>Novas condições.</p>', 'status' => 'published']);
        $this->actingAs($editor)->get("/admin/submissions/{$submission->id}")->assertInertia(fn (Assert $page) => $page
            ->where('submission.accepted_terms.label', $submission->terms_version)
            ->where('currentTermsLabel', fn (string $label) => $label !== $submission->terms_version));
        $this->assertSame(1, TermsVersion::count(), 'Viewing the moderation screen must not record versions.');

        $this->actingAs($editor)->get("/admin/terms-versions/{$submission->terms_version_id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/terms-versions/show')->where('acceptances', 1));
        $this->actingAs($this->userWithRole(UserRole::User))->get("/admin/terms-versions/{$submission->terms_version_id}")->assertForbidden();
    }
}

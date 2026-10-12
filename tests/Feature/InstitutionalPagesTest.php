<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\PortalSetting;
use App\Models\SitePage;
use App\Models\StorySubmission;
use App\Models\User;
use App\Support\InstitutionalPages;
use Database\Seeders\PortalContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstitutionalPagesTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user->refresh();
    }

    /** @return array<string, string> */
    private function contact(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria da Silva',
            'email' => 'maria@example.test',
            'subject' => 'Correção em um causo',
            'message' => 'Gostaria de corrigir o nome da cidade citada no causo publicado ontem.',
            'privacy_acknowledged' => true,
            'website' => '',
        ], $overrides);
    }

    public function test_footer_pages_show_default_content_without_seeded_records(): void
    {
        foreach (['proposta-cultural' => 'Nossa proposta cultural', 'contato' => 'Contato', 'termos-de-contribuicao' => 'Termos de envio de contribuições'] as $slug => $title) {
            $this->get("/{$slug}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('portal/institutional')
                    ->where('page.slug', $slug)
                    ->where('page.title', $title)
                    ->where('indexable', true)
                    ->where('page.body', fn (string $body) => str_contains($body, '<p>')));
        }

        $this->assertDatabaseCount('site_pages', 0);
    }

    public function test_default_texts_cover_the_required_topics(): void
    {
        $proposal = InstitutionalPages::default('proposta-cultural')['body'];
        $this->assertStringContainsString('href="/contribua"', $proposal);

        $terms = InstitutionalPages::default('termos-de-contribuicao')['body'];
        $this->assertSame(13, substr_count($terms, '<h2>'));
        $this->assertStringNotContainsStringIgnoringCase('irrevogável', $terms);
        $this->assertStringContainsString('não transfere a propriedade', $terms);
        $this->assertStringContainsString('/politica-de-privacidade', $terms);
    }

    public function test_saved_page_takes_precedence_and_draft_hides_the_default(): void
    {
        SitePage::create(['title' => 'Proposta revisada', 'slug' => 'proposta-cultural', 'summary' => 'Resumo', 'body' => '<p>Texto da equipe.</p>', 'status' => 'published']);
        $this->get('/proposta-cultural')->assertInertia(fn (Assert $page) => $page->where('page.title', 'Proposta revisada'));

        SitePage::create(['title' => 'Contato em revisão', 'slug' => 'contato', 'body' => '<p>Rascunho.</p>', 'status' => 'draft']);
        $this->get('/contato')->assertNotFound();
    }

    public function test_empty_saved_page_is_not_indexable(): void
    {
        SitePage::create(['title' => 'Termos', 'slug' => 'termos-de-contribuicao', 'body' => '<p> </p>', 'status' => 'published']);

        $this->get('/termos-de-contribuicao')->assertInertia(fn (Assert $page) => $page->where('indexable', false));
    }

    public function test_seeder_is_idempotent_and_keeps_edited_pages(): void
    {
        SitePage::create(['title' => 'Contato da redação', 'slug' => 'contato', 'body' => '<p>Editado.</p>', 'status' => 'published']);

        $this->seed(PortalContentSeeder::class);
        $this->seed(PortalContentSeeder::class);

        $this->assertSame(1, SitePage::where('slug', 'contato')->count());
        $this->assertSame('Contato da redação', SitePage::where('slug', 'contato')->value('title'));
        $this->assertSame('Termos de envio de contribuições', SitePage::where('slug', 'termos-de-contribuicao')->value('title'));
    }

    public function test_admin_can_customize_a_default_page_and_editors_cannot(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);

        $this->actingAs($admin)->get('/admin/site-pages')
            ->assertInertia(fn (Assert $page) => $page->where('defaultPages', fn ($pages) => collect($pages)->pluck('slug')->contains('contato')));
        $this->actingAs($admin)->get('/admin/site-pages/create?modelo=termos-de-contribuicao')
            ->assertInertia(fn (Assert $page) => $page
                ->where('template.slug', 'termos-de-contribuicao')
                ->where('template.status', 'published')
                ->where('termsDefaultBody', InstitutionalPages::default('termos-de-contribuicao')['body']));

        $this->actingAs($this->userWithRole(UserRole::Editor))->get('/admin/site-pages/create?modelo=contato')->assertForbidden();
    }

    public function test_legal_admin_forms_expose_error_links_and_confirm_terms_review_before_publishing(): void
    {
        $termsForm = File::get(resource_path('js/pages/admin/site-pages/form.tsx'));
        $settingsForm = File::get(resource_path('js/pages/admin/settings/portal.tsx'));

        $this->assertStringContainsString('aria-invalid={Boolean(form.errors.body)}', $termsForm);
        $this->assertStringContainsString('site-page-body-error', $termsForm);
        $this->assertStringContainsString('aria-describedby=', $termsForm);
        $this->assertStringContainsString('terms_comparison_confirmed', $termsForm);
        $this->assertStringContainsString('window.confirm(', $termsForm);
        $this->assertStringContainsString('aria-describedby={form.errors.legal_controller_name', $settingsForm);
        $this->assertStringContainsString('legalReview.productionReviewed', $settingsForm);
    }

    public function test_contact_message_is_stored_and_forwarded_when_an_address_is_configured(): void
    {
        Mail::fake();
        PortalSetting::create(['key' => 'contact_email', 'value' => 'redacao@example.test']);

        $this->post('/contato', $this->contact())->assertRedirect('/contato')->assertSessionHas('success');

        $message = ContactMessage::sole();
        $this->assertSame('maria@example.test', $message->email);
        $this->assertNotNull($message->privacy_acknowledged_at);
        $this->assertNotNull($message->forwarded_at);
        Mail::assertSent(ContactMessageReceived::class, fn (ContactMessageReceived $mail) => $mail->hasTo('redacao@example.test') && $mail->hasReplyTo('maria@example.test'));
    }

    public function test_contact_message_is_stored_without_e_mail_when_no_address_is_configured(): void
    {
        Mail::fake();

        $this->post('/contato', $this->contact())->assertSessionHas('success');

        $this->assertNull(ContactMessage::sole()->forwarded_at);
        Mail::assertNothingSent();
    }

    public function test_contact_form_validates_input_and_requires_privacy_acknowledgement(): void
    {
        $this->from('/contato')->post('/contato', $this->contact(['email' => 'invalido', 'message' => 'curta', 'privacy_acknowledged' => false]))
            ->assertRedirect('/contato')
            ->assertSessionHasErrors(['email', 'message', 'privacy_acknowledged']);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_validation_messages_are_translated_instead_of_raw_keys(): void
    {
        $this->from('/contato')->post('/contato', $this->contact(['email' => 'invalido', 'privacy_acknowledged' => false]))
            ->assertSessionHasErrors([
                'email' => 'O campo e-mail deve ser um endereço de e-mail válido.',
                'privacy_acknowledged' => 'Confirme que leu a Política de Privacidade para enviar a mensagem.',
            ]);
    }

    public function test_contact_honeypot_discards_and_rate_limit_blocks_floods(): void
    {
        $this->post('/contato', $this->contact(['website' => 'http://spam.test']))->assertRedirect('/contato');
        $this->assertDatabaseCount('contact_messages', 0);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/contato', $this->contact())->assertRedirect('/contato');
        }
        $this->post('/contato', $this->contact())->assertTooManyRequests();
    }

    public function test_only_administrators_manage_contact_messages(): void
    {
        $message = ContactMessage::create([...$this->contact(), 'privacy_acknowledged_at' => now()]);
        $admin = $this->userWithRole(UserRole::Admin);

        $this->actingAs($this->userWithRole(UserRole::Editor))->get('/admin/contact-messages')->assertForbidden();
        $this->actingAs($admin)->get('/admin/contact-messages')
            ->assertInertia(fn (Assert $page) => $page->component('admin/contact-messages/index')->where('unread', 1));

        $this->actingAs($admin)->patch("/admin/contact-messages/{$message->id}/read")->assertRedirect();
        $this->assertNotNull($message->refresh()->read_at);

        $this->actingAs($admin)->delete("/admin/contact-messages/{$message->id}")->assertRedirect();
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_contribution_requires_and_records_terms_acceptance(): void
    {
        $story = [
            'title' => 'A lembrança da estação antiga',
            'story' => str_repeat('Minha avó contava como a estação enchia de gente nas manhãs de domingo. ', 2),
            'rights_confirmed' => true,
            'publication_consent' => true,
            'website' => '',
        ];

        $this->from('/contribua')->post('/contribua', $story)->assertSessionHasErrors('terms_accepted');
        $this->assertDatabaseCount('story_submissions', 0);

        $this->post('/contribua', [...$story, 'terms_accepted' => true])->assertRedirect('/contribua/obrigado');
        $submission = StorySubmission::sole();
        $this->assertNotNull($submission->terms_accepted_at);
        $this->assertSame($submission->acceptedTerms->label, $submission->terms_version);
        $this->assertSame('padrao', $submission->acceptedTerms->source);
    }

    public function test_public_pages_publish_seo_metadata(): void
    {
        $seo = file_get_contents(resource_path('js/components/portal-seo.tsx'));
        $this->assertStringContainsString('rel="canonical"', $seo);
        $this->assertStringContainsString('property="og:title"', $seo);
        $this->assertStringContainsString('noindex, follow', $seo);
        $this->assertStringContainsString('<PortalSeo', file_get_contents(resource_path('js/pages/portal/institutional.tsx')));
        $this->assertStringContainsString('noindex', file_get_contents(resource_path('js/pages/portal/thanks.tsx')));

        $this->get('/contato')->assertInertia(fn (Assert $page) => $page->where('appUrl', rtrim(config('app.url'), '/')));
    }
}

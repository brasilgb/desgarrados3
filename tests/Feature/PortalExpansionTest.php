<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Media;
use App\Models\Publication;
use App\Models\PublicationSeries;
use App\Models\SitePage;
use App\Models\StorySubmission;
use App\Models\User;
use Database\Seeders\PortalContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalExpansionTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user->refresh();
    }

    public function test_portal_layout_is_composed_once_and_admin_has_its_own_shell(): void
    {
        $app = File::get(resource_path('js/app.tsx'));
        $public = File::get(resource_path('js/layouts/public-layout.tsx'));
        $admin = File::get(resource_path('js/layouts/admin-layout.tsx'));

        $this->assertStringContainsString("case name.startsWith('portal/')", $app);
        $this->assertSame(1, substr_count($public, '<header'));
        $this->assertSame(1, substr_count($public, '<footer'));
        $this->assertSame(1, substr_count($admin, '<header'));
        $this->assertStringNotContainsString('PublicLayout', $admin);

        foreach (['home', 'notebook', 'show', 'contribute', 'thanks', 'institutional', 'collection', 'sayings', 'not-found'] as $page) {
            $component = File::get(resource_path("js/pages/portal/{$page}.tsx"));
            $this->assertStringNotContainsString('PublicLayout', $component, "{$page} must rely on the single global public layout.");
        }
    }

    public function test_public_photo_gallery_only_includes_images_linked_to_live_stories(): void
    {
        $author = User::factory()->create();
        $media = Media::create(['user_id' => $author->id, 'path' => 'media/community/authorized.jpg', 'original_name' => 'memoria.jpg', 'alt_text' => 'Antiga estação ferroviária', 'credit' => 'Acervo da comunidade', 'mime_type' => 'image/jpeg', 'size' => 1200]);
        Publication::create(['user_id' => $author->id, 'title' => 'A estação de volta', 'slug' => 'estacao-de-volta', 'body' => '<p>Uma lembrança.</p>', 'excerpt' => 'Memória local.', 'type' => 'historia', 'origin_city' => 'Bagé', 'origin_state' => 'RS', 'cover_path' => $media->path, 'status' => 'published', 'published_at' => now()]);
        Publication::create(['user_id' => $author->id, 'title' => 'Foto ainda em revisão', 'slug' => 'foto-em-revisao', 'body' => '<p>Rascunho editorial.</p>', 'type' => 'causo', 'cover_path' => $media->path, 'status' => 'draft']);

        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->component('portal/home')
            ->has('photographicMemories', 1)
            ->where('photographicMemories.0.title', 'A estação de volta')
            ->where('photographicMemories.0.cover_media.credit', 'Acervo da comunidade')
            ->where('photographicMemories.0.origin_city', 'Bagé'));
    }

    public function test_admin_login_reaches_dashboard_and_editor_cannot_open_admin_settings(): void
    {
        config(['legal.reviewed_for_production' => false]);
        $admin = $this->userWithRole(UserRole::Admin);
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertInertia(fn ($page) => $page->component('admin/dashboard'));
        $this->put('/admin/settings/portal', [
            'portal_name' => 'Desgarrados', 'portal_description' => 'Memória e pertencimento.',
            'institutional_intro' => 'Um espaço cultural do Sul.', 'logo_path' => '', 'share_image_path' => '',
            'contact_email' => 'contato@example.test', 'instagram_url' => '', 'facebook_url' => '',
            'legal_controller_type' => 'individual', 'legal_controller_name' => 'Controlador cadastrado', 'legal_controller_document' => '', 'legal_controller_address' => '', 'legal_controller_identity_verified' => true,
            'legal_controller_public_identity_authorized' => false, 'legal_controller_personal_details_public' => false,
            'youtube_url' => '', 'show_sayings' => true, 'show_collections' => true, 'show_categories' => true,
        ])->assertRedirect();
        $this->assertDatabaseHas('portal_settings', ['key' => 'contact_email', 'value' => 'contato@example.test']);
        $this->assertDatabaseHas('portal_settings', ['key' => 'legal_controller_name', 'value' => 'Controlador cadastrado']);
        $this->assertDatabaseHas('portal_settings', ['key' => 'legal_controller_identity_verified', 'value' => '1']);
        $this->get('/admin/settings/portal')->assertInertia(fn ($page) => $page
            ->where('legalReview.identityConfirmed', true)
            ->where('legalReview.publicIdentityAuthorized', false)
            ->where('legalReview.personalDetailsAuthorized', false)
            ->where('legalReview.productionReviewed', false));
        $this->post('/logout')->assertRedirect('/');

        $editor = $this->userWithRole(UserRole::Editor);
        $this->actingAs($editor)->get('/admin/settings/portal')->assertForbidden();
        $this->actingAs($editor)->get('/admin/site-pages')->assertForbidden();
        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
        $this->actingAs($editor)->get('/admin')->assertOk();

        $regular = $this->userWithRole(UserRole::User);
        $this->actingAs($regular)->get('/admin')->assertForbidden();
    }

    public function test_collection_series_and_chapter_order_are_visible_on_publication_pages(): void
    {
        $editor = $this->userWithRole(UserRole::Editor);
        $this->actingAs($editor)->post('/admin/collections', ['name' => 'Memórias da terra natal', 'description' => 'Caminhos de volta.'])->assertRedirect();
        $this->actingAs($editor)->post('/admin/series', ['title' => 'Cartas de viagem', 'description' => 'Uma história em duas partes.'])->assertRedirect();
        $collection = Collection::firstOrFail();
        $series = PublicationSeries::firstOrFail();

        foreach ([['Parte um', 1], ['Parte dois', 2]] as [$title, $position]) {
            $this->actingAs($editor)->post('/admin/publications', [
                'title' => $title, 'body' => '<p>Conteúdo ficcional de teste.</p>', 'type' => 'historia',
                'status' => 'published', 'series_id' => $series->id, 'series_position' => $position,
                'collection_ids' => [$collection->id],
            ])->assertRedirect('/admin/publications');
        }

        $first = Publication::where('title', 'Parte um')->firstOrFail();
        $this->get('/publicacao/'.$first->slug)->assertOk()->assertInertia(fn ($page) => $page->component('portal/show')->has('chapters', 2)->where('chapters.0.title', 'Parte um')->where('publication.collections.0.name', 'Memórias da terra natal'));
        $this->get('/colecoes/'.$collection->slug)->assertOk()->assertInertia(fn ($page) => $page->component('portal/collection')->has('publications.data', 2));
    }

    public function test_editorial_pages_are_managed_by_admin_and_sanitized_before_public_display(): void
    {
        $this->seed(PortalContentSeeder::class);
        $admin = $this->userWithRole(UserRole::Admin);
        $page = SitePage::where('slug', 'proposta-cultural')->firstOrFail();

        $this->actingAs($admin)->put('/admin/site-pages/'.$page->id, [
            'title' => $page->title, 'slug' => $page->slug, 'summary' => 'Como cuidamos dos relatos.',
            'body' => '<p>Texto atualizado.</p><script>alert(1)</script>', 'status' => 'published',
        ])->assertRedirect();
        $this->get('/proposta-cultural')->assertOk()->assertInertia(fn ($response) => $response->component('portal/institutional')->where('page.body', '<p>Texto atualizado.</p>'));
        $this->get('/sobre')->assertOk();
    }

    public function test_about_page_has_a_public_fallback_when_portal_content_is_not_seeded(): void
    {
        $this->get('/sobre')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('portal/institutional')
                ->where('page.slug', 'sobre')
                ->where('page.title', 'Sobre o Desgarrados'));
    }

    public function test_optional_contact_email_requires_consent_stays_private_and_is_not_copied_to_publication(): void
    {
        $story = ['title' => 'A estrada depois da chuva', 'story' => str_repeat('Uma lembrança de casa que atravessou a mudança. ', 2), 'rights_confirmed' => true, 'publication_consent' => true, 'terms_accepted' => true, 'contact_email' => 'reader@example.test', 'contact_consent' => false];
        $this->from('/contribua')->post('/contribua', $story)->assertSessionHasErrors('contact_consent');

        $story['contact_consent'] = true;
        $this->post('/contribua', $story)->assertRedirect('/contribua/obrigado');
        $submission = StorySubmission::firstOrFail();
        $this->assertSame('reader@example.test', $submission->contact_email);
        $this->get('/')->assertDontSee('reader@example.test');

        $editor = $this->userWithRole(UserRole::Editor);
        $this->actingAs($editor)->post('/admin/submissions/'.$submission->id.'/approve', ['moderation_note' => 'Autorização revisada.'])->assertRedirect();
        $this->actingAs($editor)->post('/admin/submissions/'.$submission->id.'/convert', ['type' => 'historia'])->assertRedirect();
        $publication = $submission->refresh()->publication;
        $this->assertSame('draft', $publication->status);
        $this->assertArrayNotHasKey('contact_email', $publication->getAttributes());
    }

    public function test_sayings_are_published_only_when_selected_and_unknown_paths_use_portal_404(): void
    {
        $this->seed(PortalContentSeeder::class);
        $this->get('/ditados')->assertOk()->assertInertia(fn ($page) => $page->component('portal/sayings')->has('sayings', 3));
        $this->get('/missing-memory')->assertNotFound()->assertInertia(fn ($page) => $page->component('portal/not-found'));
    }

    public function test_publication_workflow_runs_from_editor_preview_to_public_portal_and_update(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');
        $category = Category::create(['name' => 'Memória e pertencimento', 'slug' => 'memoria-pertencimento']);
        Storage::fake('public');
        $this->post('/admin/media', [
            'file' => UploadedFile::fake()->image('capa-homologacao.jpg'),
            'alt_text' => 'Retrato da comunidade', 'credit' => 'Acervo de homologação',
        ])->assertRedirect();
        $media = Media::firstOrFail();
        Storage::disk('public')->assertExists($media->path);
        $body = '<p>Uma lembrança <strong>bem formatada</strong> segue viagem.</p><figure><img src="/storage/'.$media->path.'" alt="Retrato da comunidade"><figcaption>Acervo da comunidade</figcaption></figure><script>alert(1)</script>';
        $payload = [
            'title' => 'Homologação NEXT08: lembranças em movimento', 'excerpt' => 'Um registro temporário para validar a publicação.',
            'body' => $body, 'type' => 'historia', 'category_id' => $category->id, 'cover_path' => $media->path,
            'credit' => 'Comunidade local', 'origin_city' => 'Porto Alegre', 'origin_state' => 'RS',
            'status' => 'draft', 'is_featured' => true,
        ];

        $this->actingAs($admin)->get('/admin')->assertOk()->assertInertia(fn ($page) => $page->component('admin/dashboard'));
        $this->post('/admin/publications', $payload)->assertRedirect('/admin/publications');
        $publication = Publication::where('title', $payload['title'])->firstOrFail();
        $this->assertSame('draft', $publication->status);
        $this->assertFalse($publication->is_featured === true && $publication->status === 'published');
        $this->get('/publicacao/'.$publication->slug)->assertNotFound();

        $this->post('/admin/publications/preview', $payload)->assertOk()->assertInertia(fn ($page) => $page
            ->component('admin/publications/preview')
            ->where('publication.title', $payload['title'])
            ->where('publication.category.name', $category->name)
            ->where('publication.cover_media.alt_text', 'Retrato da comunidade')
            ->where('publication.body', '<p>Uma lembrança <strong>bem formatada</strong> segue viagem.</p><figure><img src="/storage/'.$media->path.'" alt="Retrato da comunidade" loading="lazy"><figcaption>Acervo da comunidade</figcaption></figure>'));
        $this->assertSame('draft', $publication->fresh()->status, 'A prévia não deve publicar ou salvar alterações.');

        $payload['status'] = 'published';
        $this->put('/admin/publications/'.$publication->id, $payload)->assertRedirect('/admin/publications');
        $publication->refresh();
        $this->assertSame('published', $publication->status);
        $this->assertTrue($publication->is_featured);
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('portal/home')->where('featured.title', $publication->title));
        $this->get('/publicacao/'.$publication->slug)->assertOk()->assertInertia(fn ($page) => $page
            ->component('portal/show')
            ->where('publication.category.name', $category->name)
            ->where('publication.cover_media.alt_text', 'Retrato da comunidade')
            ->where('publication.body', $publication->body));
        $this->delete('/admin/media/'.$media->id)->assertRedirect();
        $this->assertDatabaseHas('media', ['id' => $media->id, 'path' => $media->path]);
        Storage::disk('public')->assertExists($media->path);

        $payload['title'] = 'Homologação NEXT08: conteúdo atualizado';
        $payload['excerpt'] = 'O editor também atualiza o conteúdo já publicado.';
        $payload['body'] = '<p>Atualização <em>confirmada</em> no portal público.</p>';
        $this->put('/admin/publications/'.$publication->id, $payload)->assertRedirect('/admin/publications');
        $publication->refresh();
        $this->assertSame('published', $publication->status);
        $this->get('/publicacao/'.$publication->slug)->assertOk()->assertInertia(fn ($page) => $page
            ->component('portal/show')
            ->where('publication.title', $payload['title'])
            ->where('publication.body', $payload['body']));

        $scheduledPayload = $payload;
        $scheduledPayload['title'] = 'Homologação NEXT08: publicação agendada';
        $scheduledPayload['status'] = 'scheduled';
        $scheduledPayload['is_featured'] = false;
        $scheduledPayload['published_at'] = now()->addDays(2)->format('Y-m-d\TH:i');
        $this->post('/admin/publications', $scheduledPayload)->assertRedirect('/admin/publications');
        $scheduled = Publication::where('title', $scheduledPayload['title'])->firstOrFail();
        $this->assertSame('scheduled', $scheduled->status);
        $this->get('/publicacao/'.$scheduled->slug)->assertNotFound();
        $this->get('/admin/publications?status=scheduled')->assertOk()->assertInertia(fn ($page) => $page
            ->component('admin/publications/index')
            ->where('publications.data.0.status', 'scheduled')
            ->where('publications.data.0.title', $scheduled->title));
        $this->get('/admin')->assertOk()->assertInertia(fn ($page) => $page->where('stats.scheduled', 1));
    }

    public function test_duplicating_a_publication_creates_an_unfeatured_draft(): void
    {
        $editor = $this->userWithRole(UserRole::Editor);
        $collection = Collection::create(['name' => 'Coleção para duplicação', 'slug' => 'colecao-duplicacao']);
        $source = Publication::create([
            'user_id' => $editor->id, 'title' => 'Publicação original para duplicar', 'slug' => 'publicacao-original-duplicar',
            'body' => '<p>Conteúdo original.</p>', 'type' => 'historia', 'status' => 'published',
            'published_at' => now(), 'is_featured' => true,
        ]);
        $source->collections()->sync([$collection->id]);

        $this->actingAs($editor)->post('/admin/publications/'.$source->id.'/duplicate')->assertRedirect();
        $copy = Publication::where('title', 'Publicação original para duplicar (cópia)')->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertNull($copy->published_at);
        $this->assertFalse($copy->is_featured);
        $this->assertNull($copy->series_id);
        $this->assertSame(['Coleção para duplicação'], $copy->collections()->pluck('name')->all());
        $this->get('/publicacao/'.$copy->slug)->assertNotFound();
    }
}

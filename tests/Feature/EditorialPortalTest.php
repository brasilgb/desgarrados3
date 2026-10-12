<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Media;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditorialPortalTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::Editor, 'email_verified_at' => now()])->save();

        return $user->refresh();
    }

    public function test_seeded_portal_and_publication_pages_render(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('publications', 7);
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('portal/home')->has('publications.data', 0));
        foreach (['historias', 'causos', 'anedotas', 'poesia', 'literatura', 'musica', 'tradicoes'] as $notebook) {
            $this->get('/cadernos/'.$notebook)->assertOk()->assertInertia(fn ($page) => $page->component('portal/notebook'));
        }
        $this->get('/publicacao/a-janela-de-onde-se-via-a-estacao')->assertNotFound();
        $this->assertDatabaseHas('publications', ['slug' => 'a-janela-de-onde-se-via-a-estacao', 'status' => 'draft', 'published_at' => null]);
    }

    public function test_editor_can_publish_content_and_it_appears_in_public_search(): void
    {
        $editor = $this->editor();
        $category = Category::create(['name' => 'Memórias', 'slug' => 'memorias']);

        $this->actingAs($editor)->post('/admin/publications', [
            'title' => 'Carta para casa', 'excerpt' => 'Uma lembrança compartilhada.', 'body' => 'Texto original de demonstração.',
            'type' => 'historia', 'category_id' => $category->id, 'status' => 'published', 'credit' => 'Equipe editorial',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/publications');

        $publication = Publication::where('title', 'Carta para casa')->firstOrFail();
        $this->assertSame('published', $publication->status);
        $this->assertNotNull($publication->published_at);
        $this->get('/?q=Carta')->assertOk()->assertInertia(fn ($page) => $page->has('publications.data', 1)->where('publications.data.0.title', 'Carta para casa'));
        $this->get('/publicacao/carta-para-casa')->assertOk();
    }

    public function test_publication_management_requires_an_authorized_editor(): void
    {
        $this->get('/admin/publications')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/publications')->assertForbidden();
    }

    public function test_editor_can_upload_credited_image_to_media_library(): void
    {
        Storage::fake('public');
        $editor = $this->editor();

        $this->actingAs($editor)->post('/admin/media', [
            'file' => UploadedFile::fake()->image('campo.jpg'), 'alt_text' => 'Paisagem de demonstração', 'credit' => 'Acervo de demonstração',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('media', ['original_name' => 'campo.jpg', 'alt_text' => 'Paisagem de demonstração', 'credit' => 'Acervo de demonstração']);
        $this->assertCount(1, Storage::disk('public')->allFiles('media'));
    }

    public function test_scheduled_publication_appears_when_its_release_time_arrives(): void
    {
        $editor = $this->editor();
        $release = now()->addDay();
        $this->actingAs($editor)->post('/admin/publications', [
            'title' => 'Lembrança agendada', 'body' => 'Texto preparado para publicação futura.', 'type' => 'historia',
            'status' => 'scheduled', 'published_at' => $release->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $publication = Publication::where('title', 'Lembrança agendada')->firstOrFail();
        $this->assertSame('scheduled', $publication->status);
        $this->get('/')->assertInertia(fn ($page) => $page->where('publications.data', fn ($items) => collect($items)->doesntContain(fn ($item) => $item['title'] === 'Lembrança agendada')));

        $this->travelTo($publication->published_at->addMinute());
        $this->get('/publicacao/lembranca-agendada')->assertOk();
    }

    public function test_editor_preview_is_private_and_does_not_create_a_publication(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor)->post('/admin/publications/preview', [
            'title' => 'Prévia privada', 'excerpt' => 'Resumo de prévia', 'body' => '<h2>Texto de teste</h2><script>alert(1)</script>',
            'type' => 'poema', 'status' => 'draft', 'seo_title' => 'Título para busca',
        ])->assertOk()->assertInertia(fn ($page) => $page->component('admin/publications/preview')->where('publication.body', '<h2>Texto de teste</h2>')->where('publication.seo_title', 'Título para busca'));

        $this->assertDatabaseMissing('publications', ['title' => 'Prévia privada']);
        $this->get('/publicacao/previa-privada')->assertNotFound();
    }

    public function test_publication_content_is_sanitized_before_it_is_saved(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor)->post('/admin/publications', [
            'title' => 'Texto seguro', 'body' => '<h2>Memória</h2><script>alert(1)</script><p onclick="alert(1)"><a href="javascript:alert(1)">Leia</a><img src="/storage/fora.jpg" onerror="alert(1)"></p>',
            'type' => 'historia', 'status' => 'published',
        ])->assertSessionHasNoErrors();

        $body = Publication::where('title', 'Texto seguro')->firstOrFail()->body;
        $this->assertStringContainsString('<h2>Memória</h2>', $body);
        $this->assertStringContainsString('<a>Leia</a>', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('<img', $body);
    }

    public function test_editor_can_edit_a_draft_and_publish_it(): void
    {
        $editor = $this->editor();
        $draft = Publication::create(['user_id' => $editor->id, 'title' => 'Rascunho inicial', 'slug' => 'rascunho-inicial', 'body' => '<p>Primeiro texto.</p>', 'type' => 'historia', 'status' => 'draft']);

        $this->actingAs($editor)->get('/admin/publications/'.$draft->id.'/edit')->assertOk()->assertInertia(fn ($page) => $page->component('admin/publications/form')->where('publication.title', 'Rascunho inicial'));
        $this->put('/admin/publications/'.$draft->id, [
            'title' => 'História publicada', 'excerpt' => 'Uma lembrança.', 'body' => '<h2>Texto revisado</h2>',
            'type' => 'historia', 'status' => 'published', 'seo_title' => 'História · Desgarrados', 'seo_description' => 'Uma memória compartilhada.',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/publications');

        $this->assertSame('published', $draft->refresh()->status);
        $this->get('/publicacao/historia-publicada')->assertOk()->assertInertia(fn ($page) => $page->where('publication.seo_title', 'História · Desgarrados')->where('publication.body', '<h2>Texto revisado</h2>'));

        $this->put('/admin/publications/'.$draft->id, [
            'title' => 'História publicada', 'excerpt' => 'Uma lembrança.', 'body' => '<h2>Texto revisado</h2>',
            'type' => 'historia', 'status' => 'draft', 'seo_title' => 'História · Desgarrados', 'seo_description' => 'Uma memória compartilhada.',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/publications');

        $this->assertSame('draft', $draft->refresh()->status);
        $this->get('/publicacao/historia-publicada')->assertNotFound();
    }

    public function test_embedded_image_must_belong_to_the_media_library(): void
    {
        $editor = $this->editor();
        Media::create(['user_id' => $editor->id, 'path' => 'media/licenciada.jpg', 'original_name' => 'licenciada.jpg', 'alt_text' => 'Campo ao entardecer', 'credit' => 'Acervo autorizado', 'mime_type' => 'image/jpeg', 'size' => 1200]);

        $this->actingAs($editor)->post('/admin/publications', [
            'title' => 'Imagem creditada', 'body' => '<figure><img src="/storage/media/licenciada.jpg" alt="Campo ao entardecer" onerror="alert(1)"><figcaption>Crédito: Acervo autorizado</figcaption></figure>',
            'type' => 'literatura', 'status' => 'published',
        ])->assertSessionHasNoErrors();

        $body = Publication::where('title', 'Imagem creditada')->firstOrFail()->body;
        $this->assertStringContainsString('/storage/media/licenciada.jpg', $body);
        $this->assertStringContainsString('loading="lazy"', $body);
        $this->assertStringNotContainsString('onerror', $body);
    }
}

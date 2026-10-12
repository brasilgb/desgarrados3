<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Publication;
use App\Models\PublicationRevision;
use App\Models\TerritorialUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user->refresh();
    }

    /** @return array<string, mixed> */
    private function draftData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Rascunho operacional', 'excerpt' => 'Resumo inicial.', 'body' => '<p>Texto inicial.</p>',
            'type' => 'historia', 'category_id' => null, 'credit' => null, 'origin_state' => null,
            'origin_city' => null, 'cover_path' => null, 'status' => 'draft', 'published_at' => null,
            'seo_title' => null, 'seo_description' => null, 'is_featured' => false,
            'collection_ids' => [], 'series_id' => null, 'series_position' => null,
        ], $overrides);
    }

    public function test_filtered_publications_support_author_type_dates_and_preserved_query_parameters(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $other = $this->userWithRole(UserRole::Editor);
        $selected = Publication::create([
            'user_id' => $other->id, 'title' => 'Poema revisado', 'slug' => 'poema-revisado', 'body' => '<p>Um poema.</p>',
            'type' => 'poema', 'status' => 'draft', 'created_at' => now()->subDay(),
        ]);
        Publication::create([
            'user_id' => $admin->id, 'title' => 'História publicada', 'slug' => 'historia-publicada', 'body' => '<p>Uma história.</p>',
            'type' => 'historia', 'status' => 'published', 'published_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/publications?author='.$other->id.'&type=poema&status=draft&from='.now()->subDays(2)->toDateString().'&to='.now()->toDateString())
            ->assertOk()->assertInertia(fn ($page) => $page
            ->component('admin/publications/index')
            ->where('publications.data.0.id', $selected->id)
            ->where('filters.author', (string) $other->id)
            ->where('filters.type', 'poema'));
    }

    public function test_bulk_changes_create_revisions_and_restoring_preserves_original_author(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $author = $this->userWithRole(UserRole::Editor);
        $publication = Publication::create([
            'user_id' => $author->id, 'title' => 'Caderno de origem', 'slug' => 'caderno-de-origem',
            'body' => '<p>Texto da versão anterior.</p>', 'type' => 'historia', 'status' => 'published',
            'published_at' => now(), 'is_featured' => true,
        ]);

        $this->actingAs($admin)->post('/admin/publications/bulk?status=published&type=historia', [
            'ids' => [$publication->id], 'action' => 'set_notebook', 'type' => 'poema',
        ])->assertRedirect('/admin/publications?status=published&type=poema');
        $publication->refresh();
        $this->assertSame('poema', $publication->type);
        $this->assertSame(1, $publication->lock_version);

        $revision = PublicationRevision::where('publication_id', $publication->id)->firstOrFail();
        $this->assertSame('historia', $revision->snapshot['type']);
        $this->actingAs($admin)->get('/admin/publications/'.$publication->id.'/revisions')
            ->assertOk()->assertInertia(fn ($page) => $page
            ->component('admin/publications/revisions')
            ->where('revisions.0.editor.name', $admin->name)
            ->where('revisions.0.snapshot.type', 'historia')
            ->where('current.type', 'poema'));

        $this->post('/admin/publications/'.$publication->id.'/revisions/'.$revision->id.'/restore')
            ->assertRedirect('/admin/publications/'.$publication->id.'/edit');
        $publication->refresh();
        $this->assertSame('historia', $publication->type);
        $this->assertSame($author->id, $publication->user_id);
        $this->assertSame(2, $publication->lock_version);
        $this->assertSame($revision->id, $publication->revisions()->firstOrFail()->restored_from_revision_id);

        $this->post('/admin/publications/bulk', ['ids' => [$publication->id], 'action' => 'archive'])
            ->assertRedirect('/admin/publications?status=archived');
        $publication->refresh();
        $this->assertSame('archived', $publication->status);
        $this->assertFalse($publication->is_featured);
        $this->assertSame(3, $publication->lock_version);
    }

    public function test_draft_autosave_is_versioned_sanitized_and_never_publishes(): void
    {
        $editor = $this->userWithRole(UserRole::Editor);
        $publication = Publication::create([
            'user_id' => $editor->id, 'title' => 'Rascunho persistido', 'slug' => 'rascunho-persistido',
            'body' => '<p>Antes do autosave.</p>', 'type' => 'historia', 'status' => 'draft',
        ]);
        $payload = $this->draftData([
            'title' => 'Rascunho salvo automaticamente',
            'body' => '<p>Alteração segura.</p><script>alert(1)</script>',
            'status' => 'published',
            'lock_version' => 0,
        ]);

        $this->actingAs($editor)->putJson('/admin/publications/'.$publication->id.'/autosave', $payload)
            ->assertOk()->assertJsonPath('lock_version', 1);
        $publication->refresh();
        $this->assertSame('Rascunho salvo automaticamente', $publication->title);
        $this->assertSame('<p>Alteração segura.</p>', $publication->body);
        $this->assertSame('draft', $publication->status);
        $this->get('/publicacao/'.$publication->slug)->assertNotFound();

        $payload['title'] = 'Tentativa com versão antiga';
        $this->putJson('/admin/publications/'.$publication->id.'/autosave', $payload)->assertStatus(409);
        $this->assertSame('Rascunho salvo automaticamente', $publication->fresh()->title);

        $publication->forceFill(['status' => 'published', 'published_at' => now()])->save();
        $payload['lock_version'] = 1;
        $this->putJson('/admin/publications/'.$publication->id.'/autosave', $payload)->assertStatus(409);
    }

    public function test_publication_actions_are_forbidden_to_regular_accounts(): void
    {
        $user = $this->userWithRole(UserRole::User);
        $publication = Publication::create([
            'user_id' => $user->id, 'title' => 'Sem acesso editorial', 'slug' => 'sem-acesso-editorial',
            'body' => '<p>Privado.</p>', 'type' => 'historia', 'status' => 'draft',
        ]);
        $this->actingAs($user)->post('/admin/publications/bulk', ['ids' => [$publication->id], 'action' => 'archive'])->assertForbidden();
        $this->actingAs($user)->putJson('/admin/publications/'.$publication->id.'/autosave', [])->assertForbidden();
    }

    public function test_editor_can_publish_with_official_municipality_and_structured_references(): void
    {
        $editor = $this->userWithRole(UserRole::Editor);
        TerritorialUnit::create(['ibge_code' => 43, 'unit_type' => 'state', 'name' => 'Rio Grande do Sul', 'state_code' => 'RS', 'state_name' => 'Rio Grande do Sul', 'region_name' => 'Sul']);
        TerritorialUnit::create(['ibge_code' => 4300406, 'unit_type' => 'municipality', 'name' => 'Alegrete', 'state_code' => 'RS', 'state_name' => 'Rio Grande do Sul', 'region_name' => 'Sul']);
        $memory = Category::create(['name' => 'Memória', 'slug' => 'memoria']);
        $migration = Category::create(['name' => 'Migração', 'slug' => 'migracao']);

        $this->actingAs($editor)->post('/admin/publications', $this->draftData([
            'title' => 'Memória editorial de Alegrete', 'status' => 'published', 'origin_scope' => 'municipality',
            'origin_state' => 'RS', 'municipality_code' => '4300406',
            'category_ids' => [$memory->id, $migration->id],
            'source_references' => [['name' => 'Arquivo Municipal', 'url' => 'https://example.org/arquivo', 'note' => 'Referência para revisão editorial.']],
        ]))->assertRedirect('/admin/publications');

        $publication = Publication::where('slug', 'memoria-editorial-de-alegrete')->firstOrFail();
        $this->assertSame('Alegrete', $publication->origin_city);
        $this->assertSame('RS', $publication->origin_state);
        $this->assertSame('municipality', $publication->origin_scope);
        $this->assertSame(4300406, (int) $publication->municipality_code);
        $this->assertSame('Arquivo Municipal', $publication->source_references[0]['name']);
        $this->assertCount(2, $publication->categories);

        $this->get('/publicacao/'.$publication->slug)->assertOk()->assertInertia(fn ($page) => $page
            ->where('publication.municipality.name', 'Alegrete')
            ->where('publication.source_references.0.name', 'Arquivo Municipal')
            ->has('publication.categories', 2));
        $this->get('/cadernos/historias?assunto=migracao')->assertOk()->assertInertia(fn ($page) => $page
            ->has('publications.data', 1)->where('publications.data.0.title', 'Memória editorial de Alegrete'));

        $this->actingAs($editor)->post('/admin/publications', $this->draftData([
            'title' => 'Localidade inválida', 'origin_scope' => 'municipality', 'origin_state' => 'RS', 'municipality_code' => '9999999',
        ]))->assertSessionHasErrors('municipality_code');
    }
}

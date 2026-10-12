<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Media;
use App\Models\Publication;
use App\Models\PublicationRevision;
use App\Models\User;
use App\Support\MediaVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::Admin, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user->refresh();
    }

    private function story(User $author, string $slug, string $path): Publication
    {
        return Publication::create([
            'user_id' => $author->id,
            'title' => 'Memória '.$slug,
            'slug' => $slug,
            'body' => '<p>Uma lembrança da comunidade.</p>',
            'type' => 'historia',
            'cover_path' => $path,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    private function media(User $author, string $path): Media
    {
        return Media::create([
            'user_id' => $author->id,
            'path' => $path,
            'original_name' => 'fotografia.jpg',
            'alt_text' => 'Uma antiga estação',
            'credit' => 'Acervo familiar',
            'mime_type' => 'image/jpeg',
            'size' => 18,
        ]);
    }

    public function test_archiving_last_live_story_removes_public_url_and_keeps_private_editorial_copy(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = $this->admin();
        $media = $this->media($admin, 'media/community/station.jpg');
        Storage::disk('public')->put($media->path, 'authorized photograph');
        $publication = $this->story($admin, 'estacao-antiga', $media->path);
        $this->assertNotNull($publication->fresh()->published_at);

        $this->actingAs($admin)->post('/admin/publications/bulk', ['ids' => [$publication->id], 'action' => 'archive'])
            ->assertRedirect('/admin/publications?status=archived');

        Storage::disk('public')->assertMissing($media->path);
        $this->assertDatabaseHas('media', ['id' => $media->id, 'is_public' => false]);
        $media->refresh();
        $this->assertNotNull($media->private_path);
        Storage::disk('local')->assertExists($media->private_path);
        app(MediaVisibility::class)->syncPublication($publication->fresh());
        $this->assertCount(1, Storage::disk('local')->allFiles('media-retired'));
        $this->actingAs($admin)->get('/admin/media/'.$media->id.'/file')->assertOk();
        $this->assertSame('authorized photograph', Storage::disk('local')->get($media->private_path));

        $revision = PublicationRevision::query()->where('publication_id', $publication->id)->firstOrFail();
        $this->assertNotNull($revision->snapshot['published_at']);
        $this->post('/admin/publications/'.$publication->id.'/revisions/'.$revision->id.'/restore')->assertRedirect()->assertSessionHas('success');
        $this->assertSame('published', $publication->fresh()->status);
        $this->assertNotNull($publication->fresh()->published_at);
        $this->assertSame($media->path, $publication->fresh()->cover_path);
        $this->assertTrue($media->fresh()->is_public);
        Storage::disk('public')->assertExists($media->path);
        $this->assertDatabaseHas('media', ['id' => $media->id, 'is_public' => true]);
    }

    public function test_shared_photo_remains_public_until_its_last_live_story_is_archived(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = $this->admin();
        $media = $this->media($admin, 'media/community/shared.jpg');
        Storage::disk('public')->put($media->path, 'shared photograph');
        $first = $this->story($admin, 'memoria-primeira', $media->path);
        $second = $this->story($admin, 'memoria-segunda', $media->path);

        $this->actingAs($admin)->post('/admin/publications/bulk', ['ids' => [$first->id], 'action' => 'archive'])->assertRedirect();
        Storage::disk('public')->assertExists($media->path);
        $this->assertDatabaseHas('media', ['id' => $media->id, 'is_public' => true]);

        $this->post('/admin/publications/bulk', ['ids' => [$second->id], 'action' => 'archive'])->assertRedirect();
        Storage::disk('public')->assertMissing($media->path);
        Storage::disk('local')->assertExists($media->fresh()->private_path);
    }

    public function test_private_editorial_preview_reference_prevents_media_deletion(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = $this->admin();
        $media = $this->media($admin, 'media/community/draft-photo.jpg');
        Storage::disk('public')->put($media->path, 'editorial draft photo');
        Publication::create([
            'user_id' => $admin->id,
            'title' => 'Rascunho com fotografia',
            'slug' => 'rascunho-com-fotografia',
            'body' => '<figure><img src="http://localhost/admin/media/'.$media->id.'/file"></figure>',
            'type' => 'historia',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->get('/admin/media')->assertOk()->assertInertia(fn ($page) => $page
            ->where('media.data.0.used_in_count', 1));
        $this->delete('/admin/media/'.$media->id)->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    public function test_due_schedule_restores_media_without_publishing_drafts_or_future_schedules(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = $this->admin();
        $dueMedia = $this->media($admin, 'media/community/due.jpg');
        $futureMedia = $this->media($admin, 'media/community/future.jpg');
        $draftMedia = $this->media($admin, 'media/community/draft.jpg');
        $duePrivatePath = 'media-retired/due.jpg';
        $futurePrivatePath = 'media-retired/future.jpg';
        $draftPrivatePath = 'media-retired/draft.jpg';
        Storage::disk('local')->put($duePrivatePath, 'due photo');
        Storage::disk('local')->put($futurePrivatePath, 'future photo');
        Storage::disk('local')->put($draftPrivatePath, 'draft photo');
        $dueMedia->forceFill(['is_public' => false, 'private_path' => $duePrivatePath])->save();
        $futureMedia->forceFill(['is_public' => false, 'private_path' => $futurePrivatePath])->save();
        $draftMedia->forceFill(['is_public' => false, 'private_path' => $draftPrivatePath])->save();
        $due = Publication::create(['user_id' => $admin->id, 'title' => 'Agendada para agora', 'slug' => 'agendada-agora', 'body' => '<p>Texto</p>', 'type' => 'historia', 'cover_path' => $dueMedia->path, 'status' => 'scheduled', 'published_at' => now()->subMinute()]);
        Publication::create(['user_id' => $admin->id, 'title' => 'Agendada para depois', 'slug' => 'agendada-depois', 'body' => '<p>Texto</p>', 'type' => 'historia', 'cover_path' => $futureMedia->path, 'status' => 'scheduled', 'published_at' => now()->addDay()]);
        Publication::create(['user_id' => $admin->id, 'title' => 'Ainda em revisão', 'slug' => 'ainda-em-revisao', 'body' => '<p>Texto</p>', 'type' => 'historia', 'cover_path' => $draftMedia->path, 'status' => 'draft']);

        $this->artisan('media:sync-scheduled-visibility')->assertSuccessful();

        Storage::disk('public')->assertExists($dueMedia->path);
        $this->assertTrue($dueMedia->fresh()->is_public);
        $this->assertNotNull($due->fresh()->media_visibility_synced_at);
        Storage::disk('public')->assertMissing($futureMedia->path);
        Storage::disk('public')->assertMissing($draftMedia->path);
        $this->assertSame('scheduled', $due->fresh()->status);
        $this->assertSame('draft', Publication::query()->where('slug', 'ainda-em-revisao')->value('status'));
        $this->artisan('media:sync-scheduled-visibility')->expectsOutputToContain('0 publicação(ões) agendada(s)');
    }

    public function test_public_home_reads_do_not_move_or_reconcile_media(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $author = User::factory()->create();
        $media = $this->media($author, 'media/community/read-only.jpg');
        Storage::disk('public')->put($media->path, 'public photo');
        Publication::create(['user_id' => $author->id, 'title' => 'História pública', 'slug' => 'historia-publica', 'body' => '<p>Texto</p>', 'type' => 'historia', 'cover_path' => $media->path, 'status' => 'published', 'published_at' => now()]);
        $pathLookups = 0;
        DB::listen(function ($query) use (&$pathLookups): void {
            if (str_contains(strtolower($query->sql), 'from "media" where "path" =')) {
                $pathLookups++;
            }
        });

        $this->get('/')->assertOk();

        Storage::disk('public')->assertExists($media->path);
        $this->assertTrue($media->fresh()->is_public);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, $pathLookups);
    }
}

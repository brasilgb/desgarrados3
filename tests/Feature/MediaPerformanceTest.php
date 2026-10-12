<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Media;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_library_reference_query_count_is_constant_for_a_page_of_files(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::Admin, 'is_active' => true, 'email_verified_at' => now()])->save();
        $mediaItems = collect(range(1, 12))->map(function (int $number) use ($admin): Media {
            $media = Media::create([
                'user_id' => $admin->id,
                'path' => "media/perf-{$number}.jpg",
                'original_name' => "perf-{$number}.jpg",
                'mime_type' => 'image/jpeg',
                'size' => 10,
            ]);
            Publication::create([
                'user_id' => $admin->id,
                'title' => "Publicação {$number}",
                'slug' => "publicacao-{$number}",
                'body' => '<p>Texto</p>',
                'type' => 'historia',
                'cover_path' => $media->path,
                'status' => 'draft',
            ]);

            return $media;
        });

        $publicationQueryCount = 0;
        DB::listen(function ($query) use (&$publicationQueryCount): void {
            if (str_contains(strtolower($query->sql), 'publications')) {
                $publicationQueryCount++;
            }
        });

        foreach ($mediaItems as $media) {
            Publication::query()->where('cover_path', $media->path)
                ->orWhere('body', 'like', '%'.$media->path.'%')
                ->orWhere('body', 'like', '%/admin/media/'.$media->id.'/file%')
                ->count();
        }
        $before = $publicationQueryCount;

        $publicationQueryCount = 0;
        $this->actingAs($admin)->get('/admin/media')->assertOk();
        $after = $publicationQueryCount;

        fwrite(STDERR, "Media reference queries (12 files): {$before} -> {$after}\n");
        $this->assertSame(12, $before);
        $this->assertSame(1, $after);
    }
}

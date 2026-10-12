<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PortalSetting;
use App\Models\Saying;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalOptionalAreasTest extends TestCase
{
    use RefreshDatabase;

    public function test_sayings_show_on_the_home_until_an_administrator_turns_them_off(): void
    {
        Saying::create(['phrase' => 'De grão em grão, a galinha enche o papo.', 'meaning' => 'Paciência.', 'status' => 'published']);
        Saying::create(['phrase' => 'Rascunho que não aparece.', 'meaning' => 'Rascunho.', 'status' => 'draft']);

        // No saved settings yet: same default as the admin form and the seeder.
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('saying.phrase', 'De grão em grão, a galinha enche o papo.'));

        PortalSetting::create(['key' => 'show_sayings', 'value' => '0']);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('saying', null));

        $this->get('/ditados')->assertOk()->assertInertia(fn (Assert $page) => $page->has('sayings', 1));
    }

    public function test_collections_and_categories_also_default_to_visible(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('collections')->has('categories'));

        PortalSetting::create(['key' => 'show_categories', 'value' => '0']);
        Category::create(['name' => 'Campo', 'slug' => 'campo']);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('categories', []));
    }

    public function test_sayings_page_is_reachable_from_the_main_menu(): void
    {
        $this->assertStringContainsString("{ href: '/ditados', label: 'Ditados e expressões', icon: Quote }", File::get(resource_path('js/lib/public-navigation.ts')));
        $this->assertStringContainsString("currentUrl === '/ditados'", File::get(resource_path('js/layouts/public-layout.tsx')));
    }
}

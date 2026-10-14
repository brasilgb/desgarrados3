<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Controllers\RegionalHubController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RegionalHubTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user->refresh();
    }

    public function test_central_campeira_is_public_and_exposes_deterministic_tools_without_a_login(): void
    {
        $this->get('/nossa-terra')->assertOk()->assertInertia(fn ($page) => $page
            ->component('portal/nossa-terra')
            ->has('enabledTools', 17)
            ->where('featuredTools', ['area', 'arroba', 'fuel']));
    }

    public function test_administrator_can_configure_tool_availability_titles_highlights_and_order(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $ids = ['area', 'seed', 'arroba', 'wool', 'stocking', 'grapes', 'poultry', 'swine', 'milk', 'feed', 'orchard', 'honey', 'weight', 'volume', 'fuel', 'travel', 'moving'];
        $this->actingAs($admin)->get('/admin/campeira')->assertOk()->assertInertia(fn ($page) => $page->component('admin/regional-tools')->has('tools', 17));
        $this->actingAs($admin)->put('/admin/campeira', [
            'enabled' => ['area', 'travel'], 'featured' => ['travel'], 'titles' => ['travel' => 'Viagem da família'], 'order' => array_reverse($ids),
        ])->assertRedirect();
        $this->assertDatabaseHas('portal_settings', ['key' => 'regional_tools_enabled', 'value' => '["area","travel"]']);
        $this->assertDatabaseHas('portal_settings', ['key' => 'regional_tools_titles', 'value' => '{"travel":"Viagem da família"}']);
        $this->get('/nossa-terra')->assertOk()->assertInertia(fn ($page) => $page->where('enabledTools', ['area', 'travel'])->where('featuredTools', ['travel'])->where('toolTitles.travel', 'Viagem da família')->where('toolOrder', array_reverse($ids)));
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('portal/home')->where('featuredTools', [['id' => 'travel', 'title' => 'Viagem da família']]));
    }

    public function test_home_highlights_featured_tools_and_skips_disabled_ones(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('portal/home')
            ->where('featuredTools', [['id' => 'area', 'title' => null], ['id' => 'arroba', 'title' => null], ['id' => 'fuel', 'title' => null]]));

        $admin = $this->userWithRole(UserRole::Admin);
        $ids = ['area', 'seed', 'arroba', 'wool', 'stocking', 'grapes', 'poultry', 'swine', 'milk', 'feed', 'orchard', 'honey', 'weight', 'volume', 'fuel', 'travel', 'moving'];
        $this->actingAs($admin)->put('/admin/campeira', [
            'enabled' => ['fuel', 'arroba'], 'featured' => ['area', 'arroba', 'fuel'], 'titles' => [], 'order' => array_reverse($ids),
        ])->assertRedirect();
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->where('featuredTools', [['id' => 'fuel', 'title' => null], ['id' => 'arroba', 'title' => null]]));
    }

    public function test_editors_cannot_change_regional_tool_settings_and_invalid_tool_ids_are_rejected(): void
    {
        $editor = $this->userWithRole(UserRole::Editor);
        $ids = ['area', 'seed', 'arroba', 'wool', 'stocking', 'grapes', 'poultry', 'swine', 'milk', 'feed', 'orchard', 'honey', 'weight', 'volume', 'fuel', 'travel', 'moving'];
        $this->actingAs($editor)->get('/admin/campeira')->assertForbidden();
        $this->actingAs($editor)->put('/admin/campeira', ['enabled' => [], 'featured' => [], 'titles' => [], 'order' => $ids])->assertForbidden();

        $admin = $this->userWithRole(UserRole::Admin);
        $this->actingAs($admin)->put('/admin/campeira', ['enabled' => ['unknown'], 'featured' => [], 'titles' => [], 'order' => $ids])->assertSessionHasErrors('enabled.0');
        $this->assertDatabaseMissing('portal_settings', ['key' => 'regional_tools_enabled']);
    }

    public function test_each_tool_has_an_indexable_page_listed_in_the_sitemap(): void
    {
        $this->get('/nossa-terra/ferramentas/peso-bovino-e-arrobas')->assertOk()->assertHeaderMissing('X-Robots-Tag')
            ->assertInertia(fn ($page) => $page->component('portal/regional-tool')->where('toolId', 'arroba')->has('enabledTools', 17));
        $this->get('/nossa-terra/ferramentas/nao-existe')->assertNotFound();

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        foreach (RegionalHubController::TOOL_SLUGS as $slug) {
            $this->assertStringContainsString("/nossa-terra/ferramentas/{$slug}</loc>", $sitemap);
        }
    }

    public function test_disabled_tools_have_no_page_and_leave_the_sitemap(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $this->actingAs($admin)->put('/admin/campeira', [
            'enabled' => ['area'], 'featured' => [], 'titles' => ['area' => 'Medidas de terra'], 'order' => array_keys(RegionalHubController::TOOL_SLUGS),
        ])->assertRedirect();
        auth()->logout();

        $this->get('/nossa-terra/ferramentas/area-rural')->assertOk()->assertInertia(fn ($page) => $page->where('toolTitles.area', 'Medidas de terra'));
        $this->get('/nossa-terra/ferramentas/custo-de-viagem')->assertNotFound();
        $sitemap = $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString('/nossa-terra/ferramentas/area-rural</loc>', $sitemap);
        $this->assertStringNotContainsString('/nossa-terra/ferramentas/custo-de-viagem', $sitemap);

        // Old shared links move to the tool page; a disabled tool falls back to the hub, kept out of the index.
        $this->get('/nossa-terra?ferramenta=area')->assertRedirect('/nossa-terra/ferramentas/area-rural')->assertStatus(301);
        $this->get('/nossa-terra?ferramenta=travel')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
    }

    public function test_tool_slugs_match_the_frontend_catalog(): void
    {
        $catalog = File::get(resource_path('js/lib/regional-tools-catalog.ts'));
        preg_match_all("/^    (\w+): \{ id: '(\w+)', slug: '([a-z0-9-]+)'/m", $catalog, $matches, PREG_SET_ORDER);

        $this->assertSame(RegionalHubController::TOOL_SLUGS, collect($matches)->mapWithKeys(fn (array $match) => [$match[2] => $match[3]])->all());
    }
}

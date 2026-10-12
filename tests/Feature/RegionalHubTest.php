<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->get('/nossa-terra')->assertOk()->assertInertia(fn ($page) => $page->where('enabledTools', ['area', 'travel'])->where('featuredTools', ['travel'])->where('toolTitles.travel', 'Viagem da família'));
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
}

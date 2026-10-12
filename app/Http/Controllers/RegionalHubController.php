<?php

namespace App\Http\Controllers;

use App\Models\PortalSetting;
use Inertia\Inertia;
use Inertia\Response;

class RegionalHubController extends Controller
{
    private const TOOL_IDS = ['area', 'seed', 'arroba', 'wool', 'stocking', 'grapes', 'poultry', 'swine', 'milk', 'feed', 'orchard', 'honey', 'weight', 'volume', 'fuel', 'travel', 'moving'];

    public function __invoke(): Response
    {
        $settings = PortalSetting::query()->whereIn('key', ['regional_tools_featured', 'regional_tools_order'])->pluck('value', 'key');
        $featured = $settings->has('regional_tools_featured') ? json_decode($settings->get('regional_tools_featured'), true) : ['area', 'arroba', 'fuel'];
        $order = $settings->has('regional_tools_order') ? json_decode($settings->get('regional_tools_order'), true) : self::TOOL_IDS;
        $public = self::publicTools();

        return Inertia::render('portal/nossa-terra', [
            'enabledTools' => $public['enabled'],
            'featuredTools' => is_array($featured) ? array_values(array_intersect(self::TOOL_IDS, $featured)) : ['area', 'arroba', 'fuel'],
            'toolTitles' => $public['titles'],
            'toolOrder' => is_array($order) ? array_values(array_intersect(self::TOOL_IDS, $order)) : self::TOOL_IDS,
        ]);
    }

    /**
     * Tools visitors can open, with the titles customized in the admin panel.
     *
     * @return array{enabled: list<string>, titles: array<string, string>}
     */
    public static function publicTools(): array
    {
        $settings = PortalSetting::query()->whereIn('key', ['regional_tools_enabled', 'regional_tools_titles'])->pluck('value', 'key');
        $enabled = $settings->has('regional_tools_enabled') ? json_decode($settings->get('regional_tools_enabled'), true) : self::TOOL_IDS;
        $titles = json_decode($settings->get('regional_tools_titles', '{}'), true);

        return [
            'enabled' => is_array($enabled) ? array_values(array_intersect(self::TOOL_IDS, $enabled)) : self::TOOL_IDS,
            'titles' => is_array($titles) ? array_intersect_key($titles, array_flip(self::TOOL_IDS)) : [],
        ];
    }
}

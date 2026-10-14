<?php

namespace App\Http\Controllers;

use App\Models\PortalSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegionalHubController extends Controller
{
    private const TOOL_IDS = ['area', 'seed', 'arroba', 'wool', 'stocking', 'grapes', 'poultry', 'swine', 'milk', 'feed', 'orchard', 'honey', 'weight', 'volume', 'fuel', 'travel', 'moving'];

    /** Public address of each tool page; must match `slug` in resources/js/lib/regional-tools-catalog.ts. */
    public const TOOL_SLUGS = [
        'area' => 'area-rural', 'seed' => 'sementes-por-area', 'arroba' => 'peso-bovino-e-arrobas', 'wool' => 'producao-de-la',
        'stocking' => 'lotacao-por-area', 'grapes' => 'producao-de-uvas', 'poultry' => 'indicadores-de-aves', 'swine' => 'indicadores-de-suinos',
        'milk' => 'producao-leiteira', 'feed' => 'consumo-de-racao', 'orchard' => 'producao-de-maca-e-pessego', 'honey' => 'producao-de-mel',
        'weight' => 'conversor-de-peso', 'volume' => 'conversor-de-volume', 'fuel' => 'custo-de-combustivel', 'travel' => 'custo-de-viagem',
        'moving' => 'estimativa-de-mudanca',
    ];

    public function __invoke(Request $request): Response|RedirectResponse
    {
        // Old shared links (?ferramenta=id) move permanently to the tool's own page.
        $requested = $request->query('ferramenta');
        if (is_string($requested) && in_array($requested, self::publicTools()['enabled'], true)) {
            return redirect(self::toolPath($requested), 301);
        }

        $settings = PortalSetting::query()->whereIn('key', ['regional_tools_featured', 'regional_tools_order'])->pluck('value', 'key');
        $featured = $settings->has('regional_tools_featured') ? json_decode($settings->get('regional_tools_featured'), true) : ['area', 'arroba', 'fuel'];
        $order = $settings->has('regional_tools_order') ? json_decode($settings->get('regional_tools_order'), true) : self::TOOL_IDS;
        $public = self::publicTools();

        return Inertia::render('portal/nossa-terra', [
            'enabledTools' => $public['enabled'],
            'featuredTools' => is_array($featured) ? array_values(array_intersect(self::TOOL_IDS, $featured)) : ['area', 'arroba', 'fuel'],
            'toolTitles' => $public['titles'],
            'toolOrder' => self::adminOrder($order),
        ]);
    }

    /** Indexable page of one tool; disabled tools are not public. */
    public function tool(string $slug): Response
    {
        $id = array_search($slug, self::TOOL_SLUGS, true);
        $public = self::publicTools();
        abort_unless(is_string($id) && in_array($id, $public['enabled'], true), 404);

        return Inertia::render('portal/regional-tool', [
            'toolId' => $id,
            'enabledTools' => $public['enabled'],
            'toolTitles' => $public['titles'],
        ]);
    }

    public static function toolPath(string $id): string
    {
        return '/nossa-terra/ferramentas/'.self::TOOL_SLUGS[$id];
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

    /**
     * Featured tools open to visitors, in the admin order, for the home page shortcuts.
     *
     * @return list<array{id: string, title: string|null}>
     */
    public static function featuredPublicTools(): array
    {
        $settings = PortalSetting::query()->whereIn('key', ['regional_tools_featured', 'regional_tools_order'])->pluck('value', 'key');
        $featured = $settings->has('regional_tools_featured') ? json_decode($settings->get('regional_tools_featured'), true) : ['area', 'arroba', 'fuel'];
        $order = $settings->has('regional_tools_order') ? json_decode($settings->get('regional_tools_order'), true) : self::TOOL_IDS;
        $public = self::publicTools();
        $ids = array_intersect(self::adminOrder($order), is_array($featured) ? $featured : [], $public['enabled']);

        return array_values(array_map(fn (string $id) => ['id' => $id, 'title' => $public['titles'][$id] ?? null], $ids));
    }

    /**
     * Every tool id in the order saved by the admin; unknown ids are dropped and tools missing from it go last.
     *
     * @return list<string>
     */
    private static function adminOrder(mixed $order): array
    {
        return is_array($order) ? array_values(array_unique([...array_intersect($order, self::TOOL_IDS), ...self::TOOL_IDS])) : self::TOOL_IDS;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortalSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RegionalToolSettingsController extends Controller
{
    private const TOOLS = [
        ['id' => 'area', 'title' => 'Área rural', 'category' => 'Agricultura'],
        ['id' => 'seed', 'title' => 'Sementes por área', 'category' => 'Agricultura'],
        ['id' => 'arroba', 'title' => 'Peso bovino e arrobas', 'category' => 'Pecuária'],
        ['id' => 'wool', 'title' => 'Lã ovina', 'category' => 'Pecuária'],
        ['id' => 'stocking', 'title' => 'Lotação por área', 'category' => 'Pecuária'],
        ['id' => 'grapes', 'title' => 'Produção de uvas', 'category' => 'Produção colonial'],
        ['id' => 'poultry', 'title' => 'Indicadores de aves', 'category' => 'Produção colonial'],
        ['id' => 'swine', 'title' => 'Indicadores de suínos', 'category' => 'Produção colonial'],
        ['id' => 'milk', 'title' => 'Produção leiteira', 'category' => 'Pecuária'],
        ['id' => 'feed', 'title' => 'Consumo de ração', 'category' => 'Produção colonial'],
        ['id' => 'orchard', 'title' => 'Maçã e pêssego', 'category' => 'Produção colonial'],
        ['id' => 'honey', 'title' => 'Produção de mel', 'category' => 'Produção colonial'],
        ['id' => 'weight', 'title' => 'Peso', 'category' => 'Utilidades'],
        ['id' => 'volume', 'title' => 'Volume', 'category' => 'Utilidades'],
        ['id' => 'fuel', 'title' => 'Combustível', 'category' => 'Utilidades'],
        ['id' => 'travel', 'title' => 'Custo de viagem', 'category' => 'Utilidades'],
        ['id' => 'moving', 'title' => 'Estimativa de mudança', 'category' => 'Utilidades'],
    ];

    public function edit(): Response
    {
        $settings = PortalSetting::query()->whereIn('key', ['regional_tools_enabled', 'regional_tools_featured', 'regional_tools_titles', 'regional_tools_order'])->pluck('value', 'key');
        $decode = static fn (string $key, mixed $fallback): mixed => json_decode($settings->get($key, json_encode($fallback)), true) ?? $fallback;

        return Inertia::render('admin/regional-tools', [
            'tools' => self::TOOLS,
            'enabled' => $decode('regional_tools_enabled', array_column(self::TOOLS, 'id')),
            'featured' => $decode('regional_tools_featured', ['area', 'arroba', 'fuel']),
            'titles' => $decode('regional_tools_titles', []),
            'order' => $decode('regional_tools_order', array_column(self::TOOLS, 'id')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $ids = array_column(self::TOOLS, 'id');
        $data = $request->validate([
            'enabled' => ['present', 'array'],
            'enabled.*' => ['required', 'string', 'distinct', Rule::in($ids)],
            'featured' => ['present', 'array'],
            'featured.*' => ['required', 'string', 'distinct', Rule::in($ids)],
            'order' => ['required', 'array', 'size:'.count($ids)],
            'order.*' => ['required', 'string', 'distinct', Rule::in($ids)],
            'titles' => ['nullable', 'array'],
            'titles.*' => ['nullable', 'string', 'max:80'],
        ]);
        $titles = array_filter(array_intersect_key($data['titles'] ?? [], array_flip($ids)), static fn ($title): bool => filled($title));

        DB::transaction(function () use ($data, $titles): void {
            foreach (['enabled', 'featured', 'order'] as $key) {
                PortalSetting::updateOrCreate(['key' => "regional_tools_{$key}"], ['value' => json_encode($data[$key], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            }
            PortalSetting::updateOrCreate(['key' => 'regional_tools_titles'], ['value' => json_encode($titles, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        });

        return back()->with('success', 'Central Campeira atualizada.');
    }
}

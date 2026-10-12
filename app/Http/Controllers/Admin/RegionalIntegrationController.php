<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RegionalIntegration;
use App\Services\RegionalIntegrations\IntegrationCatalog;
use App\Services\RegionalIntegrations\RegionalIntegrationSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class RegionalIntegrationController extends Controller
{
    public function index(IntegrationCatalog $catalog): Response
    {
        $catalog->ensure();
        RegionalIntegration::query()->whereNull('next_sync_at')->each(function (RegionalIntegration $integration): void {
            $next = match ($integration->key) {
                'ibge_locations' => now()->addMonth()->startOfMonth()->setTime(2, 0),
                'brasilapi_holidays' => now()->addYear()->startOfYear()->setTime(2, 30),
                'mapa_zarc' => now()->addWeek()->startOfWeek()->setTime(3, 0),
                default => null,
            };
            if ($next) {
                $integration->forceFill(['next_sync_at' => $next])->save();
            }
        });

        return Inertia::render('admin/integrations/index', ['integrations' => RegionalIntegration::query()->orderBy('name')->get()]);
    }

    public function update(Request $request, RegionalIntegration $integration): RedirectResponse
    {
        abort_unless($integration->availability === 'available', 404);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $integration->update(['enabled' => (bool) $data['enabled']]);

        return back()->with('success', 'Preferência de sincronização atualizada.');
    }

    public function sync(RegionalIntegration $integration, RegionalIntegrationSync $sync): RedirectResponse
    {
        try {
            $result = $sync->run($integration->key);

            return back()->with('success', "Sincronização concluída: {$result['count']} {$result['label']}.");
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'Falha ao sincronizar a fonte. Os dados válidos anteriores foram preservados.';

            return back()->withErrors(['integration' => $message]);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\RegionalIntegration;
use App\Services\RegionalIntegrations\IntegrationCatalog;
use App\Services\RegionalIntegrations\RegionalIntegrationSync;
use Illuminate\Console\Command;
use Throwable;

class SyncRegionalIntegrations extends Command
{
    protected $signature = 'campeira:sync {source? : Chave da fonte; omita para sincronizar todas as fontes ativas}';

    protected $description = 'Sincroniza localidades, feriados e dados CSV oficiais do ZARC';

    public function handle(IntegrationCatalog $catalog, RegionalIntegrationSync $sync): int
    {
        $catalog->ensure();
        $key = $this->argument('source');
        $sources = $key
            ? RegionalIntegration::query()->whereKey($key)->get()
            : RegionalIntegration::query()->where('availability', 'available')->where('enabled', true)->get();
        if ($sources->isEmpty()) {
            $this->error('Nenhuma fonte disponível corresponde à solicitação.');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($sources as $source) {
            try {
                $result = $sync->run($source->key);
                $this->info("{$source->name}: {$result['count']} {$result['label']} sincronizados.");
            } catch (Throwable $exception) {
                $failed = true;
                $this->error("{$source->name}: {$exception->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

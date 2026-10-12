<?php

namespace App\Console\Commands;

use App\Models\Publication;
use App\Support\MediaVisibility;
use Illuminate\Console\Command;

class SyncScheduledMediaVisibility extends Command
{
    protected $signature = 'media:sync-scheduled-visibility';

    protected $description = 'Disponibiliza as imagens de publicações agendadas que chegaram à data de publicação.';

    public function handle(MediaVisibility $visibility): int
    {
        $count = 0;
        $failures = 0;
        Publication::query()
            ->where('status', 'scheduled')
            ->where('published_at', '<=', now()->toDateTimeString())
            ->whereNull('media_visibility_synced_at')
            ->chunkById(50, function ($publications) use ($visibility, &$count, &$failures): void {
                foreach ($publications as $publication) {
                    if ($visibility->syncPublication($publication)) {
                        $count++;
                    } else {
                        $failures++;
                    }
                }
            });

        $this->info("Visibilidade sincronizada para {$count} publicação(ões) agendada(s).");
        if ($failures > 0) {
            $this->error("Não foi possível sincronizar {$failures} publicação(ões); serão tentadas novamente.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Services\RegionalIntegrations;

use App\Models\RegionalHoliday;
use App\Models\RegionalIntegration;
use App\Models\TerritorialUnit;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RegionalIntegrationSync
{
    private const SOUTH_STATES = ['PR', 'RS', 'SC'];

    public function __construct(private readonly IntegrationCatalog $catalog) {}

    /** @return array{count: int, label: string} */
    public function run(string $key): array
    {
        $this->catalog->ensure();
        $source = RegionalIntegration::query()->findOrFail($key);
        if (! $source->enabled || $source->availability !== 'available') {
            throw new RuntimeException('Esta fonte está desativada ou não está disponível para sincronização automática.');
        }

        $lock = Cache::lock("regional-integration:{$key}", 600);
        if (! $lock->get()) {
            throw new RuntimeException('Já existe uma sincronização desta fonte em andamento.');
        }

        $source->forceFill(['last_attempted_at' => now(), 'last_error' => null])->save();

        try {
            $result = match ($key) {
                'ibge_locations' => ['count' => $this->syncIbgeLocations(), 'label' => 'localidades'],
                'brasilapi_holidays' => ['count' => $this->syncHolidays(), 'label' => 'feriados'],
                default => throw new RuntimeException('Provedor de integração não implementado.'),
            };
            $source->forceFill(['records_count' => $result['count'], 'last_synced_at' => now(), 'last_error' => null, 'next_sync_at' => $this->nextSync($key)])->save();

            return $result;
        } catch (Throwable $exception) {
            $source->forceFill(['last_error' => Str::limit($exception->getMessage(), 2000)])->save();
            Log::warning('Falha ao sincronizar integração regional.', ['source' => $key, 'message' => $exception->getMessage()]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->timeout(45)->retry(2, 500);
    }

    private function syncIbgeLocations(): int
    {
        $base = 'https://servicodados.ibge.gov.br/api/v1/localidades';
        $statesResponse = $this->http()->get("{$base}/estados", ['orderBy' => 'nome'])->throw()->json();
        $municipalitiesResponse = $this->http()->get("{$base}/municipios", ['orderBy' => 'nome'])->throw()->json();
        if (! is_array($statesResponse) || count($statesResponse) !== 27 || ! is_array($municipalitiesResponse) || count($municipalitiesResponse) < 5000) {
            throw new RuntimeException('A resposta do IBGE não passou pela validação de estados e municípios.');
        }

        $statesById = [];
        $rows = [];
        foreach ($statesResponse as $state) {
            if (! is_array($state) || ! isset($state['id'], $state['sigla'], $state['nome']) || ! preg_match('/^[A-Z]{2}$/', $state['sigla'])) {
                throw new RuntimeException('Formato inesperado na lista de estados do IBGE.');
            }
            $statesById[(string) $state['id']] = $state;
            $rows[] = ['ibge_code' => $state['id'], 'unit_type' => 'state', 'name' => $state['nome'], 'state_code' => $state['sigla'], 'state_name' => $state['nome'], 'region_name' => data_get($state, 'regiao.nome'), 'created_at' => now(), 'updated_at' => now()];
        }

        foreach ($municipalitiesResponse as $city) {
            if (! is_array($city) || ! isset($city['id'], $city['nome'])) {
                throw new RuntimeException('Formato inesperado na lista de municípios do IBGE.');
            }
            $state = data_get($city, 'microrregiao.mesorregiao.UF') ?? data_get($city, 'regiao-imediata.regiao-intermediaria.UF');
            $stateId = (string) data_get($state, 'id');
            $stateData = $statesById[$stateId] ?? null;
            if (! $stateData) {
                throw new RuntimeException('Município sem vínculo de estado reconhecido pelo IBGE.');
            }
            $rows[] = ['ibge_code' => $city['id'], 'unit_type' => 'municipality', 'name' => $city['nome'], 'state_code' => $stateData['sigla'], 'state_name' => $stateData['nome'], 'region_name' => data_get($stateData, 'regiao.nome'), 'created_at' => now(), 'updated_at' => now()];
        }

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                TerritorialUnit::query()->upsert($chunk, ['ibge_code'], ['unit_type', 'name', 'state_code', 'state_name', 'region_name', 'updated_at']);
            }
        });

        return count($rows);
    }

    private function syncHolidays(): int
    {
        $year = (int) now()->format('Y');
        $nextYear = $year + 1;
        $sourceUrl = 'https://brasilapi.com.br/api/feriados/v1';
        $rows = [];
        foreach ([$year, $nextYear] as $targetYear) {
            foreach (self::SOUTH_STATES as $state) {
                $response = $this->http()->get("{$sourceUrl}/{$targetYear}", ['uf' => $state])->throw()->json();
                if (! is_array($response) || count($response) < 8 || count($response) > 80) {
                    throw new RuntimeException("A resposta de feriados para {$state}/{$targetYear} não passou pela validação.");
                }
                foreach ($response as $holiday) {
                    if (! is_array($holiday) || ! isset($holiday['date'], $holiday['name'], $holiday['type']) || ! is_string($holiday['date']) || ! is_string($holiday['name'])) {
                        throw new RuntimeException('Formato inesperado na resposta de feriados da BrasilAPI.');
                    }
                    $date = date_create_from_format('!Y-m-d', $holiday['date']);
                    if (! $date || (int) $date->format('Y') !== $targetYear) {
                        throw new RuntimeException('Data inválida na resposta de feriados da BrasilAPI.');
                    }
                    $providerType = strtolower($holiday['type']);
                    if (! in_array($providerType, ['national', 'state'], true)) {
                        throw new RuntimeException('Tipo de feriado desconhecido na BrasilAPI; dados anteriores foram preservados.');
                    }
                    $scope = $providerType;
                    $optional = array_key_exists('optional', $holiday) ? (bool) $holiday['optional'] : null;
                    $eventKey = hash('sha256', implode('|', [$holiday['date'], Str::lower($holiday['name']), $scope, $scope === 'state' ? $state : 'BR']));
                    $rows[] = ['provider' => 'brasilapi', 'provider_event_key' => $eventKey, 'name' => $holiday['name'], 'date' => $holiday['date'], 'scope' => $scope, 'state_code' => $scope === 'state' ? $state : null, 'optional' => $optional, 'reference_year' => $targetYear, 'source_url' => "{$sourceUrl}/{$targetYear}?uf={$state}", 'last_synced_at' => now(), 'created_at' => now(), 'updated_at' => now()];
                }
            }
        }

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                RegionalHoliday::query()->upsert($chunk, ['provider_event_key'], ['name', 'date', 'scope', 'state_code', 'optional', 'reference_year', 'source_url', 'last_synced_at', 'updated_at']);
            }
        });

        return RegionalHoliday::query()->where('provider', 'brasilapi')->count();
    }

    private function nextSync(string $key): mixed
    {
        return match ($key) {
            'ibge_locations' => now()->addMonth()->startOfMonth()->setTime(2, 0),
            'brasilapi_holidays' => now()->addYear()->startOfYear()->setTime(2, 30),
            default => null,
        };
    }
}

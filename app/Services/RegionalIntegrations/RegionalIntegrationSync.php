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
                'mapa_zarc' => ['count' => $this->syncZarc(), 'label' => 'registros ZARC'],
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

    private function syncZarc(): int
    {
        $catalogUrl = 'https://dados.agricultura.gov.br/api/3/action/package_show?id=6d3d141c-885e-41a4-ab7f-dc8ff323b96f';
        $catalog = Http::acceptJson()->timeout(45)->retry(2, 500)->get($catalogUrl)->throw()->json();
        $resources = data_get($catalog, 'result.resources');
        if (data_get($catalog, 'success') !== true || ! is_array($resources)) {
            throw new RuntimeException('O catálogo CKAN do MAPA não retornou recursos válidos.');
        }

        $seasonal = collect($resources)->filter(fn ($resource) => is_array($resource) && strtolower((string) ($resource['format'] ?? '')) === 'csv' && preg_match('/Safra\s+(\d{4})\/(\d{4})/iu', (string) ($resource['name'] ?? ''), $match) === 1)
            ->sortByDesc(fn ($resource) => (int) (preg_match('/Safra\s+(\d{4})\/(\d{4})/iu', (string) $resource['name'], $match) ? $match[1] : 0))->first();
        $selected = array_filter([$seasonal]);
        if ($selected === []) {
            throw new RuntimeException('Não foi encontrada tábua CSV anual ou perene no catálogo ZARC.');
        }

        $sourceMetadataJson = DB::table('regional_integrations')->where('key', 'mapa_zarc')->value('metadata');
        $sourceMetadata = is_string($sourceMetadataJson) ? json_decode($sourceMetadataJson, true) : [];
        $sourceMetadata = is_array($sourceMetadata) ? $sourceMetadata : [];
        $previousResources = $sourceMetadata['resources'] ?? [];
        $previousResources = is_array($previousResources) ? $previousResources : [];
        $importedResources = [];
        foreach ($selected as $resource) {
            $version = $resource['metadata_modified'] ?? $resource['last_modified'] ?? null;
            $previous = collect($previousResources)->firstWhere('resource_id', (string) ($resource['id'] ?? ''));
            if ($version !== null && ($previous['version'] ?? null) === $version) {
                continue;
            }
            $url = (string) ($resource['url'] ?? '');
            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'dados.agricultura.gov.br') {
                throw new RuntimeException('O catálogo ZARC apontou para um endereço externo não permitido.');
            }
            $declaredSize = (int) ($resource['size'] ?? 0);
            if ($declaredSize > 1_000_000_000) {
                throw new RuntimeException('O CSV ZARC excede 1 GB e foi preservado para avaliação operacional.');
            }
            $path = tempnam(sys_get_temp_dir(), 'desgarrados-zarc-');
            if ($path === false) {
                throw new RuntimeException('Não foi possível criar arquivo temporário para o CSV ZARC.');
            }
            try {
                $response = Http::timeout(300)->retry(2, 1000)->withOptions(['sink' => $path])->get($url)->throw();
                $fileSize = filesize($path);
                if ($fileSize === 0) {
                    file_put_contents($path, $response->body());
                    $fileSize = filesize($path);
                }
                if ($fileSize === false || $fileSize < 100 || $fileSize > 1_000_000_000) {
                    throw new RuntimeException('O CSV ZARC está vazio ou excede 1 GB; dados importados anteriormente foram preservados.');
                }
                $this->importZarcCsv((string) ($resource['name'] ?? 'Tábua ZARC'), $path);
            } finally {
                @unlink($path);
            }
            $importedResources[] = ['name' => (string) ($resource['name'] ?? 'Tábua ZARC'), 'resource_id' => (string) ($resource['id'] ?? ''), 'version' => $version];
        }

        if ($importedResources !== []) {
            $existingResources = collect($previousResources)->keyBy('resource_id');
            foreach ($importedResources as $resource) {
                $existingResources->put($resource['resource_id'], $resource);
            }
            RegionalIntegration::query()->whereKey('mapa_zarc')->update(['metadata' => json_encode(['resources' => $existingResources->values()->all()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        }

        return (int) DB::table('zarc_records')->count();
    }

    private function importZarcCsv(string $datasetKey, string $path): int
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo temporário do CSV ZARC.');
        }
        $firstLine = (string) fgets($stream);
        $delimiter = (string) collect([',', ';', "\t"])->sortByDesc(fn ($candidate) => substr_count($firstLine, $candidate))->first();
        $this->csvDelimiter = $delimiter;
        rewind($stream);
        $headers = fgetcsv($stream, 0, $delimiter);
        if (! is_array($headers)) {
            fclose($stream);
            throw new RuntimeException('CSV ZARC sem cabeçalho válido.');
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]) ?? (string) $headers[0];
        $normalized = array_map(fn ($header) => preg_replace('/[^a-z0-9]/', '', Str::lower((string) $header)) ?? '', $headers);
        $indexes = array_flip($normalized);
        $required = ['nomecultura', 'safraini', 'safrafin', 'geocodigo', 'uf', 'municipio'];
        $decennials = array_map(fn (int $number) => 'dec'.$number, range(1, 36));
        if (array_diff($required, array_keys($indexes)) !== [] || array_diff($decennials, array_keys($indexes)) !== []) {
            fclose($stream);
            throw new RuntimeException('O formato do CSV ZARC mudou; dados anteriores foram preservados.');
        }

        $datasetKey = Str::limit($datasetKey, 80, '');
        $count = 0;
        $skipped = 0;
        try {
            DB::transaction(function () use ($stream, $headers, $indexes, $datasetKey, &$count, &$skipped): void {
                DB::table('zarc_records')->where('dataset_key', $datasetKey)->delete();
                $batch = [];
                $lineNumber = 1;
                while (($values = fgetcsv($stream, 0, $this->csvDelimiter)) !== false) {
                    $lineNumber++;
                    if ($values === [null] || count($values) !== count($headers)) {
                        throw new RuntimeException('Linha inválida no CSV ZARC; dados anteriores foram preservados.');
                    }
                    $sourceRow = array_combine($headers, $values);
                    $get = static fn (string $key): string => trim((string) ($sourceRow[$headers[$indexes[$key]]] ?? ''));
                    $start = filter_var($get('safraini'), FILTER_VALIDATE_INT);
                    $end = filter_var($get('safrafin'), FILTER_VALIDATE_INT);
                    $code = $get('geocodigo');
                    $state = strtoupper($get('uf'));
                    if (! in_array($state, self::SOUTH_STATES, true)) {
                        continue;
                    }
                    if (! $start || ! $end || $start < 2000 || $start > 2200 || $end < $start || ! preg_match('/^\d{7}$/', $code) || ! preg_match('/^[A-Z]{2}$/', $state) || $get('nomecultura') === '' || $get('municipio') === '') {
                        $skipped++;

                        continue;
                    }
                    $hash = hash('sha256', json_encode($sourceRow, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                    $batch[] = ['dataset_key' => $datasetKey, 'row_hash' => $hash, 'season_start' => $start, 'season_end' => $end, 'culture' => $get('nomecultura'), 'state_code' => $state, 'municipality_code' => $code, 'municipality' => $get('municipio'), 'source_row' => json_encode($sourceRow, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()];
                    if (count($batch) >= 500) {
                        DB::table('zarc_records')->insertOrIgnore($batch);
                        $count += count($batch);
                        $batch = [];
                    }
                }
                if ($batch !== []) {
                    DB::table('zarc_records')->insertOrIgnore($batch);
                    $count += count($batch);
                }
                if ($count === 0 || $skipped > max(100, intdiv($count + $skipped, 100))) {
                    throw new RuntimeException('O CSV ZARC não continha registros compatíveis para os estados do Sul.');
                }
            });
        } finally {
            fclose($stream);
        }

        if ($skipped > 0) {
            Log::notice('Linhas ZARC incompletas foram ignoradas na importação.', ['dataset' => $datasetKey, 'imported' => $count, 'skipped' => $skipped]);
        }

        return $count;
    }

    private string $csvDelimiter = ',';

    private function nextSync(string $key): mixed
    {
        return match ($key) {
            'ibge_locations' => now()->addMonth()->startOfMonth()->setTime(2, 0),
            'brasilapi_holidays' => now()->addYear()->startOfYear()->setTime(2, 30),
            'mapa_zarc' => now()->addWeek()->startOfWeek()->setTime(3, 0),
            default => null,
        };
    }
}

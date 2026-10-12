<?php

namespace App\Services\RegionalIntegrations;

use App\Models\RegionalIntegration;

class IntegrationCatalog
{
    /** @return array<string, array{name: string, data_type: string, source_url: string, license: ?string, availability: string, enabled: bool}> */
    public static function definitions(): array
    {
        return [
            'ibge_locations' => ['name' => 'IBGE · estados e municípios', 'data_type' => 'Território oficial', 'source_url' => 'https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome', 'license' => 'Dados públicos do IBGE', 'availability' => 'available', 'enabled' => true],
            'brasilapi_holidays' => ['name' => 'BrasilAPI · feriados nacionais e estaduais', 'data_type' => 'Feriados', 'source_url' => 'https://brasilapi.com.br/api/feriados/v1/{ano}?uf={UF}', 'license' => 'BrasilAPI · licença MIT; origem referenciada pelo serviço', 'availability' => 'available', 'enabled' => true],
            'mapa_zarc' => ['name' => 'MAPA · ZARC Tábua de Risco', 'data_type' => 'Janelas oficiais por safra, cultura e município', 'source_url' => 'https://dados.agricultura.gov.br/dataset/tabua-de-risco-zoneamento-agricola-de-risco-climatico', 'license' => 'Creative Commons Attribution (CC BY)', 'availability' => 'available', 'enabled' => true],
            'embrapa_agroapi' => ['name' => 'Embrapa · AgroAPI Agritec', 'data_type' => 'Zoneamento, cultivares e produtividade', 'source_url' => 'https://www.portal.agroapi.cnptia.embrapa.br/api-docs/agritec/v2', 'license' => null, 'availability' => 'credentials_and_paid_tier', 'enabled' => false],
            'conab_prices' => ['name' => 'Conab · preços e produção', 'data_type' => 'Séries históricas e mercado', 'source_url' => 'https://portaldeinformacoes.conab.gov.br/', 'license' => 'Consultar condições de reutilização de cada conjunto', 'availability' => 'no_stable_public_api_confirmed', 'enabled' => false],
            'inmet_weather' => ['name' => 'INMET · dados meteorológicos', 'data_type' => 'Observações meteorológicas', 'source_url' => 'https://portal.inmet.gov.br/', 'license' => null, 'availability' => 'access_key_required', 'enabled' => false],
        ];
    }

    public function ensure(): void
    {
        foreach (self::definitions() as $key => $definition) {
            RegionalIntegration::query()->firstOrCreate(['key' => $key], ['key' => $key, ...$definition]);
        }
    }
}

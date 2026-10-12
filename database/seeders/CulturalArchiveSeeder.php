<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Institutional, source-linked drafts for editorial review; no images are copied. */
class CulturalArchiveSeeder extends Seeder
{
    public function run(): void
    {
        $editor = User::firstOrCreate(
            ['email' => 'acervo-cultural@desgarrados.invalid'],
            ['name' => 'Acervo cultural · revisão editorial', 'password' => Hash::make(Str::random(64)), 'email_verified_at' => now()],
        );

        $items = [
            [
                'category' => 'Patrimônio e memória', 'title' => 'São Miguel Arcanjo: vestígios e memória Guarani', 'slug' => 'sao-miguel-arcanjo-vestigios-memoria-guarani', 'state' => 'RS',
                'excerpt' => 'Rascunho factual baseado em referências do Iphan sobre o sítio arqueológico e a Tava Guarani. Revisão editorial pendente.',
                'body' => "RASCUNHO PARA REVISÃO EDITORIAL — texto original baseado nas fontes institucionais listadas abaixo; verificar atualização e abordagem com especialista antes de publicar.\n\nEm São Miguel das Missões, os remanescentes de São Miguel Arcanjo são reconhecidos pelo Iphan como sítio arqueológico. O conjunto foi tombado em 1938 e integra o Patrimônio Mundial desde 1983. A memória da região também é viva: a Tava, lugar de referência para o povo Guarani, foi inscrita pelo Iphan no Livro de Registro dos Lugares em 2014.\n\nEste registro patrimonial reúne camadas diferentes de história e pertencimento. Uma apresentação pública deve incluir a perspectiva Guarani e não reduzir o lugar a ruínas ou a uma narrativa somente colonial.",
                'sources' => [
                    ['name' => 'Iphan — Patrimônio Arqueológico do Rio Grande do Sul', 'url' => 'https://www.gov.br/iphan/pt-br/superintendencias/rio-grande-do-sul/patrimonio-arqueologico', 'note' => 'Tombamento de 1938, reconhecimento mundial em 1983 e registro da Tava em 2014.'],
                    ['name' => 'Iphan — Superintendência no Rio Grande do Sul', 'url' => 'https://www.gov.br/iphan/pt-br/superintendencias/rio-grande-do-sul', 'note' => 'Atuação institucional e inventários culturais no estado.'],
                ],
            ],
            [
                'category' => 'Patrimônio e memória', 'title' => 'Santa Catarina: muitas heranças no patrimônio protegido', 'slug' => 'santa-catarina-diversidade-patrimonio-protegido', 'state' => 'SC',
                'excerpt' => 'Rascunho factual sobre diversidade arquitetônica e referências culturais catarinenses, com base no Iphan. Revisão editorial pendente.',
                'body' => "RASCUNHO PARA REVISÃO EDITORIAL — texto original baseado em fonte institucional; confirmar recorte histórico e termos com pesquisador local antes de publicar.\n\nO Iphan descreve em Santa Catarina bens que registram diferentes trajetórias de imigração e formação cultural. Entre os exemplos arquitetônicos está a técnica enxaimel, associada à presença de comunidades alemãs, junto a heranças italiana, açoriana, polonesa, ucraniana e austríaca, entre outras.\n\nA diversidade também aparece em saberes ligados ao território: o órgão destaca a pesca artesanal com auxílio de botos e práticas tradicionais dos engenhos de farinha como referências em processo de reconhecimento. São pistas para um acervo que trate as culturas catarinenses no plural e acompanhe o estado de cada processo de proteção.",
                'sources' => [
                    ['name' => 'Iphan — Santa Catarina', 'url' => 'https://www.gov.br/iphan/pt-br/superintendencias/santa-catarina', 'note' => 'Descrição institucional da diversidade arquitetônica e referências culturais em processo.'],
                ],
            ],
            [
                'category' => 'Patrimônio e memória', 'title' => 'Caminhos históricos e fandango caiçara no Paraná', 'slug' => 'caminhos-historicos-fandango-caicara-parana', 'state' => 'PR',
                'excerpt' => 'Rascunho factual sobre referências materiais e imateriais do Paraná, baseado no Iphan. Revisão editorial pendente.',
                'body' => "RASCUNHO PARA REVISÃO EDITORIAL — texto original baseado em referência institucional; revisar com comunidades caiçaras e especialistas antes de publicar.\n\nNo Paraná, caminhos, cidades e práticas culturais ajudam a contar histórias que atravessam litoral e planalto. O Iphan registra a influência dos caminhos tropeiros e do ciclo da erva-mate entre as referências do patrimônio material; o centro histórico da Lapa conserva uma configuração urbana ligada ao antigo caminho das tropas.\n\nHá também patrimônio vivo: o Fandango Caiçara foi registrado como patrimônio imaterial pelo Iphan em novembro de 2012. Uma narrativa sobre essa manifestação deve reconhecer seus praticantes e comunidades como protagonistas, e não reproduzir letras, músicas ou imagens sem autorização.",
                'sources' => [
                    ['name' => 'Iphan — Paraná', 'url' => 'https://www.gov.br/iphan/pt-br/superintendencias/parana', 'note' => 'Registro do Fandango Caiçara em novembro de 2012 e ações de inventário cultural.'],
                    ['name' => 'Iphan — Patrimônio Material do Paraná', 'url' => 'https://www.gov.br/iphan/pt-br/superintendencias/parana/patrimonio-material', 'note' => 'Caminhos tropeiros, ciclo da erva-mate e conjunto histórico da Lapa.'],
                ],
            ],
        ];

        foreach ($items as $item) {
            $category = Category::firstOrCreate(['slug' => Str::slug($item['category'])], ['name' => $item['category']]);
            $publication = Publication::firstOrNew(['slug' => $item['slug']]);
            if (! $publication->exists) {
                $publication->forceFill([
                    'user_id' => $editor->id,
                    'category_id' => $category->id,
                    'title' => $item['title'],
                    'slug' => $item['slug'],
                    'excerpt' => $item['excerpt'],
                    'body' => $item['body'],
                    'type' => 'historia',
                    'credit' => 'Texto original de síntese · fontes institucionais relacionadas',
                    'origin_scope' => 'state',
                    'origin_state' => $item['state'],
                    'origin_city' => null,
                    'municipality_code' => null,
                    'source_references' => $item['sources'],
                    'status' => 'draft',
                    'published_at' => null,
                ])->save();
            }
            $publication->categories()->syncWithoutDetaching([$category->id]);
        }
    }
}

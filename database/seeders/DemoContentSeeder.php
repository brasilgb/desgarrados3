<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoContentSeeder extends Seeder
{
    /** Create repeatable, clearly fictional stories for every public notebook. */
    public function run(): void
    {
        $demo = User::firstOrCreate(
            ['email' => 'demo-content@desgarrados.invalid'],
            ['name' => 'Conteúdo demonstrativo', 'password' => Hash::make(Str::random(64)), 'email_verified_at' => now()],
        );

        Publication::query()->where('user_id', $demo->id)->where('credit', 'like', '%demonstrativ%')->whereIn('status', ['published', 'scheduled'])->update(['status' => 'draft', 'published_at' => null]);

        $examples = [
            ['category' => 'Partidas e reencontros', 'type' => 'historia', 'title' => 'A janela de onde se via a estação', 'excerpt' => 'Ficção de demonstração com ambientação imaginária na Campanha; não descreve fatos, costumes ou pessoas reais.', 'body' => "CONTEÚDO DEMONSTRATIVO — ficção original com ambientação imaginária na Campanha; não retrata pessoas ou acontecimentos locais.\n\nDa janela da casa antiga, Lia via a estação e sabia de cor o horário do trem. Anos depois, já na cidade grande, bastava escutar o apito de um ônibus para lembrar a rua de terra, a vizinha que acenava e a mesa posta para quem chegasse.", 'credit' => 'Ficção demonstrativa'],
            ['category' => 'Prosa de galpão', 'type' => 'causo', 'title' => 'O banco que sempre tinha mais um lugar', 'excerpt' => 'Causo imaginário ambientado na Fronteira, sem origem histórica ou tradição local atribuída.', 'body' => "CONTEÚDO DEMONSTRATIVO — causo fictício ambientado na Fronteira; não registra fato ou costume de uma comunidade real.\n\nNo galpão da vila, o banco parecia curto até alguém chegar. Então aparecia espaço para mais um, depois para outro. Ninguém descobriu o segredo da madeira; a explicação mais aceita era que a conversa boa fazia caber todo mundo.", 'credit' => 'Causo demonstrativo'],
            ['category' => 'Riso cotidiano', 'type' => 'anedota', 'title' => 'O mapa dobrado ao contrário', 'excerpt' => 'Cena fictícia de humor ambientada na Serra Gaúcha, criada para demonstração editorial.', 'body' => "CONTEÚDO DEMONSTRATIVO — humor original e fictício, ambientado sem retratar pessoas ou costumes reais da Serra Gaúcha.\n\nNa primeira semana na capital, Rui abriu o mapa para procurar o mercado. Uma senhora apontou a direção certa, ele agradeceu e foi embora. Só percebeu três quadras depois que segurava o mapa pelo lado de dentro. Voltou rindo: pelo menos a cidade já tinha começado a lhe ensinar a olhar de novo.", 'credit' => 'Texto demonstrativo'],
            ['category' => 'Versos de memória', 'type' => 'poema', 'title' => 'Rua guardada no bolso', 'excerpt' => 'Poema original, de ambientação imaginária no Litoral, sobre saudade e pertencimento.', 'body' => "CONTEÚDO DEMONSTRATIVO — poema original com cenário imaginário no Litoral; não reproduz obra de terceiros nem descreve fato local.\n\nGuardo a rua no bolso,\npequena dobra de chão.\nQuando a cidade amanhece distante,\nela me encontra pela mão.\n\nHá um portão, um rio,\ne uma voz chamando para entrar.\nLevo tudo sem alarde:\nraízes também sabem viajar.", 'credit' => 'Poema demonstrativo'],
            ['category' => 'Leituras do Sul', 'type' => 'literatura', 'title' => 'Caderno para uma casa emprestada', 'excerpt' => 'Fragmento ficcional de ambientação imaginária na região colonial, sem alegação histórica.', 'body' => "CONTEÚDO DEMONSTRATIVO — fragmento de ficção ambientado numa região colonial imaginária; não atribui fatos a comunidades reais.\n\nA casa nova tinha paredes claras e nenhum retrato. No primeiro domingo, a família colocou sobre a mesa a toalha que atravessara duas mudanças. Não era a mesma casa, mas a mesa reconheceu o gesto. Aos poucos, os objetos foram deixando de ser bagagem e viraram endereço.", 'credit' => 'Ficção demonstrativa'],
            ['category' => 'Memória sonora', 'type' => 'musica', 'title' => 'A roda que começou baixinho', 'excerpt' => 'Cena ficcional de convivência musical, ambientada no Planalto; não reproduz letras, melodias ou fatos locais.', 'body' => "CONTEÚDO DEMONSTRATIVO — cena ficcional com ambientação imaginária no Planalto; não reproduz letras, partituras ou gravações.\n\nNo encontro da associação, alguém trouxe um instrumento e começou um ritmo simples. Outra pessoa acompanhou batendo os dedos na mesa. Ninguém precisou anunciar o começo: quando perceberam, a roda já tinha espaço para quem tocava, para quem cantava baixinho e para quem preferia só escutar.", 'credit' => 'Cena demonstrativa'],
            ['category' => 'Saberes de comunidade', 'type' => 'tradição', 'title' => 'A mesa comprida da colheita', 'excerpt' => 'Narrativa fictícia sobre convivência urbana e saberes compartilhados, sem tradição real atribuída.', 'body' => "CONTEÚDO DEMONSTRATIVO — narrativa fictícia ambientada numa comunidade urbana imaginária do Sul; não descreve uma tradição real específica.\n\nNo fim da colheita, cada família trouxe um prato e uma história para a mesa comunitária. As crianças aprenderam a receita perguntando; os mais velhos aprenderam os nomes novos das ferramentas. O encontro não guardava o passado numa vitrine: deixava espaço para que cada geração acrescentasse seu jeito.", 'credit' => 'Narrativa demonstrativa'],
        ];

        foreach ($examples as $example) {
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($example['category'])],
                ['name' => $example['category']],
            );

            $attributes = [
                'user_id' => $demo->id,
                'category_id' => $category->id,
                'title' => $example['title'],
                'excerpt' => $example['excerpt'],
                'body' => $example['body'],
                'type' => $example['type'],
                'credit' => $example['credit'],
                'status' => 'draft',
                'published_at' => null,
                'origin_scope' => 'regional',
                'source_references' => [],
            ];
            $publication = Publication::firstOrNew(['slug' => Str::slug($example['title']), 'user_id' => $demo->id]);
            $isUneditedDemo = ! $publication->exists || (str_contains(mb_strtolower((string) $publication->credit), 'demonstrativ') && str_starts_with((string) $publication->body, 'CONTEÚDO DEMONSTRATIVO'));
            if ($isUneditedDemo) {
                $publication->forceFill(['slug' => Str::slug($example['title']), ...$attributes])->save();
            }
            $publication->categories()->syncWithoutDetaching([$category->id]);
        }
    }
}

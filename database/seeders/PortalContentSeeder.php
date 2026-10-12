<?php

namespace Database\Seeders;

use App\Models\PortalSetting;
use App\Models\Saying;
use App\Models\SitePage;
use App\Support\InstitutionalPages;
use Illuminate\Database\Seeder;

class PortalContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'portal_name' => 'Desgarrados',
            'portal_description' => 'Para quem partiu, para quem ficou e para quem nunca deixou de pertencer.',
            'logo_path' => '',
            'share_image_path' => '',
            'institutional_intro' => 'Um espaço cultural para guardar e compartilhar memórias do Sul.',
            'contact_email' => '',
            'instagram_url' => '',
            'facebook_url' => '',
            'youtube_url' => '',
            'show_sayings' => '1',
            'show_collections' => '1',
            'show_categories' => '1',
        ] as $key => $value) {
            PortalSetting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        foreach (InstitutionalPages::defaults() as $slug => $page) {
            SitePage::firstOrCreate(['slug' => $slug], [...$page, 'status' => 'published']);
        }

        foreach ([
            ['phrase' => 'Cavalo dado não se olha os dentes.', 'meaning' => 'Um presente é recebido com gratidão, sem exigir que seja perfeito.', 'context' => 'Ditado popular usado em conversas sobre generosidade e expectativa.', 'source_note' => 'Ditado de circulação ampla; sem autoria individual confirmada.'],
            ['phrase' => 'De grão em grão, a galinha enche o papo.', 'meaning' => 'Pequenas ações repetidas podem produzir um resultado importante.', 'context' => 'Lembra o valor da paciência no trabalho e nos projetos coletivos.', 'source_note' => 'Ditado popular; origem específica não confirmada.'],
            ['phrase' => 'Quem não tem cão caça com gato.', 'meaning' => 'Na falta do recurso ideal, a pessoa encontra outro caminho para resolver a situação.', 'context' => 'Uma expressão de improviso, presente em muitas regiões do Brasil.', 'source_note' => 'Ditado popular; sem autoria individual confirmada.'],
        ] as $saying) {
            Saying::firstOrCreate(['phrase' => $saying['phrase']], [...$saying, 'status' => 'published', 'user_id' => null]);
        }
    }
}

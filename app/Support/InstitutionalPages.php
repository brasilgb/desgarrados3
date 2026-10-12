<?php

namespace App\Support;

use App\Models\SitePage;
use App\Models\TermsVersion;

/**
 * Default copy for the institutional pages linked from the public footer.
 *
 * The seeder creates missing records from this catalog and the public controller
 * falls back to it while no record exists, so a saved page always takes precedence.
 */
class InstitutionalPages
{
    public const TERMS_SLUG = 'termos-de-contribuicao';

    /** @return array<string, array{title: string, summary: string, body: string}> */
    public static function defaults(): array
    {
        return [
            'sobre' => [
                'title' => 'Sobre o Desgarrados',
                'summary' => 'Um portal de histórias, memória e pertencimento.',
                'body' => '<p>O Desgarrados reúne relatos, causos, poesia e tradições de quem leva suas raízes consigo.</p><p>É um espaço para quem partiu, para quem ficou e para quem nunca deixou de pertencer. As histórias são avaliadas pela equipe antes de qualquer publicação.</p>',
            ],
            'proposta-cultural' => [
                'title' => 'Nossa proposta cultural',
                'summary' => 'Memória, tradição e vida do Sul contadas com cuidado, autoria e contexto.',
                'body' => implode('', [
                    '<p>O Desgarrados é um portal cultural dedicado às histórias de quem nasceu, vive ou carrega no peito o Sul do Brasil. O nome lembra quem se afastou do rebanho, da querência ou da cidade natal e, mesmo longe, segue pertencendo a ela.</p>',
                    '<h2>Memória que não se perde</h2>',
                    '<p>Muito do que somos passa de boca em boca: o causo contado na roda de chimarrão, a receita da avó, o verso de uma toada, o jeito de nomear o tempo e o trabalho no campo. Queremos guardar essas lembranças com respeito, registrando de onde vêm e quem as conta, para que continuem circulando.</p>',
                    '<h2>Cultura gaúcha e sul-brasileira, no plural</h2>',
                    '<p>Valorizamos a cultura gaúcha e as muitas culturas do Sul: da Campanha à Serra, do Litoral às Missões, das cidades às colônias, de Santa Catarina e do Paraná. Preferimos a diversidade das trajetórias reais à caricatura, e o contexto à generalização.</p>',
                    '<h2>O que você encontra aqui</h2>',
                    '<ul><li>Histórias de vida, causos e anedotas;</li><li>Poesia, literatura e música, sempre com crédito e respeito aos direitos autorais;</li><li>Tradições, ditados e expressões da memória popular;</li><li>Informações e ferramentas para o dia a dia de quem vive e trabalha no campo, na seção Nossa Terra.</li></ul>',
                    '<h2>Feito também por quem lê</h2>',
                    '<p>Visitantes e colaboradores podem enviar relatos, lembranças e fotografias. Cada contribuição é lida pela equipe editorial, que avalia, revisa com cuidado e só publica com autorização e crédito conforme a escolha de quem enviou.</p>',
                    '<h2>Tecnologia a serviço do encontro</h2>',
                    '<p>Usamos a tecnologia para aproximar pessoas, cultura e informação: organizar o acervo, facilitar a busca, levar ferramentas úteis ao campo e permitir que uma história contada em uma cidade pequena chegue a quem está longe.</p>',
                    '<p><strong>Tem uma lembrança que merece ser guardada?</strong> <a href="/contribua">Compartilhe sua história com o Desgarrados</a>.</p>',
                ]),
            ],
            'contato' => [
                'title' => 'Contato',
                'summary' => 'Fale com a equipe do Desgarrados.',
                'body' => '<p>Use o formulário abaixo para dúvidas, sugestões, correções ou pedidos sobre conteúdos publicados. A equipe lê todas as mensagens e responde pelo e-mail informado quando necessário.</p><p>Para enviar uma história, causo ou lembrança para avaliação, use o <a href="/contribua">formulário de contribuição</a>.</p>',
            ],
            'termos-de-contribuicao' => [
                'title' => 'Termos de envio de contribuições',
                'summary' => 'Como funcionam o envio, a avaliação e a publicação de materiais enviados ao Desgarrados.',
                'body' => self::termsBody(),
            ],
        ];
    }

    /** @return array{title: string, summary: string, body: string}|null */
    public static function default(string $slug): ?array
    {
        return self::defaults()[$slug] ?? null;
    }

    /**
     * The terms text currently shown at /termos-de-contribuicao, stored once per distinct content.
     *
     * The version is identified by a hash of the sanitized title and body, so it only changes when the
     * text changes, and earlier versions stay untouched. Returns null while a saved page is unpublished,
     * because visitors cannot read terms that are not public.
     */
    public static function currentTerms(): ?TermsVersion
    {
        $current = self::resolveCurrentTerms();
        if (! $current) {
            return null;
        }
        $attributes = ['document' => self::TERMS_SLUG, 'content_hash' => $current['hash']];

        return TermsVersion::query()->where($attributes)->first()
            ?? TermsVersion::createOrFirst($attributes, [
                'label' => self::termsLabel($current['hash']),
                'source' => $current['source'],
                'title' => $current['title'],
                'body' => $current['body'],
            ]);
    }

    /** Label of the terms visitors see right now, without recording a version. */
    public static function currentTermsLabel(): ?string
    {
        $current = self::resolveCurrentTerms();

        return $current ? self::termsLabel($current['hash']) : null;
    }

    /** @return array{title: string, body: string, hash: string, source: string}|null */
    private static function resolveCurrentTerms(): ?array
    {
        $page = SitePage::query()->where('slug', self::TERMS_SLUG)->first(['title', 'body', 'status']);
        if ($page && $page->status !== 'published') {
            return null;
        }

        $default = self::default(self::TERMS_SLUG);
        $title = $page->title ?? $default['title'];
        $body = app(HtmlContentSanitizer::class)->sanitize($page->body ?? $default['body']);

        return ['title' => $title, 'body' => $body, 'hash' => hash('sha256', $title."\n".$body), 'source' => $page ? 'pagina' : 'padrao'];
    }

    private static function termsLabel(string $hash): string
    {
        return 'termos-'.substr($hash, 0, 12);
    }

    private static function termsBody(): string
    {
        return implode('', [
            '<p>Estes termos explicam como o Desgarrados recebe, avalia e, quando autorizado, publica materiais enviados pelo <a href="/contribua">formulário de contribuição</a>. O aceite é solicitado a cada envio.</p>',
            '<h2>1. Materiais aceitos</h2>',
            '<p>O formulário recebe relatos de vida, lembranças, causos, anedotas, tradições e fotografias relacionadas à memória e à cultura do Sul. Fotografias devem estar em JPG, PNG ou WebP, com até 5 MB. O formulário não recebe áudio ou vídeo; poemas, letras de música e obras de terceiros não devem ser enviados sem autorização específica.</p>',
            '<h2>2. Identificação e responsabilidade</h2>',
            '<p>Nome, pseudônimo, município, UF e e-mail são opcionais. Quem envia declara que as informações prestadas são verdadeiras e responde pelo conteúdo enviado.</p>',
            '<h2>3. Autoria e direitos</h2>',
            '<p>Envie apenas material de sua autoria ou que você esteja autorizado a compartilhar. A autoria e os direitos sobre o material continuam com você; o envio não transfere a propriedade do texto ou da imagem ao Desgarrados.</p>',
            '<h2>4. Autorização de uso e publicação</h2>',
            '<p>Ao marcar a autorização, você concede licença não exclusiva e gratuita para a equipe receber, armazenar, avaliar, editar sem alterar o sentido e, se aprovada, reproduzir e disponibilizar a contribuição no portal Desgarrados para fins culturais e de preservação da memória. A licença abrange as cópias técnicas necessárias para hospedagem, segurança e apresentação responsiva. Não transfere a titularidade, não autoriza exploração comercial, sublicenciamento para outros usos, publicação em redes sociais, campanhas ou produtos, nem adaptação substancial. Esses usos exigem autorização específica. O conteúdo pode ficar acessível mundialmente pela internet. Seu nome ou pseudônimo só aparece como crédito se você autorizar separadamente. A fotografia depende de autorização específica e separada.</p>',
            '<h2>5. Tratamento editorial, revisão e moderação</h2>',
            '<p>Todo envio passa por leitura da equipe editorial. Podemos ajustar ortografia, clareza, estrutura e extensão, preservando o sentido e o crédito informado. Enquanto avaliado, o material permanece privado. O envio não garante publicação.</p>',
            '<h2>6. Materiais que envolvem terceiros</h2>',
            '<p>Evite incluir dados pessoais de outras pessoas, como nomes completos, endereços ou informações sensíveis, sem autorização delas. Relatos sobre terceiros devem respeitar sua honra, imagem e privacidade.</p>',
            '<h2>7. Fotografias e direitos de imagem</h2>',
            '<p>Envie somente fotografias próprias ou autorizadas e informe o crédito ou a autoria. A licença da fotografia é limitada à avaliação e eventual publicação no portal, nos termos acima. Ela não substitui autorização de uso de imagem das pessoas retratadas. Se alguém puder ser identificado, obtenha autorização adequada antes do envio; no caso de criança ou adolescente, não envie imagem identificável sem autorização do responsável e avaliação editorial de seu melhor interesse. O portal pode recusar ou retirar imagem quando houver dúvida sobre direitos ou risco à pessoa retratada.</p>',
            '<h2>8. Obras protegidas por direitos autorais</h2>',
            '<p>Não envie letras de música, poemas, trechos de livros, fotografias ou outras obras protegidas de terceiros sem autorização de quem detém os direitos. A indicação da fonte ou do crédito não substitui autorização quando exigida. Limitações e exceções legais permanecem aplicáveis, mas a equipe pode pedir comprovação ou recusar material quando a situação não estiver clara.</p>',
            '<h2>9. Recusa de materiais</h2>',
            '<p>A equipe pode recusar ou moderar material que viole direitos, exponha indevidamente dados pessoais, contenha discurso de ódio, discriminação, violência, conteúdo ilegal ou publicitário, ou não se relacione com a proposta do portal. A moderação não implica renúncia a direitos do autor ou de terceiros.</p>',
            '<h2>10. Correção ou retirada</h2>',
            '<p>Você pode pedir correção, interromper a autorização para usos futuros ou solicitar retirada de material em avaliação ou publicado pela página de <a href="/contato">Contato</a>. A equipe verificará o pedido e cessará usos sob seu controle quando cabível, sem prometer remoção de cópias já feitas por terceiros, caches ou obrigações legais de conservação. Isso não afeta direitos indisponíveis nem usos permitidos por lei.</p>',
            '<h2>11. Crianças e adolescentes</h2>',
            '<p>Não envie contribuição ou imagem identificável de criança ou adolescente sem autorização apropriada de seu responsável. A equipe pode pedir comprovação, ocultar identificadores, recusar ou retirar material para proteger os direitos e o melhor interesse da pessoa menor de idade. A autorização de quem envia não substitui a autorização de imagem ou de direitos de terceiros.</p>',
            '<h2>12. Dados pessoais</h2>',
            '<p>Os dados enviados são usados para avaliar, editar e, se autorizado, publicar a contribuição, e para contato somente quando você o autorizar. O e-mail nunca é publicado. Mais detalhes estão na <a href="/politica-de-privacidade">Política de Privacidade</a>.</p>',
            '<h2>13. Dúvidas</h2>',
            '<p>Dúvidas sobre contribuições podem ser enviadas pela página de <a href="/contato">Contato</a>.</p>',
        ]);
    }
}

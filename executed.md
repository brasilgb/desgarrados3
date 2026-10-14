# Resultado da execução — Auditoria e povoamento editorial (14/10/2026)

## Comparação com o correio anterior

O `correio.md` estava diferente da versão versionada no Git. A versão anterior era "DESGARRADOS-NEXT-29 — Localização regional global"; a atual pede auditoria, pesquisa e povoamento editorial. A nova instrução foi executada.

## Onde a carga foi feita

O único banco do projeto é o MySQL de produção, usado pelo contêiner que serve `desgarrados.com.br`. Gravar nele seria publicar no site ao vivo; além disso, o contêiner roda uma cópia própria do código que ainda não tem o importador, e a instrução proíbe deploy. Por isso:

- **o banco de produção foi apenas lido** (contagens e a tabela `territorial_units`);
- toda a carga foi feita e validada numa **base SQLite descartável**, com o código desta pasta, imagens baixadas de verdade e o site servido localmente;
- a carga em produção fica pronta para um único comando depois do deploy (ver Pendências).

## Auditoria

### Estado encontrado (produção)

| Item | Quantidade |
|---|---:|
| Publicações (todos os cadernos) | 0 |
| Categorias, coleções, séries | 0 |
| Mídias | 0 |
| Guias de produção (Nossa Terra) | 0 |
| Ditados, páginas institucionais salvas, usuários | 0 |
| Municípios e UFs do IBGE | 5.571 + 27 |

O portal estava estruturalmente pronto e sem nenhum acervo. Todos os cadernos, coleções e a seção de produção rural de Nossa Terra levavam a listagens vazias. As páginas institucionais só aparecem pelos textos padrão do código.

### Estrutura mapeada

`Página → seção → caderno (tipo) → categorias/coleções/séries → conteúdo`

- Cultura Gaúcha: 7 cadernos em `/cadernos/{slug}`, um por tipo de publicação (`historia`, `causo`, `anedota`, `poema`, `literatura`, `musica`, `tradição`), com filtro por assunto (categorias), coleções (`/colecoes/{slug}`) e séries (capítulos).
- Nossa Terra: ferramentas regionais e guias de produção (`/nossa-terra/producao`, modelo `ProductionGuide`, categorias fixas).
- Publicação: corpo HTML sanitizado, capa (`Media`), créditos, SEO, localização (estado/município IBGE) e fontes (`source_references`, JSON).
- Imagens: `Media` sem nenhum campo de origem ou licença; a visibilidade pública é sincronizada pelo uso em publicações publicadas.

### Validação pública após a carga (ambiente local)

- **380 páginas rastreadas** (home, 7 cadernos com todos os filtros por assunto, 12 coleções, 105 publicações, 18 guias, paginação, busca, sitemap, páginas institucionais): **todas com status 200**.
- **48 imagens públicas** servidas sem erro; nenhuma listagem vazia; nenhum título acima de 90 caracteres; nenhum slug duplicado.
- Os 25 rascunhos respondem 404 ao público; as 105 publicações publicadas estão todas no `sitemap.xml`.
- A segunda execução do importador não criou nem baixou nada (idempotência confirmada).
- Revisão visual com Chrome sem interface (home, caderno, coleção, publicação, ensaio fotográfico). Cards, capas, legendas e fontes ficaram corretos. **Problema encontrado e corrigido:** os créditos de imagem saíam como "Unknown authorUnknown author · Public domain"; agora saem como "Autor desconhecido · Domínio público · Wikimedia Commons".

## Conteúdo

| Seção | Antes | Adicionados | Publicados | Rascunhos |
|---|---:|---:|---:|---:|
| Histórias (inclui 3 ensaios fotográficos) | 0 | 24 | 20 | 4 |
| Causos | 0 | 21 | 21 | 0 |
| Anedotas | 0 | 22 | 20 | 2 |
| Poesia | 0 | 15 | 11 | 4 |
| Literatura | 0 | 25 | 17 | 8 |
| Música | 0 | 12 | 7 | 5 |
| Tradições | 0 | 11 | 9 | 2 |
| Nossa Terra — guias de produção | 0 | 18 | 18 | 0 |
| **Total** | **0** | **148** | **123** | **25** |

Também foram criados 27 assuntos (categorias), 12 coleções (Obra de Simões Lopes Neto, Porto Alegre antiga, Imigração italiana, Imigração alemã, Missões, Territórios negros, Vida campeira, Serra gaúcha, Fronteira e Campanha, Litoral e herança açoriana, Ferrovias do RS, Enchentes históricas) e 3 séries (Lendas do Sul, Contos gauchescos, Casos do Romualdo).

Destaques:

- **Domínio público na íntegra:** obra de João Simões Lopes Neto (morto em 1916): 12 entradas de *Lendas do Sul*, 19 *Contos gauchescos*, 22 *Casos do Romualdo* (humor) e 7 seleções do *Cancioneiro Guasca*; além de Lobo da Costa ("A alguém") e Cruz e Sousa ("Antífona", 1893). Os textos vêm da transcrição do Wikisource, não da memória.
- **Textos editoriais próprios:** fundação de Porto Alegre e de Rio Grande, charqueadas, São Leopoldo 1824, imigração italiana 1875, Farroupilha, Lanceiros Negros e Porongos, Federalista, Missões e Guerra Guaranítica, tropeirismo, ferrovias, Colônia Africana, Areal da Baronesa, Jaguarão, Santo Amaro, Santa Tereza, Santa Maria, autores (Erico, Scliar, Alcides Maya, Caldre e Fião, Partenon Literário…), música (chamamé, chimarrita, Teixeirinha, Troncos Missioneiros, gaita, sopapo, Elis Regina) e tradições (chimarrão, pilcha, 35 CTG e Chama Crioula, Festa da Uva, Oktoberfest, kerb, talian, Tava Guarani, quilombos).
- **Nossa Terra:** 18 guias com dados oficiais (médias 2020–2022 da PAM/PPM do IBGE, compiladas pelo Atlas Socioeconômico do RS): arroz, soja, trigo, milho, feijão e mandioca, fumo, erva-mate, bovinos, ovinos, suínos, aves e ovos, leite, uva e vinho, maçã, pêssego, citros, banana do Litoral Norte e cooperativismo.
- **Distribuição regional:** Metropolitana/Porto Alegre, Vale do Sinos, Serra, Vale do Taquari, Vale do Rio Pardo, Missões, Campanha e Fronteira Oeste, Sul (Pelotas, Rio Grande, Jaguarão), Centro (Santa Maria), Planalto e Norte, Campos de Cima da Serra e Litoral; culturas campeira, urbana, italiana, alemã, açoriana, indígena e afro-gaúcha. Das 130 publicações, 59 estão vinculadas a um município do catálogo IBGE, 9 ao estado e 62 marcadas como memória regional (sobretudo os textos de Simões Lopes Neto, que não têm local único).

## Fontes

Principais instituições e acervos consultados (todo link foi verificado com resposta HTTP 200 antes de entrar no acervo):

- Iphan (superintendências RS e PR, patrimônio material, arqueológico e imaterial; certidão do Talian; publicação sobre o sopapo; dossiê da Ponte Barão de Mauá)
- Atlas Socioeconômico do RS / Secretaria de Planejamento (23 páginas temáticas, com dados IBGE)
- UNESCO (Missões Jesuíticas; chamamé)
- Embrapa (Uva e Vinho, Trigo, Pecuária Sul, Clima Temperado, Suínos e Aves, Soja), IRGA
- Universidades: UFRGS (Sextante, Iluminuras, Projeto Alma), UFPel, USP (Revista de História), FURG (Biblos), UCS, Unisc, Uergs
- Academia Brasileira de Letras, Instituto Moreira Salles, IHGRGS, Casa de Cultura Mario Quintana, Museu Júlio de Castilhos, Centro Nacional de Folclore e Cultura Popular, Museu Brasileiro de Rádio e Televisão
- Governo do RS, Senado Federal, Câmara dos Deputados (biblioteca digital), Prefeitura de Pelotas, Festa Nacional da Uva, MTG
- Wikisource (textos em domínio público) e Wikimedia Commons (imagens)
- Wikipédia apenas como orientação e complemento; **nenhum item foi publicado tendo só a Wikipédia como fonte**

Cada fonte guarda nome, URL, autor/instituição quando disponível, data de acesso (14/10/2026), licença quando relevante e nota sobre o que ela sustenta.

## Imagens

- **55 imagens adicionadas** (48 públicas; 7 usadas só por rascunhos ficam no armazenamento privado, pelo mecanismo já existente).
- Origem: todas do Wikimedia Commons, com a licença lida dos metadados do próprio arquivo, e não declarada à mão.
- Licenças: 47 domínio público (fotografias de 1851 a 1950, incluindo Virgílio Calegari, Lunara, Domingos Mancuso, acervos de Caxias do Sul e da Colônia Africana, aquarela de Wendroth), 1 CC0, 6 CC BY-SA 4.0, 1 CC BY-SA 3.0.
- Cada imagem guarda título, descrição, autor, crédito, data, local, legenda, licença, URL da licença e URL de origem (`media.provenance`), além de texto alternativo.
- 30 fotografias históricas estão em capas e em 3 ensaios fotográficos com legendas (Porto Alegre 1900–1930, Caxias do Sul, Colônia Africana).
- **Recusadas por falta de segurança jurídica (na seleção):** fotos dos anos 1950 e 1970 marcadas como "domínio público" no Commons (praças de Rio Grande, parque de Caxias, uma foto de Teixeirinha), porque pela lei brasileira a fotografia só entra em domínio público 70 anos após a publicação; fotos da Prefeitura de Porto Alegre com licença "Attribution" genérica; arquivos com licença NC/ND. Não havia imagem segura para as charqueadas nem para a enchente de 1941, e esses textos seguem sem imagem. O importador também recusa automaticamente NC, ND, restrições adicionais, SVG e TIFF.

## Direitos autorais

- Reprodução integral só de obras de autores mortos há mais de 70 anos (Lei 9.610/1998, art. 41) ou de poesia popular de autoria desconhecida, sempre a partir de transcrição identificável, com edição de referência e grafia original preservada.
- Autores protegidos (Erico Verissimo, Mario Quintana, Scliar, Dyonélio, Cyro Martins, Caio F. Abreu, Faraco, Lupicínio, Teixeirinha etc.): apenas textos editoriais próprios, sem trechos de obras e sem letras de canções. Cada um traz a nota "obra protegida".
- Lendas e causos de tradição oral identificados como tais ("lenda da tradição oral na versão literária de…"); nenhuma autoria inventada.
- Textos editoriais escritos do zero a partir das fontes, sem paráfrase de um texto único.
- Datas e afirmações sem confirmação foram retiradas ou o item ficou em rascunho. Exemplos: data de fundação do MTG (fontes divergem), ano de criação do IRGA, cidades da Festa do Divino, data exata do primeiro trem.

## Desenvolvimento

- **Migration:** `2026_10_20_100000_add_provenance_to_media` (coluna JSON `media.provenance`). As publicações reaproveitam `source_references`, agora com `author`, `license` e `accessed_at`.
- **Models / controllers:** `Media` (provenance); `Admin\PublicationController` passa a validar e preservar os novos campos de fonte ao salvar pelo painel; `ProductionGuideAdminController` ganhou as categorias Apicultura, Pesca e Cooperativismo e agroindústria.
- **Comando:** `php artisan desgarrados:editorial-import [--images] [--update] [--dry-run] [--report=arquivo.json] [--path=…]`.
- **Serviços:** `App\Services\EditorialImport\EditorialImporter` (idempotente por slug; nunca sobrescreve item editado por pessoa; transação única; sanitiza HTML; resolve município pelo IBGE; registra revisão; sincroniza visibilidade de mídia; recusa publicar item sem fonte) e `CommonsImageFetcher` (licença verificada nos metadados, pausa entre downloads, respeito a `Retry-After`).
- **Dados:** `database/data/editorial/*.json` (taxonomia, 7 cadernos, guias). Autor institucional "Acervo Desgarrados" (conta inativa, sem login).
- **Front-end:** `portal/show.tsx` mostra autor e licença de cada fonte, em letra pequena.
- **Testes adicionados:** `tests/Feature/EditorialImportTest.php` (7 testes: criação com sanitização, fontes, localização e taxonomia; idempotência; proteção de edição humana; bloqueio de publicação sem fonte e dry-run; licença de imagem, proveniência, galeria e créditos; comando; validade do acervo empacotado).
- **Testes executados:**
  - `php artisan test`: 174 aprovados, 2 ignorados, **6 falhas que já existiam antes desta etapa** (mesmos testes, `LogicException`: o GD da imagem Docker não gera imagens falsas). Sem relação com esta mudança.
  - `EditorialImportTest`: 7/7 aprovados.
  - `phpstan analyse`: 0 erros. `pint --test`: aprovado.
  - `npm run types:check` e `npm run build` (em imagem Docker descartável): aprovados.
- Bug encontrado e corrigido durante a validação: o Wikimedia limitou downloads em massa (HTTP 429) e o importador abortava. Agora ele espaça os downloads, respeita `Retry-After` e recusa a imagem em vez de interromper a carga.

Nenhum push, commit ou deploy foi feito.

## Pendências (decisão humana)

1. **Carga em produção:** depois do deploy desta versão, rodar no contêiner `php artisan migrate --force` e `php artisan desgarrados:editorial-import --images --report=storage/app/import-report.json`. Observação: em produção há uma migration pendente que não é desta etapa (`2026_10_19_100000_drop_zarc_records_table`); ela será aplicada junto.
2. **25 rascunhos** aguardando revisão, sobretudo por falta de fonte além da Wikipédia: Muckers, primeiro trem (data 14 ou 15/04/1874), enchente de 1941 (sem foto licenciada), Calegari e Lunara, Alceu Wamosy ("Duas almas" é domínio público, mas falta edição digitalizada confiável), Eduardo Guimaraens, Lila Ripoll, Aureliano de Figueiredo Pinto, Dyonélio, Cyro Martins, Josué Guimarães, Qorpo-Santo, Assis Brasil, Faraco, Caio F. Abreu, Antônio Chimango, Lupicínio, Pedro Raymundo, bugio, Califórnia da Canção, Hino Rio-Grandense, churrasco, cavalo crioulo.
3. **Dois Casos do Romualdo em rascunho por conteúdo** (XV "O gringo das linguiças": estereótipo de imigrante italiano e crueldade animal; XVII "Essência de cachorro": estereótipo indígena e matança de cães). Decidir se publicam com nota de contexto ou ficam fora.
4. **Texto sobre a Tava:** pede revisão de lideranças ou pesquisadores Guarani sobre a tradução e o uso do termo.
5. Crédito longo numa foto da Revolução Federalista (o campo de autor do Commons traz uma nota de acervo); se quiser, encurtar no painel de mídia.

## Carga em produção (executada depois, a pedido)

Feita em 14/10/2026, depois de autorização explícita:

1. Backup antes de qualquer mudança: `/opt/infra-abrasil/backups/desgarrados_pre_editorial_20261014_153740.sql` (347 MB) e cópias de `docker-compose.yml` e `nginx/conf.d/apps.conf` com o mesmo carimbo.
2. Build e reinício só de `desgarrados`, `desgarrados-worker` e `desgarrados-scheduler`; os três ficaram saudáveis.
3. Rodada **apenas** a migration `2026_10_20_100000_add_provenance_to_media`. A `2026_10_19_100000_drop_zarc_records_table` **continua pendente**, e a tabela `zarc_records` segue intacta (249.078 linhas).
4. **Correção no nginx (problema anterior a esta etapa):** em produção nenhuma URL `/storage/...` era servida (404), então nenhuma imagem do portal, inclusive uploads do painel, apareceria. Foram acrescentados o volume `./volumes/desgarrados:/var/www/desgarrados/storage:ro` no nginx e `location ^~ /storage/` nos dois blocos do desgarrados. Validado com `nginx -t` num contêiner temporário; depois o nginx foi recriado e todos os sites responderam normalmente.
5. O volume público pertencia ao `root` e o PHP não conseguia gravar nele (uploads pelo painel também falhariam); a posse passou para `www-data`. A pasta estava vazia.
6. Carga: `php artisan desgarrados:editorial-import --images`, rodando como `www-data`. Resultado igual ao da validação: 130 publicações (105 publicadas, 25 rascunhos), 18 guias e 55 imagens, nenhuma recusada.
7. Verificação no site ao vivo: 380 páginas com status 200, as 48 imagens públicas carregando, rascunhos com 404 e `.php` sob `/storage/` com 404.

### Novas pendências

- **`storage/app/private` não é volume:** ele fica dentro do contêiner. As 7 imagens usadas só por rascunhos foram para lá, junto com o relatório da carga, e serão perdidas na próxima reconstrução do contêiner; o mesmo vale para fotos privadas de contribuições. Sugestão: montar `./volumes/desgarrados-private:/var/www/html/storage/app/private` no compose. Até lá, para publicar um desses rascunhos depois de um redeploy, reimporte com `--images --update`.
- A migration `drop_zarc_records_table` segue pendente por decisão sua; o backup acima contém a tabela.
- O `PortalContentSeeder` não foi rodado em produção; as páginas institucionais seguem com os textos padrão do código.

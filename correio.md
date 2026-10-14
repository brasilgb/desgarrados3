# DESGARRADOS — Auditoria, pesquisa, curadoria e povoamento editorial automático

Data: 14/10/2026  
Projeto alvo: `apps/desgarrados-next/`

## OBJETIVO

Faça uma auditoria completa do portal Desgarrados existente e, a partir da estrutura real encontrada no projeto, inicie um processo de **povoamento editorial consistente e de qualidade**.

Você está autorizado a:

- analisar todas as páginas públicas;
- analisar menus, submenus, links, cadernos, categorias, séries e coleções;
- identificar páginas vazias, pouco preenchidas ou com conteúdo provisório;
- pesquisar conteúdo na internet;
- consultar fontes públicas e confiáveis;
- cadastrar automaticamente conteúdo no banco do projeto;
- baixar e cadastrar imagens quando a licença/origem permitir;
- criar textos editoriais próprios baseados em fatos e fontes;
- complementar conteúdos existentes;
- cadastrar autores históricos quando necessário;
- criar categorias, tags, séries e coleções quando isso melhorar a organização;
- preencher metadados, SEO, resumos, imagens de capa e créditos;
- executar seeders, scripts ou comandos Laravel necessários;
- validar visualmente e através de testes o resultado.

Trabalhe **somente em `apps/desgarrados-next/`**.

Não faça push nem deploy nesta etapa.

---

# 1. PRIMEIRO: ENTENDA O SITE REAL

Antes de cadastrar qualquer coisa, faça uma auditoria do projeto existente.

Analise:

- rotas públicas;
- página inicial;
- Cultura Gaúcha;
- Nossa Terra;
- páginas institucionais;
- busca;
- menus e submenus;
- cadernos;
- categorias;
- séries;
- coleções;
- acervo fotográfico;
- estrutura das publicações;
- modelos/tabelas existentes;
- campos disponíveis no editor;
- sistema de autores;
- imagens;
- SEO;
- localização regional;
- conteúdo já cadastrado.

NÃO crie uma estrutura editorial paralela sem necessidade.

Aproveite o que já existe.

Monte internamente um mapa:

`Página → seção → caderno → categoria → tipo de conteúdo → quantidade atual → conteúdo necessário`

Identifique links públicos que conduzem a páginas vazias, quase vazias ou excessivamente genéricas.

---

# 2. NÃO QUERO UM BLOG GENÉRICO

O Desgarrados é um **portal regional, cultural e de utilidade**, e não um blog comum.

O objetivo editorial é preservar e divulgar:

- memória;
- cultura;
- identidade;
- literatura;
- tradição oral;
- história;
- costumes;
- música;
- fotografia;
- produção rural;
- conhecimento regional;
- acontecimentos e características do Rio Grande do Sul e do Sul do Brasil.

O conteúdo deve ter personalidade regional, mas sem transformar tudo em caricatura gauchesca.

Evite:

- excesso de CTAs;
- linguagem de marketing;
- textos genéricos gerados apenas para SEO;
- páginas artificiais;
- títulos caça-cliques;
- conteúdo repetitivo;
- enchimento textual.

---

# 3. CULTURA GAÚCHA — CONTEÚDOS PRIORITÁRIOS

Analise a estrutura existente e povoe adequadamente os cadernos relacionados a:

## Histórias

Pesquisar e produzir conteúdos sobre:

- episódios históricos do Rio Grande do Sul;
- formação das cidades;
- imigração;
- tropeirismo;
- charqueadas;
- estâncias;
- ferrovias;
- navegação;
- colonização;
- Revolução Farroupilha;
- Revolução Federalista;
- movimentos regionais;
- personagens históricos;
- vida cotidiana antiga;
- comércio;
- agricultura;
- pecuária;
- desenvolvimento urbano;
- história de comunidades do interior.

Prefira episódios específicos a enormes textos enciclopédicos.

---

## Causos

Pesquisar causos tradicionais e narrativas da tradição oral.

Temas possíveis:

- peões;
- tropeiros;
- carreteiros;
- estancieiros;
- pescadores;
- colonos;
- viajantes;
- bolichos;
- bailes;
- carreiras;
- assombrações;
- tempestades;
- enchentes;
- animais;
- acontecimentos curiosos.

Quando o causo não tiver autoria conhecida, identificar corretamente como:

- tradição oral;
- relato popular;
- autor desconhecido;
- adaptação editorial baseada em fonte identificada.

Nunca invente autoria histórica.

---

# 4. ANEDOTAS E HUMOR REGIONAL

Pesquisar anedotas tradicionais e humor regional.

Pode incluir:

- gaúcho;
- colono;
- italiano;
- alemão;
- fronteiriço;
- pescador;
- tropeiro;
- interior;
- bolicho;
- futebol;
- vida rural.

Tenha cuidado com conteúdos ofensivos ou degradantes.

Privilegie humor de situação, linguagem e costumes.

Anedotas muito curtas podem ser cadastradas como conteúdo curto, caso o modelo suporte.

Não transforme anedotas modernas protegidas por direitos autorais em cópias integrais.

---

# 5. POESIA

Pesquisar poetas e poesia relacionada ao Rio Grande do Sul e ao Sul.

Prioridade absoluta para:

1. obras em domínio público;
2. autores históricos cujos direitos já tenham expirado;
3. acervos oficiais;
4. bibliotecas digitais;
5. instituições culturais;
6. obras explicitamente licenciadas para reprodução.

Quando uma obra estiver em domínio público, ela poderá ser reproduzida conforme permitido.

Quando NÃO estiver em domínio público:

- não copie integralmente;
- apresente o autor;
- contextualize a obra;
- produza texto editorial;
- utilize somente pequenos trechos quando permitido;
- indique a fonte.

Pesquisar também poesia regional menos conhecida, evitando limitar o portal aos autores mais famosos.

---

# 6. LITERATURA

Criar um acervo introdutório sobre literatura gaúcha e sul-brasileira.

Pesquisar:

- autores;
- livros;
- contos;
- crônicas;
- movimentos literários;
- regionalismo;
- literatura de imigração;
- literatura urbana;
- literatura campeira;
- literatura contemporânea.

Para autores históricos em domínio público, avaliar publicação de:

- contos;
- poemas;
- pequenos textos;
- crônicas;
- excertos adequados.

Para obras protegidas:

NÃO copiar capítulos, contos ou poemas completos.

Crie artigos próprios apresentando:

- autor;
- obra;
- contexto;
- importância;
- características;
- curiosidades;
- referências.

---

# 7. MÚSICA

Criar conteúdo sobre música do Rio Grande do Sul.

Abranger:

- música nativista;
- música missioneira;
- música serrana;
- música tradicionalista;
- milonga;
- chamamé;
- vaneira;
- vanerão;
- bugio;
- chamarrita;
- música urbana;
- festivais;
- compositores;
- intérpretes;
- instrumentos;
- história dos ritmos.

IMPORTANTE:

Não reproduzir letras de músicas protegidas integralmente.

Pode:

- contar a história da música;
- falar dos compositores;
- explicar contexto;
- analisar significado;
- registrar discografia;
- usar pequenos trechos quando legalmente adequado;
- incorporar/linkar conteúdo oficial quando possível.

---

# 8. TRADIÇÕES

Criar conteúdos sobre:

- chimarrão;
- churrasco;
- fogo de chão;
- roda de mate;
- pilcha;
- bombacha;
- lenço;
- poncho;
- guaiaca;
- botas;
- indumentária;
- CTGs;
- fandangos;
- bailes;
- rodeios;
- cavalos;
- provas campeiras;
- tropeirismo;
- carreiras;
- culinária;
- festas regionais;
- festas de imigração;
- tradições italianas;
- tradições alemãs;
- tradições açorianas;
- tradições indígenas;
- tradições fronteiriças.

Evite apresentar uma única cultura como se representasse todo o Rio Grande do Sul.

---

# 9. NOSSA TERRA

O portal não deve ficar restrito à Campanha ou ao Pampa.

Representar também:

- Serra Gaúcha;
- Vale dos Sinos;
- Vale do Taquari;
- Região Metropolitana;
- Litoral;
- Missões;
- Planalto;
- Fronteira;
- Centro;
- Sul;
- Norte;
- Campos de Cima da Serra;
- demais regiões relevantes.

Produzir conteúdo informativo relacionado a:

### Pecuária

- bovinos;
- ovinos;
- lã;
- criação;
- raças;
- arroba;
- manejo;
- história da pecuária regional.

### Agricultura

- arroz;
- soja;
- milho;
- trigo;
- feijão;
- frutas;
- hortaliças;
- calendário agrícola;
- produção regional.

### Serra Gaúcha

Dar atenção especial a:

- viticultura;
- uvas;
- vinhos;
- suínos;
- aves/frangos;
- produção familiar;
- agroindústria;
- imigração italiana;
- gastronomia.

### Outras produções

- leite;
- mel;
- apicultura;
- suinocultura;
- avicultura;
- pesca;
- frutas;
- ração;
- pequenas propriedades;
- cooperativismo.

Para números e dados atuais, preferir fontes oficiais.

---

# 10. FONTES PRIORITÁRIAS

Sempre que possível pesquise primeiro em fontes como:

- Biblioteca Nacional;
- Hemeroteca Digital Brasileira;
- Biblioteca Pública do Estado do Rio Grande do Sul;
- universidades;
- UFRGS;
- UFSM;
- Unipampa;
- FURG;
- UCS;
- PUCRS;
- SciELO;
- Domínio Público;
- Internet Archive quando aplicável;
- Wikisource para obras comprovadamente em domínio público;
- museus;
- memoriais;
- prefeituras;
- arquivos históricos;
- IPHAN;
- Iphae;
- IBGE;
- Embrapa;
- INMET;
- Conab;
- MAPA;
- secretarias estaduais;
- instituições culturais;
- fundações;
- CTGs e entidades tradicionalistas como fontes complementares;
- jornais históricos acessíveis legalmente.

Wikipedia pode ser usada para orientação inicial, mas NÃO deve ser a fonte principal quando houver fonte melhor.

---

# 11. DIREITOS AUTORAIS

Esta regra é obrigatória.

Antes de copiar qualquer texto integral, determine se:

- está em domínio público;
- possui licença permissiva;
- possui autorização explícita para reprodução.

Se não houver certeza:

NÃO copie integralmente.

Produza conteúdo editorial original baseado nas informações encontradas.

Para cada conteúdo pesquisado, mantenha:

- fonte;
- URL;
- autor da fonte quando disponível;
- data de acesso;
- observação de direitos/licença quando relevante.

Evite copiar simplesmente mudando algumas palavras.

Sintetize e escreva conteúdo editorial genuinamente próprio.

---

# 12. IMAGENS

Também faça pesquisa de imagens adequadas.

Prioridade:

1. domínio público;
2. Wikimedia Commons;
3. acervos governamentais;
4. museus;
5. bibliotecas;
6. arquivos históricos;
7. Creative Commons compatível;
8. fotografias institucionais com autorização clara.

Para cada imagem, registrar quando possível:

- título;
- descrição;
- autor/fotógrafo;
- instituição;
- origem;
- URL;
- licença;
- ano;
- local;
- texto alternativo.

NÃO baixe imagens aleatórias do Google Imagens.

O Google pode ajudar a encontrar a fonte, mas a imagem deve vir do acervo/origem identificável.

Não utilizar imagem protegida sem autorização apenas porque está acessível publicamente.

---

# 13. ACERVO FOTOGRÁFICO

Pesquise fotografias históricas e regionais adequadas para formar gradualmente um acervo.

Exemplos:

- cidades antigas;
- estações ferroviárias;
- praças;
- ruas;
- imigração;
- lavouras;
- estâncias;
- tropeiros;
- charqueadas;
- portos;
- barcos;
- festas;
- igrejas;
- escolas;
- comércio;
- paisagens;
- enchentes históricas;
- indústrias;
- serrarias;
- vinhedos;
- colheitas;
- trabalhadores;
- famílias;
- arquitetura.

Quando possível, relacionar fotografias a uma coleção.

Exemplo:

`Porto Alegre antiga`

`Ferrovias do Rio Grande do Sul`

`Imigração italiana`

`Imigração alemã`

`Vida campeira`

`Serra Gaúcha`

`Missões`

`Enchentes históricas`

---

# 14. LOCALIDADES

Quando um conteúdo possuir vínculo geográfico claro, associe corretamente:

- estado;
- município;
- região, caso o modelo já possua;
- coordenadas somente se forem realmente necessárias e houver suporte.

Não crie novamente aquele modelo complexo de "territórios".

Utilize a estrutura de UF/município existente.

---

# 15. QUANTIDADE INICIAL

Não tente colocar milhares de conteúdos de uma vez.

Nesta primeira grande carga editorial, busque qualidade e diversidade.

Como referência inicial, tente alcançar algo próximo de:

- 15 a 25 Histórias;
- 15 a 25 Causos;
- 20 a 40 Anedotas;
- 15 a 25 conteúdos de Poesia;
- 15 a 25 conteúdos de Literatura;
- 15 a 25 conteúdos sobre Música;
- 15 a 25 conteúdos de Tradições;
- 20 a 30 conteúdos de Nossa Terra;
- 30 ou mais fotografias históricas devidamente licenciadas, se houver fontes adequadas.

Isso NÃO é uma obrigação matemática.

Se houver conteúdo excelente, continue.

Se não houver fonte segura, não force quantidade.

---

# 16. DISTRIBUIÇÃO

Não publique 50 textos sobre tradicionalismo e apenas 2 sobre Serra Gaúcha.

Busque equilíbrio entre:

- Pampa;
- Campanha;
- Serra;
- Missões;
- litoral;
- região metropolitana;
- norte;
- centro;
- vales;
- fronteira.

E também entre:

- cultura campeira;
- cultura urbana;
- imigração italiana;
- imigração alemã;
- cultura açoriana;
- cultura indígena;
- comunidades afro-gaúchas;
- outras formações culturais relevantes.

O portal deve representar a diversidade histórica e cultural do estado.

---

# 17. SEO

Para cada publicação criada:

- título natural;
- slug adequado;
- resumo;
- descrição SEO;
- título SEO quando necessário;
- imagem social quando disponível;
- canonical conforme padrão do projeto;
- tags úteis;
- categoria correta;
- autor correto;
- localização quando pertinente.

Não pratique keyword stuffing.

Escreva para pessoas primeiro.

---

# 18. STATUS EDITORIAL

Conteúdos de alta confiança, bem revisados e com fontes adequadas podem ser publicados automaticamente SE o sistema já permitir isso de forma segura.

Quando houver qualquer dúvida sobre:

- autoria;
- direitos;
- data;
- fato histórico;
- fonte;
- imagem;
- licença;

cadastre como:

`rascunho`

ou

`pendente de revisão`

conforme o workflow existente.

NÃO invente informação para conseguir publicar.

---

# 19. CADASTRO AUTOMÁTICO

Depois de entender os models e tabelas existentes, implemente uma forma segura e repetível de importar/cadastrar o conteúdo pesquisado.

Pode utilizar:

- comando Artisan;
- serviço Laravel;
- importadores;
- seeders editoriais;
- JSON intermediário;
- jobs;
- scripts.

Prefira uma arquitetura idempotente.

Executar novamente o importador NÃO deve duplicar publicações.

Utilize identificador/origem/slug para impedir duplicação.

---

# 20. PROVENIÊNCIA

É importante que consigamos posteriormente descobrir de onde veio cada informação.

Se ainda não existir mecanismo adequado, implemente de maneira simples algo semelhante a:

- `source_name`
- `source_url`
- `source_author`
- `source_accessed_at`
- `source_license`
- `source_notes`

Não precisa necessariamente alterar a tabela principal se uma tabela de fontes ou metadados for arquiteturalmente melhor.

Uma publicação poderá ter várias fontes.

---

# 21. VALIDAÇÃO DE LINKS

Vasculhe o portal público depois da carga.

Verifique:

- links quebrados;
- categorias vazias;
- menus levando a páginas sem conteúdo;
- imagens inexistentes;
- slugs incorretos;
- breadcrumbs;
- paginação;
- filtros;
- busca;
- relacionamentos;
- conteúdo duplicado.

Corrija o que for seguro.

---

# 22. EXPERIÊNCIA VISUAL

Observe como os conteúdos aparecem na interface.

Não basta preencher banco.

Verifique se:

- cards ficaram bons;
- títulos não quebram layout;
- imagens possuem proporções adequadas;
- textos ficam legíveis;
- fotografias históricas têm legenda;
- créditos não dominam visualmente a publicação;
- categorias funcionam;
- coleções ficam compreensíveis;
- o portal continua com personalidade e sem aparência de blog genérico.

Não redesenhe tudo sem necessidade.

A interface atual deve ser preservada sempre que estiver funcionando bem.

---

# 23. NÃO INVENTAR CONTEÚDO HISTÓRICO

Você pode escrever textos editoriais próprios.

Porém nunca invente como fato:

- acontecimentos;
- datas;
- personagens;
- citações;
- autores;
- documentos históricos;
- lendas apresentadas como fatos;
- locais;
- estatísticas.

Quando uma história for lenda ou tradição oral, diga claramente.

---

# 24. IA COMO EDITORA, NÃO COMO FONTE

Texto criado por IA não é uma fonte.

Toda informação factual relevante deve vir de fontes pesquisáveis.

A IA pode:

- sintetizar;
- organizar;
- contextualizar;
- explicar;
- reescrever;
- relacionar informações.

Mas deve manter referências externas para os fatos.

---

# 25. RELATÓRIO FINAL

Ao terminar esta etapa, atualize `executed.md` ou o mecanismo de relatório utilizado pelo projeto com:

## Auditoria

- páginas analisadas;
- links analisados;
- áreas vazias encontradas;
- problemas encontrados.

## Conteúdo

Tabela:

| Seção | Antes | Adicionados | Publicados | Rascunhos |
|---|---:|---:|---:|---:|

## Fontes

Liste as principais instituições/fontes utilizadas.

## Imagens

Informar:

- quantidade adicionada;
- origens;
- tipos de licença encontrados;
- imagens recusadas por falta de segurança jurídica.

## Direitos autorais

Informe quais critérios foram utilizados.

## Desenvolvimento

Informe:

- migrations criadas;
- models alterados;
- comandos criados;
- seeders/importadores;
- testes adicionados;
- testes executados.

## Pendências

Liste apenas problemas reais que necessitem decisão humana.

---

# 26. REGRA DE EXECUÇÃO

Não faça somente uma análise e pare esperando autorização.

Você está autorizado nesta tarefa a:

1. auditar;
2. pesquisar;
3. selecionar;
4. preparar;
5. implementar o mecanismo de importação;
6. cadastrar;
7. baixar imagens permitidas;
8. vincular fontes;
9. testar;
10. revisar a apresentação pública;
11. corrigir problemas seguros encontrados.

Faça o máximo possível nesta execução.

Somente interrompa uma operação específica quando houver:

- risco de perda de dados;
- necessidade de credencial inexistente;
- dúvida jurídica concreta sobre um material;
- impedimento técnico externo impossível de contornar.

Nesses casos, pule aquele item e continue nos demais.

---

# RESULTADO ESPERADO

Ao final desta execução, o Desgarrados deve deixar de parecer um portal estruturalmente pronto porém sem acervo e começar a apresentar uma quantidade significativa de conteúdo regional verdadeiro, organizado, pesquisável, ilustrado e culturalmente relevante.

A meta não é simplesmente "encher páginas".

A meta é começar a construir um **acervo digital regional de qualidade**, que possa crescer continuamente ao longo dos próximos anos.

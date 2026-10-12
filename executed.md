# Resultado da execução — DESGARRADOS-NEXT-29

## Comparação

`correio.md` não está versionado no Git e não há `executed.md` anterior no diretório. Assim, não foi possível confirmar uma diferença em relação a uma versão anterior; o arquivo foi tratado como uma nova instrução.

## Implementação

- Adicionado seletor regional global ao cabeçalho público, visível também no mobile, com modal na primeira visita e ação para alterar ou redefinir a escolha.
- A seleção manual usa os municípios e UFs do catálogo `TerritorialUnit`/IBGE existente e salva apenas código IBGE, UF e estado da preferência no armazenamento local.
- Geolocalização só é pedida após clique explícito e reutiliza `/calendario-rural/localizacao`, sem gravar coordenadas.
- Mensagens cobrem permissão negada, GPS indisponível ou demorado e erros de geocodificação. A recusa não bloqueia a navegação.
- Em Minha Região, Calendário Rural e Agenda Regional, a nova escolha é aplicada quando a URL não contém filtros de localidade/abrangência explícitos; os demais parâmetros da URL são preservados.
- Não foi alterado o acervo cultural geral.

## Validação

- `php artisan test`: aprovado — 173 testes, 2 ignorados (175 no total).
- `./vendor/bin/phpstan analyse --debug --no-progress`: aprovado — 0 erros. A execução padrão do PHPStan falhou ao abrir um socket local; a execução em modo `--debug` passou.
- `git diff --check`: aprovado.
- TypeScript e build SSR não executados: este ambiente não tem `node` nem `npm` disponíveis, embora `node_modules` exista.

Nenhum push ou deploy foi executado.

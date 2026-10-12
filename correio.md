# DESGARRADOS-NEXT-29 — Localização regional global

**Projeto:** `apps/desgarrados-next/`

Implementar localização opcional como funcionalidade global do portal público.

## Objetivo

Na primeira visita, exibir um modal de boas-vindas com a pergunta:

**"De que canto tu és?"**

Explicar brevemente que a região permite personalizar informações e serviços.

Oferecer três opções:

1. Usar minha localização.
2. Escolher meu município.
3. Agora não.

## Requisitos

- Solicitar permissão de geolocalização somente após clique explícito.
- Reutilizar a estrutura existente de municípios/UF do IBGE e a preferência regional já implementada.
- Reutilizar o serviço de geocodificação reversa quando configurado, sem inventar provedor ou credenciais.
- Oferecer seleção manual por município e UF, com busca acessível.
- Persistir apenas código IBGE/UF e preferência de exibição no armazenamento local, sem persistir coordenadas.
- Não repetir o modal a cada visita ou navegação; permitir redefinir a escolha nas preferências.
- Exibir localização atual e ação para alterar município no cabeçalho público, inclusive no mobile.
- Compartilhar o contexto regional com Nossa Terra, Calendário Rural e demais páginas que realmente utilizam município, respeitando filtros explícitos da URL.
- Não filtrar ou esconder o acervo cultural geral.
- Tratar permissão negada, geolocalização indisponível, timeout e falha do geocodificador.
- Preservar privacidade, acessibilidade e experiência sem cadastro.
- Não criar duplicação de estados, APIs ou componentes de localização existentes.

## Interface

Manter a identidade visual atual do Desgarrados.

Modal compacto, responsivo, sem aparência artificial ou excesso de elementos. Uso natural de linguagem regional.

Não bloquear a navegação caso o visitante recuse a localização.

## Validação

Testar primeira visita, retorno ao site, troca de município, preferência manual, GPS negado, falhas do provedor, navegação por teclado e funcionamento das ferramentas regionais.

Executar testes Laravel, PHPStan, TypeScript e build SSR.

Registrar o resultado em `executed.md`.

**Não executar push ou deploy nesta tarefa.**
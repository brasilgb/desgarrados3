# Inventário interno de privacidade — NEXT-26.5

**Uso interno para validação pelo responsável.** Este inventário descreve o que a leitura do código e a inspeção local limitada sustentam. Não substitui validação jurídica, contratos ou confirmação da operação de produção.

## Inventário por fluxo

| Fluxo/categoria | Finalidade observada | Base legal a validar pelo controlador | Onde o código indica armazenamento/acesso | Fornecedor/operador e confirmação necessária |
|---|---|---|---|---|
| Contato: nome, e-mail, assunto, mensagem e ciência da política | Receber e responder manifestações; encaminhar mensagem ao endereço configurado | Procedimentos solicitados pelo titular e/ou outra hipótese apropriada; o aceite de ciência não é consentimento genérico | Banco `contact_messages`, administração e eventual transporte de e-mail configurado | Confirmar hospedagem, banco, provedor de e-mail, destinatários, cópias e localização do processamento |
| Contribuição: título, relato, nome/pseudônimo, origem, contato opcional, consentimentos e versão aceita | Análise editorial, comunicação opcional e publicação autorizada | Hipótese adequada para avaliação; execução da autorização/licença e obrigações aplicáveis; validar finalidade por finalidade | Banco, painel administrativo; relatos pendentes ficam privados; termos aceitos têm versão/snapshot | Confirmar hospedagem, operadores com acesso e cópias de e-mail/backup |
| Foto de contribuição e mídia editorial: imagem, nome original, crédito, metadados | Avaliação, curadoria e eventual publicação após aprovação | Avaliar autorização autoral e de imagem separadamente; validar | Disco privado durante avaliação; mídia pode ir ao disco público após ação editorial; registros e referências no banco | Confirmar storage local/remoto, região, EXIF, backups e quem tem acesso |
| Conta administrativa e autenticação: nome, e-mail, senha em hash, papel/estado, 2FA e recuperação | Autenticação, controle de acesso, administração e segurança | Execução do vínculo/legítimo interesse ou obrigação aplicável, a validar | Banco/sessão; rotas administrativas exigem autenticação, verificação e permissão | Confirmar identidade do operador de hospedagem, logs e retenção de sessões |
| Preferências e rascunhos locais | Aparência, navegação e recuperação de rascunho | Necessidade/consentimento conforme natureza e configuração, validar | Cookies de sessão/CSRF e localStorage no dispositivo; localização municipal pode persistir localmente | Código não confirma CDN, proxy, logs de borda ou configuração do browser do usuário |
| Localização opcional: latitude/longitude | Geocodificação reversa após ação e permissão do browser | Pedido explícito de recurso pelo titular; avaliar informação/necessidade | Coordenadas transitam pelo servidor e não são persistidas pela aplicação; código IBGE/UF pode ficar no navegador | URL do geocodificador é configurável; confirmar provedor, região, retenção, contrato e transferência |
| Integrações territoriais/calendário: consultas a IBGE, BrasilAPI, MAPA | Importação administrativa de dados públicos | Avaliar interesse público/execução do serviço e minimização | Requisições iniciadas pelo servidor; não há evidência de envio de formulários nesses pedidos | Confirmar hospedagem e fornecedores, se aplicável |
| Logs, e-mails e backups | Segurança, operação, entrega e recuperação | Definir conforme finalidade e obrigação de retenção | Não comprovado pelo código da aplicação | Responsável por infraestrutura deve confirmar campos, período, acesso, criptografia, região e descarte |

A base legal aplicável, a qualidade de controlador/operador, o enquadramento de pequeno porte e as obrigações do Marco Civil dependem da operação real. Não inferir esses fatos apenas pelo repositório.

## Processo simples de atendimento a titulares

1. **Receber:** direcionar pedidos ao e-mail institucional já usado pelo formulário e/ou à página Contato. Não pedir que a pessoa exponha dados sensíveis no assunto.
2. **Registrar:** na administração existente, registrar data, canal, protocolo interno, categoria do pedido, responsável e prazo de acompanhamento. Se não houver campo apropriado, manter um registro interno de acesso restrito, sem criar portal público paralelo.
3. **Verificar:** confirmar identidade de modo proporcional ao risco e ao pedido; pedir apenas informação adicional necessária e evitar cópia de documento quando uma verificação menos invasiva bastar.
4. **Localizar:** pesquisar sistemas e categorias pertinentes (contato, contribuição, conta, mídia, termos, e-mail e operadores) e identificar limitações, terceiros e cópias fora do controle direto.
5. **Decidir:** responsável pelo tratamento avalia fundamento, exceções, direitos de terceiros e obrigação de conservação. Acionar jurídico/encarregado quando designado ou necessário.
6. **Responder e executar:** responder pelo mesmo canal seguro, explicar providência, recusa fundamentada ou prazo, e registrar alterações feitas, destinatário, data e eventual encaminhamento a operador.
7. **Encerrar:** guardar somente evidência mínima do atendimento pelo prazo que o controlador aprovar. Reabrir se houver pedido complementar.

O controlador deve definir prazos internos e validar prazos legais aplicáveis antes de adotar o processo como procedimento oficial.

## Matriz de conservação e descarte para aprovação

| Registro | Critério proposto para decisão | Ação após prazo aprovado | Evidências e dependências |
|---|---|---|---|
| Mensagens de contato | Encerrar finalidade de resposta; preservar apenas enquanto necessário para histórico, defesa ou obrigação definida | Exclusão ou anonimização administrativa; conferir cópias do e-mail | Tabela tem exclusão administrativa; definir prazo, caixa postal e cópias/exportações |
| Contribuições recusadas/não publicadas e fotos privadas | Encerrar avaliação e eventual recurso; avaliar necessidade de prova de autorização/defesa | Apagar ou anonimizar texto e apagar arquivo privado, preservando apenas registro mínimo justificável | Verificar storage, associações, versões de aceite e cópias de backup |
| Contribuições publicadas | Manter enquanto publicação e autorização permanecerem válidas/necessárias; reavaliar após retirada/pedido | Retirar conteúdo e mídia pública quando devido; revisar cache/cópias controladas; preservar exceção documentada | Verificar publicação, biblioteca, CDN/cache e links externos |
| Termos aceitos/snapshots | Provar versão e contexto de aceites ainda pertinentes; definir período de defesa | Eliminar ao fim do período aprovado, sem reescrever silenciosamente o histórico | Registro imutável no modelo; migração/eliminação futura requer decisão específica |
| Usuários, sessões e recuperação | Manter conta ativa; após encerramento, considerar defesa e obrigações | Desativar/remover identificadores e expirar sessões/tokens conforme plano | Confirmar política de contas inativas, cache, sessões e logs de autenticação |
| Mídia da biblioteca | Enquanto há uso editorial ou obrigação válida | Remover referências e arquivo de todos os discos; confirmar mídia órfã | Disco público/privado, banco, miniaturas, EXIF e cópias |
| Logs de aplicação/servidor e e-mail | Aplicar finalidade, obrigação legal e segurança; Marco Civil depende de enquadramento e requisitos concretos | Expiração pelo provedor; registrar retenções excepcionais | Provedores devem informar conteúdo, retenção, localização e deleção |
| Backups e snapshots | Prazo e ciclo de rotação definidos pelo operador | Expirar por rotação; impedir reintrodução de registros apagados ou reaplicar descarte após restauração | Confirmar política, criptografia, acesso, região, teste de restauração e descarte |

**Não programar exclusão automática** até aprovação dos prazos, escopo, exceções e procedimento de restauração pelo controlador e pelos operadores.

## Segurança e responsabilidades

- **Código observado:** hash de senha, autenticação e autorização administrativa, arquivos de contribuição em disco privado antes da decisão editorial, controle de publicação e acesso à administração.
- **Validar com equipe técnica/hosting:** TLS, criptografia de volume, atualização e hardening, backups, segregação de ambientes, gestão de segredo, firewall/CDN, proteção de logs, alertas, acesso de suporte e plano de incidente.
- **Controlador:** validar finalidades/bases, direitos e retenção, confirmar identidade/canal, avaliar pedidos e incidentes, aprovar comunicações e supervisionar operadores.
- **Administração editorial:** restringir acesso a mensagens e relatos, evitar coleta excessiva, manter estado de revisão, cumprir pedidos encaminhados e escalar incidentes.
- **Operadores:** listar razão social/serviço, finalidade, dados acessíveis, região, suboperadores, retenção e mecanismo de transferência; formalizar instruções e descarte.

## Diferenças entre padrões e conteúdo personalizado

- **Política de Privacidade:** não existe página personalizada de `site_pages` para este slug; a rota serve componente estático. Não foi encontrado padrão/versão salva separado em `portal_settings`. Portanto, nenhuma diferença de conteúdo personalizado pôde ser comparada.
- **Termos de Uso:** mesma situação: página estática, sem versão personalizada encontrada. Não há comparação de texto salvo.
- **Termos de Envio (`termos-de-contribuicao`):** existe página publicada personalizada, 530 caracteres, SHA-256 `8e1781857e5b5c448cd339ce7aa5756068cdab12ee7e77f931a7d7d6ffa3e2ed`. Ela diz que o envio requer material próprio/autorizado, permite avaliação e eventual publicação após revisão, admite ajustes preservando sentido/crédito, esclarece que não há garantia de publicação, separa aprovação editorial da publicação e recomenda não enviar dados pessoais de terceiros nem obras sem autorização. O padrão atual acrescenta licença não exclusiva/gratuita e restrita ao portal, cópias técnicas, exclusão de exploração comercial/redes/campanhas/produtos e adaptações substanciais, consentimento separado para imagem, regra específica de menores, direito de retirada/correção e observância de exceções legais. São diferenças materiais que o administrador deve revisar e aprovar antes de editar a versão personalizada. A execução não a sobrescreveu.

## Acessibilidade prática executada/pendente

- Inspeção de código confirmou rótulos nativos nos campos, estrutura de títulos, links sublinhados e larguras fluidas com limites máximos e breakpoints.
- Corrigidos (nesta execução) vínculos `aria-invalid`/`aria-describedby` e identificadores das mensagens de erro no formulário de contato; contraste de texto auxiliar aumentado de `stone-500` para `stone-600`; botões de ferrugem clara em fundo claro substituídos por ferrugem escura.
- Navegação completa com teclado e leitor de tela ainda requer sessão interativa disponível. Firefox/Orca estão instalados localmente, mas não há WebDriver/controlador de navegador neste ambiente; não foi possível afirmar conclusão de teste de ponta a ponta com leitor.
- Contraste medido por luminância sRGB: `#bb623e` sobre `#fbf8f1` = 4,02:1 e sobre branco = 4,26:1 (abaixo de 4,5:1 para texto normal); `#9c4e31` sobre `#fbf8f1` = 5,58:1 (aprovado para texto normal); `#28271f` sobre `#f5f0e6` = 13,20:1. Outros trechos devem seguir o tema aprovado e a auditoria precisa abranger cada estado interativo visual.

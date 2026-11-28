---
title: Documentação Técnica — Módulo Geral
subtitle: Arquitetura, RBAC, Banco de Dados, Auditoria Global Automática, CRUDs, Mapas Interativos, Crachá CR80 e Tema Escuro
module: Módulo Geral · Guia do Desenvolvedor
system: Sistema Elo — Gerenciamento de Casas de Acolhimento
author: João Felipe
version: 1.0
date: 28/11/2026
toc_heading: Conteúdo da Documentação Técnica
---

# 1. Arquitetura Geral do Módulo Geral

Esta documentação detalha **100% da implementação técnica do Módulo Geral** e dos recursos transversais de governança do **Sistema Elo**: estrutura de arquivos, modelagem relacional das 11 tabelas, sessão isolada por aba (`tabToken`), RBAC dinâmico em JSON, sistema de tema Claro/Escuro persistido por usuário, motor de **auditoria global automática** (cobrindo todos os módulos atuais e futuros), armazenamento privado de mídias/documentos, motor de emissão de crachás CR80 (GD + Dompdf), quadros interativos com drag-and-drop (Escala e Agenda) e relatórios configuráveis.

A aplicação segue arquitetura **PHP 8.1+ server-rendered monolítica com PDO (MySQL/MariaDB)**, sem framework back-end, utilizando **Tailwind CSS (CDN + `assets/js/tailwind-config.js`)**, **Phosphor Icons**, **JavaScript Vanilla modular (`assets/js/app.js` + scripts específicos por página)** e **Dompdf (`^3.1`)** via Composer.

## 1.1 Mapa Completo de Arquivos do Módulo Geral

| Caminho do Arquivo | Camada e Papel | Chave de Permissão (`appRequirePage`) |
| --- | --- | --- |
| `pages/geral/dashboard-geral.php` | Painel executivo unificado com layout dinâmico (`items-start`), KPIs e agenda personalizada | `geral.dashboard-geral` |
| `pages/geral/cadastros/usuarios.php` | CRUD de contas de acesso, política de senha, bloqueio e expiração de sessão | `geral.usuarios` (legada) |
| `pages/geral/cadastros/perfis-acesso.php` | CRUD de perfis RBAC em JSON com matriz por módulo e seleção em lote | `geral.perfis-acesso` (legada) |
| `pages/geral/cadastros/colaboradores.php` | CRUD de equipe/voluntários, ViaCEP, upload/webcam de foto e modal de crachá | `geral.cadastros/colaboradores` |
| `pages/geral/cadastros/cracha-colaborador.php` | Motor de prévia e PDF CR80 (Dompdf + recorte GD `23:26`); oculto na sidebar | `geral.cadastros/colaboradores` |
| `pages/geral/cadastros/documentos-institucionais.php` | CRUD de documentos/certidões, visualizador modal, renovação transacional e Desatualizado | `geral.cadastros/documentos-institucionais` |
| `pages/geral/movimentacoes/escala-plantoes.php` | Mapa semanal/mensal de plantões com AJAX drag-and-drop e cópia de semana | `geral.movimentacoes/escala-plantoes` |
| `pages/geral/movimentacoes/agenda-institucional.php` | Calendário mensal interativo com AJAX drag-and-drop e participantes N:N | `geral.movimentacoes/agenda-institucional` |
| `pages/geral/movimentacoes/livro-plantao.php` | Passagens de turno, ocorrências, mural fixado, autocomplete AJAX e impressão | `geral.movimentacoes/livro-plantao` |
| `pages/geral/movimentacoes/log-auditoria.php` | Consulta global de auditoria, exportação `.log`, retenção e exclusão seletiva | `geral.movimentacoes/log-auditoria` |
| `pages/geral/relatorios/usuarios-por-perfis.php` | Relatório filtrado de usuários agrupados por perfil com exportação PDF | `geral.relatorios/usuarios-por-perfis` |
| `pages/geral/relatorios/matriz-permissoes.php` | Relatório comparativo Telas × Perfis ou detalhado em PDF via Dompdf | `geral.relatorios/matriz-permissoes` |
| `pages/geral/relatorios/colaboradores.php` | Relatório configurável baseado em `relatorio-engine.php` (Dompdf) | `geral.relatorios/colaboradores` |
| `pages/informacoes-instituicao.php` | Manutenção do registro único institucional (`idInformacoes = 1`) com ViaCEP | `instituicao.informacoes` |

### Infraestrutura Transversal e Endpoints Auxiliares (`auth/`, `components/`, `assets/`)

| Arquivo | Responsabilidade Técnica |
| --- | --- |
| `auth/access.php` | Núcleo de segurança: sessão por aba (`tabToken`), RBAC, CSRF, PDO, auditoria explícita e interceptor global automático |
| `auth/login.php` e `auth/logout.php` | Autenticação (`password_verify`), registro de `login` / `login_falha` / `logout` e encerramento seguro da aba |
| `auth/change-password.php` | Endpoint POST (AJAX/modal) para troca da própria senha com validação de política forte |
| `auth/update-theme.php` | Endpoint POST JSON para persistir `tbUsuario.tema` (`claro` ou `escuro`) por usuário logado |
| `auth/colaborador-fotos.php` e `auth/colaborador-foto.php` | Persistência validada (MIME real via `finfo`, até 5 MB) e entrega autenticada de fotos em `uploads/colaboradores/` |
| `auth/documento-institucional-arquivos.php` e `auth/documento-institucional-arquivo.php` | Persistência validada (PDF/JPG/PNG/WebP até 10 MB) e streaming autenticado (`inline` ou `attachment`) em `uploads/documentos-institucionais/` |
| `components/geral/navbar-geral.php` e `sidebar-geral.php` | Barra superior, modal de módulos (`#modulosModal`) e delegação de menu para `components/module-sidebar.php` |
| `components/personal-settings-modal.php` | Carregamento sem flash do `theme-dark.css`, menu da engrenagem (Senha, Instituição, Tema) e modais globais |
| `assets/css/theme-dark.css` | Folha global do Tema Escuro restrita a `@media screen` e `html[data-theme="escuro"]` (preserva impressão e crachás) |
| `assets/js/app.js` | Gerenciamento de `tabToken`, `BroadcastChannel`, paginação global, comboboxes AJAX, ViaCEP e `[data-theme-picker]` |

## 1.2 Convenção de Permissões e Chaves Legadas

O catálogo de telas (`appPageCatalog()` em `auth/access.php`) descobre automaticamente todos os arquivos PHP em `pages/<modulo>/<categoria>/<arquivo>.php`, gerando a chave `<modulo>.<categoria>/<arquivo>`. Apenas duas telas pioneiras do Módulo Geral mantêm chaves legadas em `$legacyPageKeys`:

- `pages/geral/cadastros/usuarios.php` -> `geral.usuarios`
- `pages/geral/cadastros/perfis-acesso.php` -> `geral.perfis-acesso`

A tela `pages/geral/cadastros/cracha-colaborador.php` é marcada como oculta no catálogo da barra lateral e compartilha a permissão `geral.cadastros/colaboradores`. A tela global `pages/informacoes-instituicao.php` utiliza a permissão independente `instituicao.informacoes`, gerenciada na aba **Permissões Gerais** (`permissoes-gerais`) em `perfis-acesso.php`.

---

# 2. Modelagem de Banco de Dados

O Módulo Geral e a camada de governança operam sobre **11 tabelas** (`InnoDB`, `utf8mb4_unicode_ci`) definidas em `db/schema.sql`, com migrations incrementais registradas em `db/migrations/`.

## 2.1 Diagrama de Relacionamentos (ER)

```text
  tbPerfilAcesso (idPerfil, permissoes JSON, administradorGlobal)
         │ 1:N (RESTRICT)
         ▼
  tbUsuario (idUsuario, login, senhaHash, tema ENUM('claro','escuro'), ...)
         ├──► tbLogAuditoria (idUsuario NULL, ON DELETE SET NULL)
         ├──► tbConfiguracaoAuditoria (idConfiguracao = 1, retencaoDias, gravarArquivoLog)
         ├──► tbColaborador (idUsuario UNIQUE NULL, ON DELETE SET NULL)
         │        ├──► tbEscalaPlantao (UNIQUE idColaborador + dataPlantao + turno)
         │        ├──► tbRegistroPlantao (idColaborador NULL + idAcolhido NULL)
         │        └──► tbAgendaEventoParticipante (PK composta idEvento + idColaborador)
         │                     ▲ (ON DELETE CASCADE em idEvento)
         │              tbAgendaEvento (idEvento, dataEvento, categoria, statusEvento)
         └──► tbDocumentoInstitucional (validade, semValidade, diasAlertaAntes, ativo)

  tbInformacoesInstituicao (idInformacoes = 1 — registro único institucional)
```

## 2.2 Resumo das 11 Tabelas do Módulo Geral

| Tabela | Chave Primária | Chaves Únicas / Índices Principais | Papel na Arquitetura |
| --- | --- | --- | --- |
| `tbPerfilAcesso` | `idPerfil` | `UNIQUE (nomePerfil)` | Armazena perfis RBAC com matriz `permissoes` em JSON e flag `administradorGlobal` |
| `tbUsuario` | `idUsuario` | `UNIQUE (email)`, `UNIQUE (login)`, `KEY (idPerfil)` | Contas de acesso, hash de senha, bloqueio, expiração de sessão (`30..180`) e `tema` |
| `tbInformacoesInstituicao` | `idInformacoes` (`TINYINT`) | Registro único `idInformacoes = 1` | Dados cadastrais da instituição e `camposEnderecoViaCep` (JSON) para crachás e recibos |
| `tbLogAuditoria` | `idLog` (`BIGINT`) | `KEY (dataHora)`, `(idUsuario, dataHora)`, `(modulo, acao)` | Trilha centralizada de auditoria de todos os módulos com `detalhesJson` |
| `tbConfiguracaoAuditoria` | `idConfiguracao` (`TINYINT`) | Registro único `idConfiguracao = 1` | Flags `gravarArquivoLog`, `retencaoDias` (`NULL` = nunca) e `ultimaLimpezaEm` |
| `tbColaborador` | `idColaborador` | `UNIQUE (cpf)`, `UNIQUE (idUsuario)`, `KEY (tipoVinculo, ativo)` | Equipe, voluntários, estagiários e terceirizados com foto e vínculo opcional 1:1 com login |
| `tbRegistroPlantao` | `idRegistroPlantao` | `KEY (dataOcorrencia, turno)`, `(statusRegistro, prioridade)`, `(fixado)` | Livro de plantão, mural fixado e rastreamento de resolução (`resolvidoPor`, `resolvidoEm`) |
| `tbEscalaPlantao` | `idEscala` | `UNIQUE (idColaborador, dataPlantao, turno)`, `KEY (dataPlantao, turno)` | Alocações de plantonistas por data, turno e status operacional |
| `tbAgendaEvento` | `idEvento` | `KEY (dataEvento, statusEvento)`, `KEY (categoria)` | Compromissos institucionais (dia inteiro ou faixa horária) |
| `tbAgendaEventoParticipante` | `(idEvento, idColaborador)` | `KEY (idColaborador)` | Associação N:N entre eventos da agenda e membros da equipe |
| `tbDocumentoInstitucional` | `idDocumento` | `KEY (ativo, semValidade, dataValidade)`, `KEY (categoria)` | Documentos/certidões, controle de validade, anexo privado e status `ativo` (`0` = Desatualizado) |

### Integridade Referencial e Auditoria de Registros

Todas as tabelas operacionais mantêm `idUsuarioCriacao` (`NOT NULL`, `RESTRICT`), `idUsuarioAlteracao` (`NULL`, `RESTRICT`), `criadoEm` e `atualizadoEm`. Já `tbLogAuditoria.idUsuario` usa `ON DELETE SET NULL`, preservando `loginInformado`.

---

# 3. Segurança, Sessão por Aba, RBAC e Tema Escuro Global

## 3.1 Autenticação, Sessão Isolada (`tabToken`) e CSRF

- **Cookies de sessão:** configurados em `appStartSession()` (`auth/access.php`) com `httponly => true`, `samesite => 'Lax'` e detecção automática de HTTPS.
- **Isolamento por aba:** cada aba recebe um `tabToken` armazenado em `$_SESSION['tabSessions'][$tabToken]`. Links internos e formulários propagam o token via `appTabUrl()` e `appTabInput()`. No front-end (`assets/js/app.js`), a abertura indevida via `window.opener` força uma nova sessão isolada — por isso **nunca se deve usar `target="_blank"`** em links internos do sistema.
- **Proteção CSRF:** `appCsrfInput()` injeta o token da sessão da aba em todo formulário e `appValidateCsrf()` compara com `hash_equals()`. Falhas de validação retornam HTTP `403`.
- **Escape XSS:** toda saída HTML passa por `appEscape()` (`htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`).

## 3.2 Sistema de Tema Claro e Escuro por Login

A personalização de tema foi desenhada para funcionar em **100% dos módulos** com **zero flash branco** na troca de páginas e **proteção total de impressões**:

1. **Persistência:** coluna `tbUsuario.tema ENUM('claro', 'escuro') NOT NULL DEFAULT 'claro'` (migration `db/migrations/20261128_user_theme.sql`), carregada em toda requisição por `appCurrentUser()`.
2. **Aplicação síncrona sem flash:** `components/personal-settings-modal.php` (incluído em todas as navbars) carrega `assets/css/theme-dark.css` (versionado por `filemtime`) e executa imediatamente `<script>document.documentElement.dataset.theme = 'claro|escuro';</script>` antes da pintura do conteúdo.
3. **Troca instantânea via AJAX:** o modal `#temaSistemaModal` dispara requisições `POST` (com `csrfToken` e `tabToken`) para `auth/update-theme.php` a partir do listener `[data-theme-picker]` em `assets/js/app.js`, aplicando o atributo `data-theme` em `<html>`, persistindo no MySQL e registrando auditoria.
4. **Escopo CSS seguro (`assets/css/theme-dark.css`):** todas as regras estão encapsuladas em `@media screen` sob o seletor `html[data-theme="escuro"]`, com exclusões explícitas (`:not(...)`) para `.badge-card`, `#crachaPrintRoot` e `.print-sheet`, garantindo que pré-visualizações de crachás e folhas impressas (`@media print`) nunca sofram inversão de cores.

---

# 4. Motor de Auditoria Global Automática

O sistema de auditoria combina **registros explícitos detalhados** com um **interceptor global automático no shutdown do PHP**, assegurando cobertura de **todos os módulos atuais (Geral, Financeiro, Gestão de Acolhidos) e de qualquer módulo futuro** (Atendimento, Estoque, etc.) sem depender de chamadas manuais em cada nova página.

## 4.1 Fluxo da Captura Automática (`auth/access.php`)

```text
[Página protegida chama appRequirePage($pageKey)]
        │  salva $GLOBALS['__appCurrentPageKey'] e $GLOBALS['__appCurrentPageUser']
        ▼
[Requisição POST chama appValidateCsrf()]
        │  registra register_shutdown_function('appRegistrarAuditoriaAutomatica')
        ▼
[Lógica da página executa]
        ├── Se a página chamou appRegistrarAuditoria(...) explicitamente:
        │     marca $GLOBALS['__appAuditoriaRegistrada'] = true (evita duplicidade)
        └── Ao encerrar o script, appRegistrarAuditoriaAutomatica() verifica:
              1. Ignora se já auditado, se não for POST, se HTTP >= 400 ou erro fatal
              2. Ignora AJAX somente leitura (buscar*, consultar*, listar*, detalhar*, ...)
              3. Ignora prévias HTML de relatório (reportAction != 'download')
              4. Só audita se houve sucesso confirmado:
                 • Header Location: (padrão PRG)
                 • Header application/pdf ou attachment (exportação/PDF)
                 • Resposta JSON 2xx de mutação (acao != '')
              5. Infere modulo, acao (criar/editar/excluir/exportar), entidade,
                 idRegistro e metadados seguros (descartando senhas e tokens)
              6. Chama appRegistrarAuditoria(...) → MySQL + logs/auditoria-AAAA-MM.log
```

## 4.2 Regras de Classificação Semântica e Segurança

- **Módulo e Entidade:** extraídos de `$pageKey` (`<modulo>.<subchave>`) cruzado com `appPageCatalog()` para obter o nome amigável da tela.
- **Ação semântica:** inferida a partir de `Content-Type` (`exportar`), `$_POST['acao']` e do parâmetro `?resultado=...` do header `Location:` (por exemplo: `criado`/`cadastrado`/`alocado`/`admitido` -> `criar`; `atualizado`/`editado`/`transferido`/`remanejado`/`desligado`/`alterado` -> `editar`; `excluido` -> `excluir`), com fallback inteligente pela presença de chaves `id* > 0` no POST.
- **Higienização de dados sensíveis:** os campos `csrfToken`, `tabToken` e qualquer chave contendo `senha`, `password` ou `token` são rigorosamente descartados antes de compor `detalhesJson`.
- **Descoberta dinâmica de módulos em `log-auditoria.php`:** o filtro e os badges de módulo combinam automaticamente `appPageCatalog()`, `auth`, `instituicao` e `SELECT DISTINCT modulo FROM tbLogAuditoria`. Qualquer módulo adicionado no futuro aparece automaticamente na tela de auditoria.

## 4.3 Espelhamento `.log`, Retenção e Exclusão Seletiva

- **Arquivos `.log` no servidor:** controlados por `tbConfiguracaoAuditoria.gravarArquivoLog` (`appGravarLinhaArquivoLog()`). Grava linhas estruturadas com `FILE_APPEND | LOCK_EX` em `logs/auditoria-AAAA-MM.log`, criando automaticamente `logs/.htaccess` com `Require all denied`. O diretório `/logs/` está listado no `.gitignore`.
- **Retenção automática:** `appExecutarLimpezaRetencaoAuditoria()` executa `DELETE FROM tbLogAuditoria WHERE dataHora < DATE_SUB(NOW(), INTERVAL :dias DAY)` respeitando um intervalo mínimo de 6 horas (`21600` s) entre limpezas automáticas via `ultimaLimpezaEm`, evitando sobrecarga em requisições consecutivas.
- **Ações administrativas em `log-auditoria.php`:** `salvarConfiguracao` (grava `gravarArquivoLog` e `retencaoDias` entre `NULL` ou `7..3650`), `limparAntigos` (executa limpeza imediata forçada) e `apagarSelecionados` (remove em lote IDs selecionados via checkboxes usando `IN (?, ?, ...)`). Todas geram um novo evento de auditoria registrando a operação.

---

# 5. Dashboard Geral e Cadastros (Implementação)

## 5.1 Dashboard Geral (`pages/geral/dashboard-geral.php`)

O dashboard executa consultas otimizadas condicionadas às permissões do usuário (`appUserCanAccessPage()`), evitando vazamento de dados financeiros ou assistenciais para perfis sem permissão:

- **Layout dinâmico unificado:** utiliza uma única grade `<section class="grid items-start gap-4 xl:grid-cols-3">`, mantendo **Mural do Plantão e Pendências** e **Atividade Recente do Sistema** empilhados na coluna esquerda (`xl:col-span-2`), enquanto **Plantonistas de Hoje**, **Próximos Eventos da Agenda**, **Alertas de Documentos**, **Ocupação de Leitos**, **Resumo Financeiro** e **Acesso Rápido** ocupam a coluna direita. Com isso, a seção de Atividade Recente acompanha dinamicamente a altura do Mural sem gerar espaços vazios.
- **Integração personalizada da Agenda:** resolve o `idColaborador` do usuário logado via `SELECT idColaborador FROM tbColaborador WHERE idUsuario = :idUsuario LIMIT 1` e destaca com `EXISTS` os eventos em que ele participa (`tbAgendaEventoParticipante`).

## 5.2 Usuários e Perfis de Acesso

- **`usuarios.php`:** valida unicidade de `login` e `email`, aplica `appPasswordPolicyError()`, gera hash com `password_hash(..., PASSWORD_DEFAULT)`, restringe `tempoExpiracaoSessao` a `30..180` minutos e protege a exclusão contra erros FK (`SQLSTATE 23000` / código `1451`).
- **`perfis-acesso.php`:** organiza as permissões em abas por módulo (utilizando a chave única `permissoes-gerais` para a aba de Permissões Gerais, evitando colisão com `geral`). Os checkboxes de seleção em lote usam o atributo `data-permission-module` com sincronização bidirecional (estado `checked` e `indeterminate`). Impede edição/remoção do perfil com `administradorGlobal = 1`.

## 5.3 Colaboradores, Fotos Privadas e Motor do Crachá CR80

- **Endpoints AJAX em `colaboradores.php`:** `acao=consultarCep` (consulta cURL à API ViaCEP retornando JSON) e `acao=buscarUsuarios` (autocomplete de contas ativas em `tbUsuario` ainda não vinculadas a outro colaborador, respeitando `UNIQUE (idUsuario)`).
- **Armazenamento seguro de fotos (`auth/colaborador-fotos.php` e `auth/colaborador-foto.php`):** valida upload tradicional (`$_FILES`) e captura de webcam (`fotoCameraDataUrl` em Base64) verificando MIME real via `finfo` e limite de **5 MB**, gravando com nome aleatório em `uploads/colaboradores/` (protegido por `.htaccess` com `Require all denied`). A visualização ocorre exclusivamente via `auth/colaborador-foto.php?id=<id>&tabToken=<token>` com sessão autenticada.
- **Motor do Crachá CR80 (`pages/geral/cadastros/cracha-colaborador.php`):** aberto via `<iframe>` no modal amplo `#modalCrachaColaborador` (preservando a sessão da aba), suportando os modos `caseiro` (A4 dobrável frente/verso), `adesivo` (A4 2× frente) e `grafica` (PDF Dompdf em `60 × 92 mm` = `54 × 86 mm` CR80 + `3 mm` de sangria + marcas de corte).
- **Recorte proporcional GD (`23:26`):** como o Dompdf não implementa `object-fit: cover`, a função auxiliar utiliza a extensão **GD** para recortar a foto pelo centro na proporção exata `23:26` antes de convertê-la em Data URI, evitando qualquer distorção de rosto no PDF.

## 5.4 Documentos e Certidões Institucionais

- **Armazenamento seguro (`auth/documento-institucional-arquivos.php` e `auth/documento-institucional-arquivo.php`):** valida PDF, JPEG, PNG e WebP até **10 MB** via `finfo`, armazena em `uploads/documentos-institucionais/` (com `.htaccess` negando acesso direto) e serve com cabeçalho `Content-Disposition: inline` (para o visualizador em modal) ou `attachment` (download).
- **Fluxo de renovação transacional (`documentos-institucionais.php`):** o link `?renovar=<id>#manutencao` pré-carrega os metadados do documento vencido/vencendo em modo de criação e popula `substituirDocumentoId`. Ao salvar dentro de transação PDO (`beginTransaction()` / `commit()`), o sistema insere a nova via, marca o registro antigo como `ativo = 0` (**Desatualizado**), registra `"Substituído por ..."` em `observacoes` e audita ambos os eventos.
- **Ação rápida de 1 clique:** `POST acao=alterarStatus` alterna `ativo` entre `0` (**Desatualizado**, fora dos alertas) e `1` (**Ativo**) diretamente pela listagem.

---

# 6. Movimentações Interativas (Escala, Agenda e Livro)

## 6.1 Escala de Plantões (`pages/geral/movimentacoes/escala-plantoes.php`)

Arquitetura híbrida: o HTML base da semana ou mês é renderizado em PHP e todas as interações rápidas usam endpoints POST JSON protegidos por CSRF na própria página:

| `$_POST['acao']` | Comportamento no Servidor |
| --- | --- |
| `alocar` | Insere novo plantão em `tbEscalaPlantao`; trata erro `1062` (duplicidade de `idColaborador + dataPlantao + turno`) com mensagem amigável |
| `mover` | Atualiza `dataPlantao` e `turno` de um `idEscala` existente (chamado pelo drag-and-drop entre células) |
| `salvar` | Cria ou edita plantão completo (`statusEscala`, `horaInicio`, `horaFim`, `observacoes`) pelo modal |
| `excluir` | Remove o registro `idEscala` selecionado |
| `copiarSemanaAnterior` | Replica em lote os plantões da semana anterior (`DATE_ADD(..., INTERVAL 7 DAY)`) com `INSERT IGNORE`, informando a quantidade copiada |

No servidor, cada carregamento calcula automaticamente os alertas de **turnos descobertos** (turnos sem nenhum registro `Confirmado`) e **conflitos** (colaboradores com 2 ou mais plantões ativos no mesmo dia).

## 6.2 Agenda Institucional (`pages/geral/movimentacoes/agenda-institucional.php`)

Combina grade mensal interativa com sincronização transacional da tabela N:N `tbAgendaEventoParticipante`:

| `$_POST['acao']` | Comportamento no Servidor |
| --- | --- |
| `salvar` | Dentro de transação PDO, cria/atualiza `tbAgendaEvento` e sincroniza participantes (`DELETE` + `INSERT` em `tbAgendaEventoParticipante` apenas para colaboradores válidos) |
| `mover` | Reagenda `dataEvento` via drag-and-drop preservando horários, status e participantes |
| `excluir` | Remove `tbAgendaEvento` (limpeza automática dos participantes via `ON DELETE CASCADE`) |

O front-end mantém os eventos do mês em memória JSON (embutidos de forma segura com `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`), atualizando a pauta lateral do dia instantaneamente ao clicar nas células do calendário e filtrando em tempo real por categoria ou "Meus eventos".

## 6.3 Livro de Plantão (`pages/geral/movimentacoes/livro-plantao.php`)

- **Endpoints AJAX:** `buscarColaboradores` e `buscarAcolhidos` alimentam comboboxes com busca dinâmica.
- **Ciclo de resolução:** ao alterar `statusRegistro` para `Resolvido`, o servidor preenche automaticamente `resolvidoPor = $user['idUsuario']` e `resolvidoEm = NOW()`; se reaberto, limpa ambos os campos.
- **Modo impressão:** suporte a folha de plantão formatada via `@media print` respeitando os filtros ativos.

---

# 7. Relatórios do Módulo Geral

| Arquivo | Motor Utilizado | Características Técnicas |
| --- | --- | --- |
| `usuarios-por-perfis.php` | HTML + Dompdf dedicado (`assets/css/usuarios-por-perfis.css` e `.js`) | Filtros por perfil/status, agrupamento e geração de PDF com cabeçalho institucional |
| `matriz-permissoes.php` | HTML + Dompdf dedicado | Constrói dinamicamente a matriz `appPageCatalog()` × `tbPerfilAcesso.permissoes`, com modos `matriz` (comparativo) e `detalhado` |
| `colaboradores.php` | Motor reutilizável `components/gestao-acolhidos/relatorio-engine.php` | Configuração declarativa de colunas SQL, filtros tipados, agrupamentos com totais, ordenação e exportação Dompdf |

---

# 8. Guia de Manutenção e Extensão

### Como adicionar uma nova tela ou um novo módulo ao Sistema Elo

1. **Crie o arquivo PHP** em `pages/<modulo>/<categoria>/<nome-da-tela>.php`.
2. **Proteja o topo do arquivo** com `require_once ... auth/access.php;` e `appRequirePage('<modulo>.<categoria>/<nome-da-tela>');`.
3. **Adicione CSRF** (`appCsrfInput()` nos formulários e `appValidateCsrf()` antes de processar qualquer POST) e siga o padrão **PRG (`header('Location: ' . appTabUrl(...))`)** após mutações.
4. **Benefícios automáticos imediatos:**
   - A nova tela aparece automaticamente em **Perfis de Acesso** e na **Matriz de Permissões**.
   - O **Menu Lateral** passa a exibi-la respeitando RBAC.
   - O **Tema Claro/Escuro** aplica-se automaticamente via navbar + `personal-settings-modal.php`.
   - O **Motor de Auditoria Global** passa a registrar todas as criações, edições, exclusões e exportações PDF da nova tela automaticamente em `tbLogAuditoria` e `logs/auditoria-AAAA-MM.log`, incluindo o novo módulo no filtro de `log-auditoria.php`.

### Quando usar `appRegistrarAuditoria()` explicitamente

O interceptor automático já cobre todas as mutações padrão. Chame `appRegistrarAuditoria()` explicitamente apenas quando quiser uma **descrição de negócio muito rica** (por exemplo, nome do colaborador + data/turno na Escala ou número de recibo em `recibo-doacao.php`) ou quando a ação ocorrer via **GET** (como emissão de comprovante PDF). Como `appRegistrarAuditoria()` marca `$GLOBALS['__appAuditoriaRegistrada'] = true`, nunca haverá duplicação de registros.

### Checklist antes de publicar alterações no Módulo Geral

- Executar `php -l` em todos os arquivos modificados.
- Se houver alteração de banco, criar o arquivo SQL em `db/migrations/` e refletir a mesma alteração em `db/schema.sql`.
- Testar no **Tema Claro** e no **Tema Escuro**, além de validar a prévia de impressão (`Ctrl+P`) e a geração de PDFs.
- Garantir que nenhum link interno utilize `target="_blank"` (preservação do `tabToken`).
- Nunca versionar arquivos das pastas `logs/` ou `uploads/` (mantidas no `.gitignore`).

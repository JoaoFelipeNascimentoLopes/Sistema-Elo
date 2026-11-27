---
title: Documentação Técnica — Módulo Financeiro
subtitle: Arquitetura, Banco de Dados, CRUDs, Funções, Endpoints AJAX e Fluxos de Código
module: Módulo Financeiro · Guia do Desenvolvedor
system: Sistema Elo — Gerenciamento de Casas de Acolhimento
author: João Felipe
version: 1.0
date: 27/11/2026
toc_heading: Conteúdo da Documentação Técnica
---

# 1. Arquitetura Geral do Módulo Financeiro

Este documento foi escrito para o desenvolvedor dominar **100% da implementação do Módulo Financeiro** no **Sistema Elo**: estrutura de arquivos, modelagem relacional, controle de acesso, segurança de formulários, funções auxiliares em PHP e JavaScript, endpoints AJAX, geração de PDFs e fluxo ponta a ponta dos dados.

O módulo segue uma arquitetura **PHP server-rendered monolítica com PDO (MySQL/MariaDB)**, sem framework externo no back-end, utilizando **Tailwind CSS (via CDN + `tailwind-config.js`)**, **Phosphor Icons**, **JavaScript Vanilla modular em `assets/js/app.js`** (e scripts inline específicos por página quando necessário) e **Dompdf (`vendor/autoload.php`)** para emissão de recibos e relatórios configuráveis em PDF.

## 1.1 Mapa Completo de Arquivos do Módulo

| Caminho do Arquivo | Camada / Papel | Chave de Permissão (`appRequirePage`) |
| --- | --- | --- |
| `pages/financeiro/dashboard-financeiro.php` | Painel gerencial com 6 queries, gráfico donut CSS e modo privacidade | `financeiro.dashboard-financeiro` |
| `pages/financeiro/cadastros/categoria-lancamentos.php` | CRUD de categorias (`Entrada` / `Saída`) | `financeiro.categoria-lancamentos` |
| `pages/financeiro/cadastros/fonte-recurso.php` | CRUD de fontes de recurso | `financeiro.fonte-recurso` |
| `pages/financeiro/cadastros/forma-pagamento.php` | CRUD de formas de pagamento | `financeiro.forma-pagamento` |
| `pages/financeiro/cadastros/fornecedores-prestadores.php` | CRUD PF/PJ com validação de CPF, CNPJ alfanumérico e telefone | `financeiro.cadastros/fornecedores-prestadores` |
| `pages/financeiro/cadastros/doadores-contribuintes.php` | CRUD PF/PJ + endereço + endpoint AJAX ViaCEP | `financeiro.cadastros/doadores-contribuintes` |
| `pages/financeiro/movimentacoes/lancamentos-financeiros.php` | CRUD de lançamentos + AJAX combobox + redirecionamento p/ recibo | `financeiro.movimentacoes/lancamentos-financeiros` |
| `pages/financeiro/movimentacoes/recibo-doacao.php` | Emissor de recibo em PDF via Dompdf | `financeiro.movimentacoes/lancamentos-financeiros` |
| `pages/financeiro/movimentacoes/metas-orcamentarias.php` | CRUD de metas mensais + AJAX detalhamento de competência | `financeiro.movimentacoes/metas-orcamentarias` |
| `pages/financeiro/relatorios/categoria-lancamentos.php` | Relatório configurável (prévia HTML + PDF Dompdf) | `financeiro.relatorios/categoria-lancamentos` |
| `pages/financeiro/relatorios/fontes-recurso.php` | Relatório configurável (prévia HTML + PDF Dompdf) | `financeiro.relatorios/fontes-recurso` |
| `pages/financeiro/relatorios/formas-pagamento.php` | Relatório configurável (prévia HTML + PDF Dompdf) | `financeiro.relatorios/formas-pagamento` |
| `pages/financeiro/relatorios/fornecedores-prestadores.php` | Relatório configurável com formatação SQL de CPF/CNPJ/tel | `financeiro.relatorios/fornecedores-prestadores` |
| `pages/financeiro/relatorios/doadores-contribuintes.php` | Relatório configurável com formatação SQL de CPF/CNPJ/tel/CEP | `financeiro.relatorios/doadores-contribuintes` |
| `pages/financeiro/relatorios/extrato-lancamentos.php` | Demonstrativo gerencial A4 paisagem (`window.print()`) | `financeiro.relatorios/extrato-lancamentos` |
| `pages/financeiro/relatorios/resumo-metas-orcamentarias.php` | Demonstrativo orçamentário A4 retrato (`window.print()`) | `financeiro.relatorios/resumo-metas-orcamentarias` |

### Componentes e Recursos Compartilhados Utilizados

| Arquivo Compartilhado | Função no Módulo Financeiro |
| --- | --- |
| `auth/access.php` | Sessão por aba (`tabToken`), RBAC, CSRF, PDO (`appPdo()`), escape XSS e catálogo de telas |
| `db/schema.sql` | Definição oficial das 7 tabelas financeiras + `tbInformacoesInstituicao` + `tbUsuario` |
| `components/financeiro/navbar-financeiro.php` | Barra superior, modal de troca de módulos (`#modulosModal`), contador de sessão e logout |
| `components/financeiro/sidebar-financeiro.php` | Define `$sidebarModuleKey = 'financeiro'` e delega para `components/module-sidebar.php` |
| `components/module-sidebar.php` | Monta automaticamente os grupos `Cadastros`, `Movimentações` e `Relatórios` conforme permissões |
| `components/tabs-pesquisa-manutencao.php` | Renderiza as abas `Pesquisa` e `Manutenção` nas telas de CRUD |
| `components/toast.php` | Exibe feedback de sucesso ou erro (`$toastSucesso` / `$toastErro`) |
| `assets/js/app.js` | Máscara monetária, validação/máscara PF/PJ, ViaCEP, paginação global de tabelas, abas e `tabToken` |
| `assets/css/usuarios-por-perfis.css` e `assets/js/usuarios-por-perfis.js` | Interface dinâmica de filtros, agrupamentos e prévia dos 5 relatórios configuráveis em PDF |

## 1.2 Ciclo de Vida de uma Requisição (Padrão PRG + CSRF + TabToken)

Todas as páginas de cadastro e movimentação do módulo seguem exatamente o mesmo fluxo de execução no servidor:

```text
[Requisição HTTP (GET ou POST)]
       │
       ▼
1. require_once auth/access.php + appRequirePage('<chave>')
   ├── Valida sessão isolada da aba ($_SESSION['tabSessions'][$tabToken])
   ├── Atualiza último acesso / checa timeout de inatividade
   └── Verifica se o perfil do usuário possui a chave da página (senão 403 / redirect)
       │
       ▼
2. Conexão PDO via $pdo = appPdo() e inicialização de closures de validação
       │
       ├── Se POST AJAX (ex.: buscarReferencias, consultarCep, detalharCompetencia):
       │     appValidateCsrf() → consulta SQL/cURL → header JSON → echo json_encode() → exit
       │
       ├── Se POST de mutação (acao = salvar | excluir):
       │     appValidateCsrf() → valida entradas → INSERT/UPDATE/DELETE
       │       ├── Sucesso: redirect PRG via appTabUrl('...?resultado=...') → exit
       │       └── Erro/PDO (1062/1451): mantém form + $abaInicial = 'manutencao'
       │
       └── Se GET:
             ├── ?editar=<id>: carrega registro do banco e força $abaInicial = 'manutencao'
             ├── ?resultado=...: popula $toastSucesso (feedback pós-redirect)
             └── Consulta listagem principal + renderiza HTML com appEscape()
```

> **Por que o padrão PRG (Post/Redirect/Get) é obrigatório?** Após qualquer `INSERT`, `UPDATE` ou `DELETE`, o servidor responde com um redirecionamento HTTP `Location` contendo `resultado=...` e `tabToken`. Isso garante que pressionar `F5` no navegador nunca reenvie o formulário nem duplique lançamentos financeiros.

## 1.3 Controle de Acesso e Chaves Legadas (`auth/access.php`)

O catálogo de páginas (`appPageCatalog()`) descobre automaticamente os arquivos PHP dentro de `pages/financeiro/`. Porém, três cadastros pioneiros possuem **chaves legadas** mapeadas em `$legacyPageKeys` dentro de `auth/access.php` para preservar permissões já gravadas na tabela `tbPerfilPagina`:

- `pages/financeiro/cadastros/categoria-lancamentos.php` -> `financeiro.categoria-lancamentos`
- `pages/financeiro/cadastros/fonte-recurso.php` -> `financeiro.fonte-recurso`
- `pages/financeiro/cadastros/forma-pagamento.php` -> `financeiro.forma-pagamento`

Todas as demais telas seguem a convenção automática `<modulo>.<subpasta>/<arquivo-sem-php>`, como `financeiro.movimentacoes/lancamentos-financeiros` e `financeiro.relatorios/extrato-lancamentos`. O arquivo `recibo-doacao.php` **não aparece no menu lateral** e compartilha explicitamente a permissão `financeiro.movimentacoes/lancamentos-financeiros`.

---

# 2. Modelagem de Banco de Dados

O Módulo Financeiro opera sobre **7 tabelas próprias** (engine `InnoDB`, charset `utf8mb4_unicode_ci`) definidas em `db/schema.sql`, além de depender de `tbUsuario` (auditoria em todas as tabelas) e `tbInformacoesInstituicao` (cabeçalho do recibo de doação).

## 2.1 Diagrama de Relacionamentos (ER)

```text
  tbUsuario (idUsuario) ◄─────── [FK idUsuarioCriacao / idUsuarioAlteracao em TODAS as 7 tabelas]

  tbCategoriaLancamentos (idCategoria) ◄─── NOT NULL ───┐
  tbFonteRecurso (idFonte)             ◄─── NULL ───────┤
  tbFormaPagamento (idFormaPagamento)  ◄─── NULL ───────┼── tbLancamentoFinanceiro (idLancamento)
  tbFornecedorPrestador (idFornecedor) ◄─── NULL ───────┤         │
  tbDoadorContribuinte (idDoador)      ◄─── NULL ───────┘         │ (usado quando Entrada + Efetivado
                                                                  │  + idDoadorContribuinte IS NOT NULL)
  tbMetaOrcamentaria (idMetaOrcamentaria)                         ▼
     └── UNIQUE (ano, mes) ── comparada via SQL com          recibo-doacao.php
         SUM(tbLancamentoFinanceiro.valorLancamento)              │
         onde naturezaCategoria = 'Saída'                         ▼
                                                      tbInformacoesInstituicao (idInformacoes = 1)
```

## 2.2 Detalhamento das Tabelas Financeiras

| Tabela | Chave Primária | Colunas de Domínio | Restrições (`UNIQUE` / `CHECK` / `ENUM`) |
| --- | --- | --- | --- |
| `tbCategoriaLancamentos` | `idCategoria` | `nomeCategoria` (100), `naturezaCategoria`, `descricaoCategoria` (255), `statusCategoria` | `ENUM('Entrada','Saída')`; `UNIQUE (nomeCategoria, naturezaCategoria)` |
| `tbFonteRecurso` | `idFonte` | `nomeFonte` (100), `descricaoFonte` (255), `statusFonte` | `UNIQUE (nomeFonte)` |
| `tbFormaPagamento` | `idFormaPagamento` | `nomeFormaPagamento` (100), `descricaoFormaPagamento` (255), `statusFormaPagamento` | `UNIQUE (nomeFormaPagamento)` |
| `tbFornecedorPrestador` | `idFornecedorPrestador` | `tipoPessoa`, `documento` (14), `nomeRazaoSocial` (150), `nomeFantasia` (150), `nomeContato` (150), `email` (190), `telefone` (20), `observacoes` (255), `statusFornecedor` | `ENUM('fisica','juridica')`; `UNIQUE (documento)` |
| `tbDoadorContribuinte` | `idDoadorContribuinte` | Mesmos campos de identificação (sem `nomeContato`) + `cep` (8), `logradouro` (150), `numero` (20), `complemento` (100), `bairro` (100), `cidade` (100), `uf` (`CHAR(2)`), `observacoes`, `statusDoador` | `ENUM('fisica','juridica')`; `UNIQUE (documento)` |
| `tbLancamentoFinanceiro` | `idLancamento` | `dataLancamento` (`DATE`), `descricaoLancamento` (255), `valorLancamento` (`DECIMAL(14,2) UNSIGNED`), `statusLancamento`, 5 FKs (`idCategoria`, `idFonte`, `idFormaPagamento`, `idFornecedorPrestador`, `idDoadorContribuinte`), `observacoes` (255) | `CHECK (valorLancamento > 0)`; `ENUM('Pendente','Efetivado') DEFAULT 'Pendente'` |
| `tbMetaOrcamentaria` | `idMetaOrcamentaria` | `ano` (`SMALLINT UNSIGNED`), `mes` (`TINYINT UNSIGNED`), `valorOrcado` (`DECIMAL(14,2) UNSIGNED`) | `CHECK (mes BETWEEN 1 AND 12)`; `CHECK (valorOrcado >= 0)`; `UNIQUE (ano, mes)` |

### Índices de Performance e Política de Integridade

- **Índices compostos em `tbLancamentoFinanceiro`:**
  - `idx_lancamento_data_status (dataLancamento, statusLancamento)` — acelera o Dashboard, o Extrato de Lançamentos e o cálculo de saldo efetivado.
  - `idx_lancamento_categoria_data (idCategoria, dataLancamento)` — acelera o gráfico de despesas por categoria e o detalhamento das Metas Orçamentárias.
- **Política de Chaves Estrangeiras:** Todas as FKs de `tbLancamentoFinanceiro` utilizam `ON UPDATE CASCADE ON DELETE RESTRICT`. Nenhum cadastro (categoria, fonte, forma de pagamento, fornecedor ou doador) pode ser apagado fisicamente se já existir ao menos um lançamento vinculado.
- **Auditoria embutida em todas as 7 tabelas:** `idUsuarioCriacao` (`NOT NULL`, FK para `tbUsuario`), `idUsuarioAlteracao` (`NULL`, FK para `tbUsuario`), `criadoEm` (`DATETIME DEFAULT CURRENT_TIMESTAMP`) e `atualizadoEm` (`DATETIME NULL ON UPDATE CURRENT_TIMESTAMP`).

## 2.3 Tratamento Padronizado de Exceções PDO

Em todos os CRUDs do módulo, blocos `try/catch (PDOException $e)` inspecionam o código numérico nativo do MySQL via `$e->errorInfo[1] ?? null`:

- **Código `1062` (Duplicate Entry):** Dispara mensagem de negócio amigável sem expor SQL (ex.: documento já cadastrado, categoria duplicada para a mesma natureza, ou meta já existente para aquele `ano/mes`).
- **Código `1451` (Cannot delete or update a parent row):** Capturado nas ações `acao=excluir`, orientando o usuário a **inativar** o registro em vez de excluí-lo quando já há lançamentos associados.

---

# 3. Infraestrutura Compartilhada (Auth e Front-end JS)

Entender as funções globais de `auth/access.php` e os comportamentos automáticos de `assets/js/app.js` é essencial, pois diversas validações, máscaras e navegações do Módulo Financeiro acontecem de forma declarativa por atributos `data-*`.

## 3.1 Funções de `auth/access.php` Utilizadas no Módulo

| Função | Assinatura / Retorno | Papel Técnico |
| --- | --- | --- |
| `appRequirePage(string $pageKey)` | `array` (usuário logado) | Valida autenticação, timeout da aba e permissão RBAC; devolve dados do usuário (`idUsuario`, `nomeCompleto`, `perfil`). |
| `appPdo()` | `PDO` | Retorna a instância singleton de conexão PDO configurada com `ERRMODE_EXCEPTION`. |
| `appValidateCsrf()` | `void` (encerra com 403 se inválido) | Compara `$_POST['csrfToken']` com o token da sessão da aba via `hash_equals`. Obrigatória em todo `POST` (inclusive AJAX). |
| `appCsrfToken()` | `string` | Gera/retorna o token CSRF para campos `<input type="hidden" name="csrfToken">` e cabeçalhos/payloads `fetch`. |
| `appCurrentTabToken()` | `?string` | Lê o token único da aba atual (`$_GET['tabToken']` ou `$_POST['tabToken']`) que isola múltiplas sessões no mesmo navegador. |
| `appTabUrl(string $path)` | `string` | Anexa `?tabToken=...` (ou `&tabToken=...`) em links e redirects PRG, evitando perda de sessão ao navegar. |
| `appEscape(?string $value)` | `string` | Wrapper seguro de `htmlspecialchars` (`ENT_QUOTES`, `ENT_SUBSTITUTE`, `UTF-8`) contra XSS. |
| `appBaseUrl()` | `string` | Calcula a URL base do projeto para carregar assets (`logo-elo.png`, `tailwind-config.js`, `app.js`). |
| `appJavascriptVersion()` | `string` | Retorna `filemtime` de `assets/js/app.js` para cache-busting automático no navegador. |

## 3.2 Comportamentos Globais de `assets/js/app.js` no Financeiro

O arquivo `assets/js/app.js` inicializa automaticamente os recursos abaixo assim que o DOM carrega:

1. **Propagação de `tabToken` e Temporizador de Sessão:**
   - Injeta o `tabToken` em links internos e formulários sem token explícito, preservando sessões simultâneas em abas diferentes.
   - Atualiza a cada segundo o elemento `[data-session-countdown]` na `navbar-financeiro.php` e dispara logout automático ao atingir zero.
2. **Controle de Abas (`[data-page-tabs]`):**
   - Gerencia os botões `[data-tab-target="pesquisa" | "manutencao"]` e painéis `[data-tab-panel]`.
   - Lê o atributo `data-initial-tab` (definido pelo PHP via `$abaInicial`) para abrir diretamente em `manutencao` durante edições (`?editar=ID`) ou quando um `POST` retorna erro de validação.
3. **Filtro e Paginação Global de Tabelas (`[data-filter-input]`):**
   - Vincula o campo de busca ao `<tbody>` correspondente (`listaCategorias`, `listaFontes`, `listaFormasPagamento`, `listaFornecedores`, `listaDoadores`, `listaLancamentos`).
   - Filtra linhas client-side sem acentuação/case-sensitivity e injeta automaticamente controles de paginação com seletor de itens por página (`10`, `25`, `50` ou `100`, padrão `10`).
4. **Máscara Monetária Centavo a Centavo (`[data-money-input]`):**
   - Usada nos campos de valor de `lancamentos-financeiros.php` e `metas-orcamentarias.php`.
   - Intercepta `beforeinput` e `paste`, mantém um buffer de dígitos (até 14 dígitos = 12 inteiros + 2 decimais) e formata em tempo real como `1.234,56`, empurrando dígitos da direita para a esquerda como em maquininhas bancárias.
5. **Motor de Contatos Financeiros (`form[data-finance-contact-form]`):**
   - Atua simultaneamente em `fornecedores-prestadores.php` e `doadores-contribuintes.php`.
   - **Alternância PF / PJ:** observa o select `tipoPessoa`, oculta/exibe blocos `[data-finance-legal-only]` (Nome Fantasia e Pessoa de Contato), troca labels dinâmicos (`[data-finance-person-name-label]` e `[data-finance-document-label]`) e limpa valores ocultos quando volta para `fisica`.
   - **Máscaras com preservação de cursor (`applyMask`):** formata CPF (`000.000.000-00`), **CNPJ alfanumérico** (`AA.AAA.AAA/AAAA-00`, já preparado para o novo padrão da Receita Federal), telefone (`(00) 0000-0000` ou `(00) 00000-0000`) e CEP (`00000-000`).
   - **Validação HTML5 customizada (`setCustomValidity`):** executa o mesmo algoritmo de módulo 11 (CPF e CNPJ alfanumérico usando `charCodeAt(i) - 48`), lista oficial de DDDs brasileiros e regras de telefone fixo/celular antes mesmo de enviar o `POST`.
   - **Integração ViaCEP com debounce (`450ms` + `AbortController`):** em `doadores-contribuintes.php`, ao completar 8 dígitos no campo `cep`, envia `POST` com `acao=consultarCep` + `csrfToken`, preenche `logradouro`, `bairro`, `cidade` e `uf`, e marca como `readOnly` os campos preenchidos automaticamente (destravando apenas os que vierem vazios do ViaCEP, como em CEPs únicos de cidade).
6. **Padronização Visual e Confirmações:**
   - `applyUiTitleCase()` normaliza capitalização de rótulos da interface.
   - Intercepta cliques/submits em elementos com `data-confirm="..."` (botões de exclusão) antes de enviar o formulário.

---

# 4. Dashboard Financeiro

O arquivo `pages/financeiro/dashboard-financeiro.php` (`appRequirePage('financeiro.dashboard-financeiro')`) é somente leitura e executa **6 consultas SQL independentes** para compor os cartões de KPIs, o comparativo semestral, o gráfico de rosca por categoria, o termômetro de meta orçamentária e o feed de movimentações recentes.

## 4.1 As 6 Consultas SQL do Dashboard

| # | Objetivo | Tabelas Envolvidas | Lógica SQL Principal |
| --- | --- | --- | --- |
| 1 | Resumo do mês atual (`$resumoMes`) | `tbLancamentoFinanceiro` + `tbCategoriaLancamentos` | `GROUP BY c.naturezaCategoria, l.statusLancamento` filtrando `l.dataLancamento >= :inicioMes AND l.dataLancamento < :proximoMes`. Preenche entradas efetivadas/pendentes e saídas efetivadas/pendentes. |
| 2 | Saldo acumulado efetivado (`$saldoAcumulado`) | `tbLancamentoFinanceiro` + `tbCategoriaLancamentos` | `SUM(CASE WHEN c.naturezaCategoria = 'Entrada' THEN l.valorLancamento ELSE -l.valorLancamento END)` com `WHERE l.statusLancamento = 'Efetivado'` (histórico completo sem recorte de data). |
| 3 | Histórico dos últimos 6 meses (`$historicoMeses`) | `tbLancamentoFinanceiro` + `tbCategoriaLancamentos` | `DATE_FORMAT(l.dataLancamento, '%Y-%m') AS competencia`, agrupado por competência e natureza para `statusLancamento = 'Efetivado'` desde 5 meses atrás até o fim do mês atual. |
| 4 | Despesas do mês por categoria (`$gastosPorCategoria`) | `tbLancamentoFinanceiro` + `tbCategoriaLancamentos` | `WHERE c.naturezaCategoria = 'Saída' AND l.statusLancamento = 'Efetivado'` no mês corrente, agrupado por categoria, ordenado por `total DESC`. |
| 5 | Meta orçamentária do mês atual (`$metaMes`) | `tbMetaOrcamentaria` | `SELECT valorOrcado FROM tbMetaOrcamentaria WHERE ano = :ano AND mes = :mes`. Se retornar `false`, exibe alerta orientativo informando que o orçamento é opcional. |
| 6 | Últimos 7 lançamentos (`$ultimosLancamentos`) | `tbLancamentoFinanceiro` + `tbCategoriaLancamentos` | `ORDER BY l.dataLancamento DESC, l.idLancamento DESC LIMIT 7` para a tabela de atividade recente. |

## 4.2 Gráfico Donut em CSS Puro (`conic-gradient`) e Meta Mensal

Para evitar dependências pesadas de bibliotecas JS de gráficos, o dashboard calcula no servidor a participação percentual de cada categoria de saída efetivada e monta uma string CSS `conic-gradient(...)`:

```php
// Cada fatia recebe cor fixa do array $coresGrafico (rotação via módulo %)
// Acumula $inicioGrau -> $fimGrau proporcional a ($totalCategoria / $totalSaidasEfetivadas) * 100
$fatiasGrafico[] = sprintf('%s %.2F%% %.2F%%', $cor, $inicio, $fim);
$estiloDonut = 'conic-gradient(' . implode(', ', $fatiasGrafico) . ')';
```

No gráfico de barras dos 6 meses, o PHP calcula `$maiorValorHistorico = max(...)` e define a altura proporcional de cada barra (`.financial-bar`) via `style="height: ...%"`. Já no cartão de meta orçamentária, calcula `$percentualMeta = ($saidasEfetivadas / $valorOrcado) * 100`, limitando a barra visual a `100%` (`min(100, $percentualMeta)`) e trocando a cor para alerta vermelho quando ultrapassa o teto orçado.

## 4.3 Modo Privacidade (Script Inline `[data-values-toggle]`)

No rodapé do `dashboard-financeiro.php`, um script inline controla o botão "Ocultar valores / Mostrar valores":

- Alterna o atributo `data-values-hidden="true|false"` no container principal.
- Percorre todos os elementos `.financial-value`, guarda o texto original em `dataset.originalValue` na primeira vez e substitui o conteúdo visível por `'••••••'`.
- Via regras CSS associadas a `[data-values-hidden="true"]`, também esconde as alturas das barras `.financial-bar` e substitui o preenchimento do `.finance-donut` por um anel neutro cinza, protegendo os números em telas compartilhadas.

---

# 5. CRUDs de Cadastros

O diretório `pages/financeiro/cadastros/` concentra 5 arquivos divididos em dois níveis de complexidade: **3 cadastros auxiliares simples** e **2 cadastros de pessoas (PF/PJ)** com validação documental avançada.

## 5.1 Cadastros Auxiliares Simples

Os arquivos `categoria-lancamentos.php`, `fonte-recurso.php` e `forma-pagamento.php` compartilham a mesma estrutura de código:

| Arquivo | Tabela | Campos Editáveis | Restrição `UNIQUE` (`PDO 1062`) | ID do `tbody` filtrado |
| --- | --- | --- | --- | --- |
| `categoria-lancamentos.php` | `tbCategoriaLancamentos` | `nomeCategoria`, `naturezaCategoria` (`Entrada`/`Saída`), `descricaoCategoria`, `statusCategoria` | `(nomeCategoria, naturezaCategoria)` | `listaCategorias` |
| `fonte-recurso.php` | `tbFonteRecurso` | `nomeFonte`, `descricaoFonte`, `statusFonte` | `nomeFonte` | `listaFontes` |
| `forma-pagamento.php` | `tbFormaPagamento` | `nomeFormaPagamento`, `descricaoFormaPagamento`, `statusFormaPagamento` | `nomeFormaPagamento` | `listaFormasPagamento` |

### Fluxo Interno Comum dos 3 Arquivos

1. **Ação `POST` `acao=excluir`:** valida `csrfToken` e `id` inteiro positivo; executa `DELETE FROM ... WHERE id = :id`. Se `PDOException` retornar `1451`, define `$toastErro` informando que existem lançamentos financeiros vinculados e recomenda inativação.
2. **Ação `POST` `acao=salvar`:** valida `csrfToken`, faz `trim()` nos campos, checa obrigatoriedade e limites de tamanho (`mb_strlen` <= 100 / 255) e valida `status` em `['0', '1']`. Em `categoria-lancamentos.php`, valida também `naturezaCategoria` em `['Entrada', 'Saída']`.
   - Se `id` estiver vazio -> `INSERT` registrando `idUsuarioCriacao = $usuarioAtual['idUsuario']` e redireciona com `?resultado=criado`.
   - Se `id` for positivo -> `UPDATE` registrando `idUsuarioAlteracao = $usuarioAtual['idUsuario']` e redireciona com `?resultado=atualizado`.
   - Se houver erro de validação ou `1062` -> popula o array de edição (`$categoriaEdicao`, `$fonteEdicao` ou `$formaEdicao`) com o que o usuário digitou e define `$abaInicial = 'manutencao'`.
3. **Ação `GET` `?editar=<id>`:** busca o registro com `LEFT JOIN tbUsuario` para exibir metadados de auditoria (`$criadoPor`, `$alteradoPor`, `criadoEm`, `atualizadoEm`) no rodapé do formulário e ativa `$abaInicial = 'manutencao'`.
4. **Listagem (`Pesquisa`):** carrega todos os registros ordenados alfabeticamente e delega a busca instantânea + paginação (`10/25/50/100`) para `assets/js/app.js`.

## 5.2 Fornecedores e Prestadores (`fornecedores-prestadores.php`)

Gerencia a tabela `tbFornecedorPrestador` (contrapartes de lançamentos de **Saída**). Além do fluxo padrão de `salvar`, `excluir` e `?editar`, declara **4 closures PHP** para validação e formatação no servidor (espelhando o front-end de `assets/js/app.js`, garantindo integridade mesmo se o JS for desabilitado):

| Closure PHP | Entrada / Retorno | Algoritmo Implementado |
| --- | --- | --- |
| `$validarDocumento` | `(string $tipoPessoa, string $documento): bool` | Para `fisica`: exige 11 dígitos, rejeita sequências repetidas (`000...` a `999...`) e valida os 2 dígitos verificadores (módulo 11). Para `juridica`: valida **CNPJ alfanumérico** via regex `/^[A-Z0-9]{12}\d{2}$/`, converte cada caractere com `(ord($char) - 48)` e aplica pesos `[5,4,3,2,9,8,7,6,5,4,3,2]` e `[6,5,4,3,2,9,8,7,6,5,4,3,2]` (módulo 11). |
| `$formatarDocumento` | `(string $tipoPessoa, string $documento): string` | Aplica máscara `000.000.000-00` (11 chars) ou `AA.AAA.AAA/AAAA-00` (14 chars) para exibição na tabela e no formulário. |
| `$validarTelefone` | `(string $telefone): bool` | Aceita vazio (opcional) ou 10/11 dígitos numéricos. Verifica se o DDD (2 primeiros dígitos) pertence à lista oficial de DDDs válidos da Anatel. Se 11 dígitos, exige 3º dígito `9` (celular); se 10 dígitos, exige 3º dígito entre `2` e `5` (fixo). Rejeita todos os dígitos iguais após o DDD. |
| `$formatarTelefone` | `(?string $telefone): string` | Formata 11 dígitos como `(XX) XXXXX-XXXX` e 10 dígitos como `(XX) XXXX-XXXX`. |

### Regras Específicas de Negócio

- **Normalização antes de gravar:** `documento` é sanitizado para maiúsculas sem pontuação (`preg_replace('/[^A-Z0-9]/', '', strtoupper(...))`), garantindo unicidade real na coluna `UNIQUE (documento)` mesmo se o usuário digitar com ou sem máscara. `telefone` grava apenas dígitos.
- **Limpeza de campos PJ em PF:** se `$tipoPessoa === 'fisica'`, o back-end força `$nomeFantasia = null` e `$nomeContato = null`, impedindo lixo residual quando um registro é convertido de PJ para PF.
- **Validação de e-mail:** quando preenchido, passa por `filter_var($email, FILTER_VALIDATE_EMAIL)` e limite de 190 caracteres.

## 5.3 Doadores e Contribuintes (`doadores-contribuintes.php`)

Gerencia a tabela `tbDoadorContribuinte` (contrapartes de lançamentos de **Entrada** e destinatários do **Recibo de Doação**). Reaproveita exatamente as mesmas 4 closures (`$validarDocumento`, `$formatarDocumento`, `$validarTelefone`, `$formatarTelefone`), substitui `nomeContato` pelo conjunto completo de endereço (`cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `uf` validada contra as 27 siglas oficiais do Brasil) e adiciona **uma 5ª closure + endpoint AJAX server-side**:

### Closure `$consultarCep` e Endpoint `POST acao=consultarCep`

```text
Front-end (app.js, debounce 450ms)
   ──► POST doadores-contribuintes.php (csrfToken + acao=consultarCep + cep=8 dígitos)
          │
          ▼
   Back-end PHP ($consultarCep)
     1. appValidateCsrf()
     2. Sanitiza CEP para 8 dígitos (rejeita se strlen !== 8 com HTTP 422)
     3. Executa cURL server-side para https://viacep.com.br/ws/{cep}/json/
        - CURLOPT_SSL_VERIFYPEER = true, CURLOPT_SSL_VERIFYHOST = 2
        - CURLOPT_CONNECTTIMEOUT = 5, CURLOPT_TIMEOUT = 10
     4. Valida HTTP 200 e verifica se JSON não possui "erro": true
     5. Retorna HTTP 200 JSON: { logradouro, bairro, cidade, uf }
```

> **Por que a consulta ao ViaCEP passa pelo PHP em vez de chamar diretamente do navegador?** Além de proteger a requisição com `csrfToken` e sessão autenticada, centralizar o cURL no servidor garante timeouts controlados, padronização do contrato JSON retornado ao front-end e tratamento uniforme de falhas de rede (HTTP `404` quando CEP não existe, `502` quando o serviço externo está indisponível).

---

# 6. Movimentações Financeiras e Emissão de Recibos

O diretório `pages/financeiro/movimentacoes/` concentra o núcleo transacional do módulo: `lancamentos-financeiros.php`, `recibo-doacao.php` e `metas-orcamentarias.php`.

## 6.1 Lançamentos Financeiros (`lancamentos-financeiros.php`)

É a tela mais rica do módulo, conectando todas as 5 tabelas de cadastro em `tbLancamentoFinanceiro`.

### Closures Auxiliares PHP

- **`$validarData(mixed $value): bool`** — usa `DateTimeImmutable::createFromFormat('!Y-m-d', $value)` e confere se `$date->format('Y-m-d') === $value`, bloqueando datas impossíveis como `2026-02-31`.
- **`$converterValor(mixed $value): ?string`** — valida o formato monetário brasileiro pela regex `/^(?:\d{1,12}|\d{1,3}(?:\.\d{3}){1,3})(?:,\d{1,2})?$/`, remove separadores de milhar (`.`), troca vírgula (`,`) por ponto (`.`), formata com 2 casas decimais e rejeita valores menores ou iguais a zero (`bccomp` / comparação decimal > 0), devolvendo a string pronta para `DECIMAL(14,2)`.
- **`$valorDigitado(mixed $value): string`** — converte valores vindos do banco (`1250.50`) para o formato PT-BR (`1.250,50`) ao popular o campo `[data-money-input]` em modo de edição.

### Endpoint AJAX `POST acao=buscarReferencias`

Os 4 seletores do formulário (`Categoria`, `Fonte de Recurso`, `Forma de Pagamento` e `Fornecedor/Doador`) não carregam milhares de registros no HTML inicial: eles usam um **combobox pesquisável assíncrono** que envia `POST` para a própria página com `csrfToken`, `acao=buscarReferencias`, `tipo`, `busca` (até 100 caracteres) e `natureza`:

| Valor de `tipo` | Tabela Consultada | Regra de Filtro e Ordenação | Formato do `value` Retornado |
| --- | --- | --- | --- |
| `categoria` | `tbCategoriaLancamentos` | Somente `statusCategoria = 1`; busca por `nomeCategoria` ou prefixo numérico de `idCategoria` (`LIMIT 50`). Inclui atributo extra `natureza`. | `<idCategoria>` |
| `fonte` | `tbFonteRecurso` | Somente `statusFonte = 1`; busca por `nomeFonte` ou `idFonte` (`LIMIT 50`). | `<idFonte>` |
| `formaPagamento` | `tbFormaPagamento` | Somente `statusFormaPagamento = 1`; busca por `nomeFormaPagamento` ou `idFormaPagamento` (`LIMIT 50`). | `<idFormaPagamento>` |
| `pessoa` | `tbDoadorContribuinte` (se `natureza === 'Entrada'`) ou `tbFornecedorPrestador` (se `natureza === 'Saída'`) | Somente ativos (`status... = 1`); busca por `nomeRazaoSocial`, `nomeFantasia`, `documento` (somente dígitos/alfanuméricos) ou `id` (`LIMIT 50`). Se `natureza` estiver vazia, retorna lista vazia. | `doador:<id>` ou `fornecedor:<id>` |

Quando `ctype_digit($busca)` é verdadeiro, a consulta adiciona uma cláusula `ORDER BY CASE WHEN id = :idExact THEN 0 WHEN CAST(id AS CHAR) LIKE :idPrefix THEN 1 ELSE 2 END` para priorizar buscas diretas por código numérico.

### Regras Críticas no `POST acao=salvar`

1. **Coerência de contraparte por natureza:**
   - Se a categoria selecionada for `Entrada`, o campo `pessoa` só aceita o prefixo `doador:<id>` e grava `idFornecedorPrestador = NULL`.
   - Se a categoria for `Saída`, só aceita `fornecedor:<id>` e grava `idDoadorContribuinte = NULL`.
2. **Proteção de referências inativas na edição (`UPDATE`):**
   - Ao criar um lançamento, todas as referências selecionadas precisam estar **ativas** (`status = 1`).
   - Ao editar um lançamento existente, o back-end carrega o registro original (`$lancamentoOriginal`). Se uma categoria, fonte, forma ou pessoa foi inativada **depois** da criação do lançamento, o usuário ainda consegue salvar outras alterações desde que mantenha o **mesmo ID original**; tentar trocar para *outro* cadastro inativo é bloqueado. Na interface, referências inativas preservadas são exibidas com o sufixo `(Inativa)` via `$autocompleteValues`.
3. **Emissão Automática do Recibo de Doação (Redirect Inteligente):**
   - Um lançamento é elegível a recibo quando atende simultaneamente às 3 condições: `naturezaCategoria === 'Entrada'`, `statusLancamento === 'Efetivado'` e `idDoadorContribuinte !== null`.
   - No `INSERT`: se for elegível, em vez de voltar para a listagem, o servidor redireciona diretamente para `recibo-doacao.php?id=<lastInsertId>` (preservando `tabToken`).
   - No `UPDATE`: se o lançamento **passou a ser elegível** (ex.: mudou de `Pendente` para `Efetivado`, ou teve um doador vinculado, ou mudou a natureza para `Entrada`), redireciona automaticamente para `recibo-doacao.php?id=<id>`. Na tabela de pesquisa, todo lançamento elegível também exibe um botão permanente para reemitir o recibo.

### Script Inline dos Comboboxes (`[data-reference-search]`)

O JavaScript inline de `lancamentos-financeiros.php` encapsula cada componente `[data-reference-search]` em uma fábrica isolada:

- **`searchItems()`:** dispara `fetch(..., { method: 'POST', signal })` com debounce de `250ms` e cancelamento via `AbortController` para evitar race conditions quando o usuário digita rápido.
- **`renderItems()` / teclado:** desenha a lista dropdown, suporta `ArrowDown`, `ArrowUp`, `Enter` e `Escape`, e sincroniza o `<input type="hidden">` com o valor real (`id` ou `doador:<id>` / `fornecedor:<id>`).
- **`updatePersonAvailability()` e `clearPerson()`:** escuta mudanças no combobox de categoria; quando a categoria não está escolhida, desabilita o campo de pessoa; quando a natureza alterna entre `Entrada` e `Saída`, atualiza dinamicamente o rótulo (`Doador/Contribuinte` vs `Fornecedor/Prestador`) e chama `clearPerson()` para impedir envio de contraparte incompatível.

## 6.2 Emissão de Recibo de Doação (`recibo-doacao.php`)

Endpoint GET dedicado (`recibo-doacao.php?id=<idLancamento>`) protegido por `appRequirePage('financeiro.movimentacoes/lancamentos-financeiros')`.

### Pipeline de Execução

1. **Validação do ID e Elegibilidade (`HTTP 404`):** valida `$_GET['id']` com `FILTER_VALIDATE_INT` (>= 1) e executa um `SELECT` unindo `tbLancamentoFinanceiro`, `tbCategoriaLancamentos`, `tbDoadorContribuinte`, `tbFormaPagamento` (`LEFT JOIN`) e `tbInformacoesInstituicao` (`LEFT JOIN ... ON i.idInformacoes = 1`). Se o registro não existir ou não for `Entrada` + `Efetivado` + com doador, aborta com `http_response_code(404)`.
2. **Validação dos Dados da Instituição (`HTTP 409`):** resolve `$nomeInstituicao` usando `razaoSocial` (ou fallback para `nomeFantasia`). Se ambos estiverem vazios, devolve uma página HTML amigável com status `409 Conflict` explicando que a doação foi salva, mas é necessário preencher **Informações da Instituição** antes de emitir o recibo, com botão de retorno para `lancamentos-financeiros.php`.
3. **Closures de Formatação:**
   - `$formatarDocumento(string $documento)` — aplica máscara de CPF (11) ou CNPJ (14).
   - `$formatarCep(?string $cep)` — aplica `00000-000`.
   - `$formatarTelefone(?string $telefone)` — aplica `(XX) XXXXX-XXXX` ou `(XX) XXXX-XXXX`.
   - `$montarEndereco(array $data, string $prefix, callable $formatarCep)` — monta endereço completo separado por ` · ` reutilizando o mesmo código para os prefixos `'doador'` e `'instituicao'`.
4. **Numeração e Renderização Dompdf:**
   - Gera o identificador único `$numeroRecibo = 'REC-' . $data->format('Y') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT)` e a data por extenso (`$dataPorExtenso`).
   - Instancia `\Dompdf\Dompdf` com `defaultFont = 'DejaVu Sans'` e `isRemoteEnabled = false` (segurança contra SSRF), renderiza em A4 retrato e faz stream com `Attachment => true` como `recibo-doacao-<id>.pdf`.

## 6.3 Metas Orçamentárias (`metas-orcamentarias.php`)

Controla o teto mensal de despesas (`tbMetaOrcamentaria`) e compara em tempo real com os lançamentos de **Saída** do ano selecionado.

### Aritmética Exata em Centavos Inteiros

Para eliminar qualquer imprecisão de ponto flutuante (`float`) em somatórios de 12 meses e saldos negativos, tanto `metas-orcamentarias.php` quanto os 2 demonstrativos gerenciais convertem os valores `DECIMAL(14,2)` do MySQL em **inteiros em centavos**:

```php
$converterCentavos = static function (mixed $value): int {
    preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', (string) $value, $parts);
    return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
};
// Exemplo: "1250.5" -> 125050 centavos; soma e subtração usam apenas inteiros exatos
```

- **`$formatarCentavos(int $centavos): string`** — usa `intdiv(abs($centavos), 100)` e `$centavos % 100`, preservando sinal negativo (`R$ -250,00`) e separadores de milhar.
- **`$converterValor(mixed $value): ?string`** — permite `0,00` (`valorOrcado >= 0`, diferente de lançamentos que exige `> 0`).
- **`$validarAno(mixed $value): int|false`** — restringe o ano ao intervalo `2000`..`9998` (garantindo que `$year + 1` nunca ultrapasse 4 dígitos no limite superior `YYYY-01-01`).

### Grade Fixa de 12 Meses e Modos de Apuração (`GET`)

Os filtros via `GET` aceitam `ano` (padrão ano atual) e `modo`:

- `efetivados` (padrão): soma apenas saídas com `statusLancamento = 'Efetivado'`.
- `pendentes`: soma saídas com `statusLancamento IN ('Efetivado', 'Pendente')` para visão projetada.

O PHP sempre percorre `for ($month = 1; $month <= 12; $month++)` exibindo os 12 meses do ano (mesmo os sem meta definida), calculando por linha o valor orçado, o gasto realizado, o saldo (`orçado - gasto`) e o status visual (`Dentro da meta`, `Atenção` ou `Acima da meta`), com totais acumulados no `<tfoot>`. Cada linha oferece atalhos inline (`+` para definir meta naquele mês, editar ou excluir).

### Endpoint AJAX `POST acao=detalharCompetencia` e Modal `<dialog>`

Ao clicar no valor gasto de qualquer mês na tabela, o script inline da página abre o elemento nativo `<dialog data-budget-dialog>` e envia um `POST` AJAX:

1. Envia `csrfToken`, `acao=detalharCompetencia`, `ano`, `mes` e `modo`.
2. O PHP consulta todos os lançamentos de `Saída` daquele mês/ano no modo escolhido, agrupa-os por categoria em memória e retorna JSON `{ categorias: [ { nome, total, lancamentos: [...] } ] }`.
3. O JS renderiza a lista agrupada dentro do `<dialog>` nativo, permitindo auditar exatamente quais despesas compõem o valor daquela competência sem sair da tela.

---

# 7. Relatórios do Módulo Financeiro

Os 7 arquivos em `pages/financeiro/relatorios/` dividem-se em **dois padrões arquiteturais distintos**: 5 relatórios configuráveis com gerador de PDF em Dompdf e 2 demonstrativos gerenciais formatados para impressão direta via navegador (`window.print()`).

## 7.1 Grupo A: Os 5 Relatórios Configuráveis em PDF (Dompdf)

Abrange `categoria-lancamentos.php`, `fontes-recurso.php`, `formas-pagamento.php`, `fornecedores-prestadores.php` e `doadores-contribuintes.php`.

### Arquitetura Baseada em `$reportConfig`

Cada arquivo inicia declarando um array declarativo `$reportConfig` que descreve a fonte de dados e as opções permitidas, seguido por um motor autossuficiente de validação, consulta SQL dinâmica segura (whitelist), prévia HTML e exportação Dompdf:

| Chave de `$reportConfig` | Papel no Motor de Relatórios |
| --- | --- |
| `file`, `title`, `pageTitle`, `singular`, `plural`, `downloadName` | Metadados da interface, cabeçalho do documento e nome do `.pdf` baixado. |
| `select` e `from` | Consulta base sempre com `INNER JOIN tbUsuario criador` e `LEFT JOIN tbUsuario alterador`. |
| `filters` | Whitelist de atributos filtráveis com `type`: `'text'` (`contains`, `equals`, `starts`), `'option'` (`equals` em lista fixa), `'status'` (`equals` em `0`/`1`) ou `'date'` (`between` com `from <= to`). |
| `groups` | Lista ordenada de atributos selecionáveis pelo usuário. Quando há **mais de 1 atributo**, o primeiro torna-se `$groupHeader` (quebra visual de grupo com contador opcional `$showGroupCount`) e os demais viram colunas (`$selectedColumns`). |
| `columns` | Define `sql` (para `ORDER BY`), `value`, `idValue` e `format` (`id-name`, `status`, `datetime` ou texto simples). |

### Máquina de Estados (`POST reportAction`)

- **`GET` (estado inicial):** exibe a tela de configuração controlada por `assets/js/usuarios-por-perfis.js` e estilizada por `assets/css/usuarios-por-perfis.css` (seleção de atributos/ordem, adição dinâmica de filtros, direção `ASC`/`DESC`, papel `A4`/`A5`/`Letter`/personalizado, orientação retrato/paisagem, margens em mm e listras zebra).
- **`POST` sem `reportAction` ou `generate`:** valida CSRF e todos os filtros/dimensões, monta `WHERE` exclusivamente com **prepared statements** (nunca interpolando valores do usuário, apenas colunas vindas da whitelist de `$reportConfig`), executa a query e renderiza a **prévia HTML** fiel ao papel configurado.
- **`POST` `reportAction=configure`:** restaura todas as seleções anteriores no formulário para o usuário ajustar sem perder os filtros montados.
- **`POST` `reportAction=download`:** gera o mesmo HTML em modo PDF via `\Dompdf\Dompdf`, aplica tamanho/orientação de papel configurados e faz stream do arquivo `.pdf`.

### Formatação Direta em SQL nos Relatórios de Contatos

Em `fornecedores-prestadores.php` e `doadores-contribuintes.php`, os campos `documento`, `telefone` e `cep` são armazenados limpos (sem máscara) no banco. Para permitir filtros e exibição já formatados no relatório sem pós-processamento complexo, o `SELECT` dentro de `$reportConfig` aplica expressões `CASE` + `CONCAT` + `SUBSTRING` diretamente no MySQL para mascarar CPF (11), CNPJ (14), telefone (10/11) e CEP (8).

## 7.2 Grupo B: Os 2 Demonstrativos Gerenciais (`window.print()`)

Diferentes dos relatórios de cadastros, `extrato-lancamentos.php` e `resumo-metas-orcamentarias.php` são voltados à análise contábil/gerencial de movimentações:

| Aspecto | `extrato-lancamentos.php` | `resumo-metas-orcamentarias.php` |
| --- | --- | --- |
| Parâmetros `GET` | `gerar=1`, `inicio`, `fim`, `natureza` (`Todas`/`Entrada`/`Saída`), `situacao` (`Todos`/`Efetivado`/`Pendente`) | `gerar=1`, `ano` (`2000..9998`), `modo` (`efetivados`/`pendentes`) |
| Consultas SQL | 1 query completa com `INNER JOIN` em categoria e 4 `LEFT JOIN`s (fonte, forma, fornecedor, doador), usando `COALESCE(fp.nomeRazaoSocial, dc.nomeRazaoSocial) AS contraparte`, ordenado por `dataLancamento, idLancamento`. | 2 queries: (1) metas do ano em `tbMetaOrcamentaria`; (2) `SUM(valorLancamento)` de saídas agrupado por `MONTH(dataLancamento), idCategoria, nomeCategoria`. |
| Aritmética | Centavos inteiros (`$converterCentavos` / `$formatarCentavos`) calculando `$totalEntradas`, `$totalSaidas` e `$movimentoLiquido`. | Centavos inteiros calculando `$totalOrcado`, `$totalGastoAno`, `$saldoMesesOrcados` (somente meses com meta) e detalhamento mensal por categoria. |
| Impressão (`@page` + `@media print`) | `@page { size: A4 landscape; margin: 8mm; }`; define larguras fixas percentuais das 9 colunas (`.print-table`), oculta navbar/sidebar/formulário (`.no-print`) e repete `<thead>` em cada página. | `@page { size: A4 portrait; margin: 10mm; }`; força grid de 3 cartões de resumo, exibe quadro de 12 meses + quadro detalhado por mês/categoria com `thead { display: table-header-group; }`. |

> **Dica de arquitetura:** Ambos os demonstrativos só executam as consultas pesadas quando `$_GET['gerar'] === '1'` e não há erro de validação. Ao abrir a tela pela primeira vez, apenas o formulário de filtros é exibido com valores padrão inteligentes (mês corrente no Extrato; ano corrente no Resumo de Metas).

---

# 8. Fluxo Completo End-to-End e Guia de Manutenção

Esta seção amarra o funcionamento completo do módulo e serve como checklist rápido sempre que você precisar corrigir um bug, adicionar um campo ou criar uma nova tela no Financeiro.

## 8.1 Fluxo Ponta a Ponta de uma Operação Real

1. **Preparação (Cadastros):**
   - Cria-se uma categoria em `tbCategoriaLancamentos` (ex.: `Doações Espontâneas`, `naturezaCategoria = 'Entrada'`), uma forma de pagamento em `tbFormaPagamento` (`PIX`) e um doador em `tbDoadorContribuinte` (com documento validado em módulo 11 e endereço preenchido via endpoint `consultarCep`).
2. **Registro da Movimentação (`lancamentos-financeiros.php`):**
   - O operador pesquisa a categoria no combobox AJAX (`acao=buscarReferencias`, `tipo=categoria`).
   - O JS detecta `natureza === 'Entrada'`, habilita o combobox de pessoa como **Doador/Contribuinte** e, ao pesquisar, consulta `tipo=pessoa&natureza=Entrada`, recebendo `doador:<id>`.
   - O campo de valor usa `[data-money-input]` no front-end e `$converterValor` no back-end.
   - Ao salvar como `Efetivado`, o `INSERT` grava em `tbLancamentoFinanceiro` com `idUsuarioCriacao` e detecta elegibilidade de recibo.
3. **Emissão Automática do Comprovante (`recibo-doacao.php`):**
   - O `Location` pós-`INSERT` direciona para `recibo-doacao.php?id=<id>&tabToken=...`.
   - O script valida elegibilidade, lê `tbInformacoesInstituicao (id = 1)`, gera `REC-AAAA-NNNNNN` e entrega o PDF via Dompdf.
4. **Reflexo Imediato nas Visões Gerenciais:**
   - **Dashboard (`dashboard-financeiro.php`):** o lançamento efetivado já soma no KPI de Entradas do Mês, no Saldo Acumulado, no histórico de 6 meses e na lista de últimos 7 lançamentos.
   - **Metas Orçamentárias (`metas-orcamentarias.php`):** se fosse uma `Saída`, somaria automaticamente no mês correspondente, apareceria no detalhamento AJAX (`acao=detalharCompetencia`) e no gráfico donut do dashboard.
   - **Extrato e Resumo (`relatorios/extrato-lancamentos.php` e `resumo-metas-orcamentarias.php`):** refletem o registro instantaneamente em centavos exatos, prontos para impressão em PDF pelo navegador.

## 8.2 Checklist Técnico para Criar ou Alterar Telas no Módulo

Ao evoluir o Módulo Financeiro, siga rigorosamente estes 8 pontos para manter total consistência com o restante do Sistema Elo:

1. **Segurança na linha 1:** comece sempre com `require_once auth/access.php` + `$usuarioAtual = appRequirePage('<chave>');`. Se criar uma tela nova em `pages/financeiro/<subpasta>/<arquivo>.php`, o catálogo de `auth/access.php` e o `components/module-sidebar.php` a descobrem automaticamente (basta liberar a permissão ao perfil em **Perfis de Acesso**).
2. **Sessão por aba em links e redirects:** nunca faça `header('Location: pagina.php')` ou `<a href="pagina.php">` sem envolver com `appTabUrl(...)`, e inclua `<input type="hidden" name="tabToken" value="...">` em formulários `GET`.
3. **CSRF em todo `POST`:** toda mutação e todo endpoint AJAX `POST` deve chamar `appValidateCsrf()` antes de tocar no banco.
4. **Escape XSS rigoroso:** nunca imprima variável vinda do banco ou de `$_GET`/`$_POST` diretamente com `echo`; use sempre `appEscape(...)`.
5. **Padrão de Banco:** crie migrations em `db/migrations/` e atualize `db/schema.sql`, mantendo `idUsuarioCriacao`, `idUsuarioAlteracao`, `criadoEm`, `atualizadoEm` e FKs com `ON UPDATE CASCADE ON DELETE RESTRICT`.
6. **Valores monetários:** grave em `DECIMAL(14,2)` no MySQL; no PHP, valide com regex PT-BR e prefira **centavos inteiros** (`int`) para qualquer soma, subtração ou totalização.
7. **Sem subtítulos no cabeçalho:** mantenha apenas o `<h1>` no cabeçalho da página (sem parágrafo descritivo abaixo), seguindo o padrão visual atual do sistema.
8. **Paginação automática:** para novas tabelas de pesquisa em telas CRUD, adicione o atributo `data-filter-input="idDoTbody"` no campo de busca — o `assets/js/app.js` aplicará automaticamente a busca e a paginação configurável (`10/25/50/100`).

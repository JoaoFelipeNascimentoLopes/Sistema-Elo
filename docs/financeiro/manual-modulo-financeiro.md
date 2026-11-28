---
title: Manual do Módulo Financeiro
subtitle: Guia Completo de Funcionalidades, Preenchimento e Fluxo Operacional
module: Módulo Financeiro
system: Sistema Elo — Gerenciamento de Casas de Acolhimento
author: João Felipe
version: 1.1
date: 28/11/2026
---

# 1. Visão Geral do Módulo Financeiro

O **Módulo Financeiro** do **Sistema Elo** concentra toda a gestão econômica da casa de acolhimento: cadastros auxiliares (categorias, fontes de recursos, formas de pagamento, fornecedores e doadores), registro diário de receitas e despesas, emissão de recibos de doação em PDF, acompanhamento de metas orçamentárias mensais, painel gerencial (dashboard) e relatórios configuráveis para impressão e prestação de contas.

## 1.1 Organização do Menu

As telas do módulo estão agrupadas em quatro pastas no menu lateral:

| Grupo | Telas Disponíveis | Finalidade Principal |
| --- | --- | --- |
| **Dashboards** | Dashboard Financeiro | Visão consolidada do saldo, receitas, despesas, pendências e meta do mês |
| **Cadastros** | Categorias de Lançamentos, Fontes de Recursos, Formas de Pagamento, Fornecedores/Prestadores, Doadores/Contribuintes | Estruturar classificações e pessoas/empresas usadas nos lançamentos |
| **Movimentações** | Lançamentos Financeiros (com Recibo de Doação), Metas Orçamentárias | Registrar receitas/despesas do dia a dia e definir o orçamento mensal |
| **Relatórios** | Categorias, Fontes, Formas, Fornecedores, Doadores, Extrato de Lançamentos, Resumo de Metas Orçamentárias | Conferência, transparência, impressão e exportação em PDF |

## 1.2 Padrão de Navegação das Telas

Todas as telas de cadastro e movimentação seguem o mesmo padrão de abas:

- **Aba Pesquisa:** lista os registros cadastrados com campo de pesquisa em tempo real e barra de paginação configurável (**10, 25, 50 ou 100** itens por página).
- **Aba Manutenção:** formulário para inclusão ou alteração de um registro, exibindo automaticamente os campos de auditoria (**Criado por** e **Alterado por** com usuário, data e hora).

> **Dica de Segurança:** o acesso a cada tela do módulo Financeiro é controlado em **Geral → Cadastros → Perfis de Acesso**. Caso alguma tela não apareça no menu lateral, solicite a liberação ao administrador do sistema.

---

# 2. Fluxo Recomendado de Utilização

Para que os lançamentos, recibos e relatórios funcionem corretamente desde o primeiro dia, siga a ordem operacional abaixo:

```text
[1. Pré-requisito]  Configurações Pessoais → Informações da Instituição
         │          (CNPJ, Razão Social/Nome Fantasia, contato e endereço para recibos)
         ▼
[2. Cadastros Base] Categorias de Lançamentos (obrigatório)
         │          Fontes de Recursos, Formas de Pagamento,
         │          Fornecedores/Prestadores e Doadores/Contribuintes
         ▼
[3. Planejamento]   Metas Orçamentárias (opcional — defina o orçamento de cada mês)
         ▼
[4. Rotina Diária]  Lançamentos Financeiros (Entradas e Saídas · Pendente / Efetivado)
         │          └─► Entrada Efetivada + Doador = Emitir Recibo de Doação (PDF)
         ▼
[5. Gestão]         Dashboard Financeiro + Relatórios (Extrato, Metas e Cadastros em PDF)
```

### Passo a passo resumido da rotina mensal

1. **Início do mês:** cadastre a meta de despesas em **Metas Orçamentárias** (opcional, apenas informativo).
2. **Ao longo do mês:** registre contas a pagar/receber em **Lançamentos Financeiros** como `Pendente` e altere para `Efetivado` assim que o pagamento ou recebimento ocorrer.
3. **Ao receber doações:** vincule o **Doador/Contribuinte**, marque como `Efetivado` e emita o **Recibo de Doação em PDF** diretamente na coluna de ações.
4. **Acompanhamento:** monitore o **Dashboard Financeiro** para acompanhar saldo acumulado, evolução dos últimos 6 meses e percentual consumido da meta mensal.
5. **Fechamento e prestação de contas:** gere o **Extrato de Lançamentos Financeiros** e o **Resumo de Metas Orçamentárias** para arquivo ou diretoria.

---

# 3. Dashboard Financeiro

**Caminho no menu:** `Financeiro → Dashboards → Dashboard Financeiro`

O painel apresenta um retrato instantâneo da saúde financeira da instituição no mês vigente ou em qualquer competência anterior/futura selecionada, considerando os lançamentos cadastrados.

## 3.1 Recursos da Barra Superior

- **Seletor de competência (`<` mês/ano `>` e botão *Mês atual*):** navegue rapidamente entre meses anteriores para consultar fechamentos passados ou escolha diretamente o mês e o ano desejados no seletor; ao visualizar outra competência, o botão **Mês atual** retorna imediatamente ao mês vigente.
- **Ocultar valores / Mostrar valores:** botão de privacidade que mascara os valores monetários e oculta barras/gráficos na tela (útil ao compartilhar a tela em reuniões ou ambientes abertos).
- **+ Novo lançamento:** atalho direto para a tela de Lançamentos Financeiros.

## 3.2 Indicadores e Gráficos Exibidos

| Bloco | O que mostra | Regra de cálculo |
| --- | --- | --- |
| **Saldo líquido acumulado** | Total disponível histórico (ou acumulado até o mês escolhido) | Soma de `Entradas` menos `Saídas` com situação `Efetivado` (total no mês atual ou até o fim da competência selecionada) |
| **Entradas efetivadas** | Receitas confirmadas na competência | Lançamentos de `Entrada` com status `Efetivado` no mês selecionado |
| **Saídas efetivadas** | Despesas pagas na competência | Lançamentos de `Saída` com status `Efetivado` no mês selecionado |
| **Lançamentos pendentes** | Valor e quantidade em aberto na competência | Soma de entradas e saídas com status `Pendente` no mês selecionado |
| **Entradas e saídas (6 meses)** | Gráfico comparativo de barras | Evolução mensal de entradas × saídas efetivadas nos 6 meses encerrados na competência |
| **Saídas por categoria** | Gráfico rosca (donut) + legenda | Distribuição percentual das despesas efetivadas por categoria no mês selecionado |
| **Meta do mês** | Barra de progresso do orçamento | Compara as saídas efetivadas do mês selecionado contra a meta cadastrada |
| **Lançamentos recentes** | Tabela rápida | Exibe os 7 últimos lançamentos registrados (ou os 7 últimos da competência consultada) |

---

# 4. Cadastros do Módulo Financeiro

Os cadastros estruturam todas as opções disponíveis na hora de lançar receitas e despesas. Nenhum cadastro que já esteja vinculado a lançamentos pode ser excluído — nesses casos, basta desmarcar a opção **Ativo** para inativá-lo sem perder o histórico.

## 4.1 Categorias de Lançamentos

**Caminho no menu:** `Financeiro → Cadastros → Categorias de Lançamentos`

Classifica o motivo de cada movimentação e define automaticamente se um lançamento será tratado como **Entrada** ou **Saída**. É o único cadastro **obrigatório** antes de registrar lançamentos financeiros.

### Campos e preenchimento

| Campo | Obrigatório | Limite / Formato | Como preencher |
| --- | --- | --- | --- |
| **Nome da categoria** | Sim | Até 100 caracteres | Nome claro e padronizado (ex.: *Doações Mensais*, *Energia Elétrica*, *Alimentação*) |
| **Natureza** | Sim | `Entrada` ou `Saída` | Define se a categoria soma (receita) ou subtrai (despesa) do saldo |
| **Descrição** | Não | Até 255 caracteres | Detalhamento opcional do que deve ser classificado nesta categoria |
| **Categoria ativa** | Sim (padrão marcado) | Caixa de seleção | Desmarque para impedir novos lançamentos usando esta categoria |

> **Regra de unicidade:** não é permitido cadastrar duas categorias com o mesmo **Nome** e a mesma **Natureza**. (É possível ter nomes iguais apenas se uma for `Entrada` e outra `Saída`.)

---

## 4.2 Fontes de Recursos

**Caminho no menu:** `Financeiro → Cadastros → Fontes de Recursos`

Identifica a origem ou o fundo vinculado ao recurso movimentado (ex.: *Recursos Próprios*, *Convênio Municipal*, *Fundo da Infância*, *Campanha de Doações*).

### Campos e preenchimento

| Campo | Obrigatório | Limite / Formato | Como preencher |
| --- | --- | --- | --- |
| **Nome da fonte** | Sim | Até 100 caracteres (único) | Nome da conta, convênio ou origem do recurso |
| **Descrição** | Não | Até 255 caracteres | Informações adicionais (ex.: número do convênio ou finalidade) |
| **Fonte ativa** | Sim (padrão marcado) | Caixa de seleção | Controla se a fonte aparece nas sugestões de novos lançamentos |

---

## 4.3 Formas de Pagamento

**Caminho no menu:** `Financeiro → Cadastros → Formas de Pagamento`

Registra os meios utilizados para pagar despesas ou receber doações (ex.: *PIX*, *Dinheiro*, *Transferência Bancária*, *Boleto*, *Cartão de Débito*).

### Campos e preenchimento

| Campo | Obrigatório | Limite / Formato | Como preencher |
| --- | --- | --- | --- |
| **Nome da forma de pagamento** | Sim | Até 100 caracteres (único) | Meio de pagamento/recebimento utilizado pela instituição |
| **Descrição** | Não | Até 255 caracteres | Observações opcionais (ex.: conta bancária vinculada ou bandeira) |
| **Forma de pagamento ativa** | Sim (padrão marcado) | Caixa de seleção | Mantém a opção disponível para novos lançamentos |

---

## 4.4 Fornecedores e Prestadores

**Caminho no menu:** `Financeiro → Cadastros → Fornecedores/Prestadores`

Cadastro das pessoas físicas ou empresas para quem a instituição efetua pagamentos (mercados, farmácias, concessionárias, manutenção, profissionais autônomos). Este cadastro fica disponível automaticamente nos lançamentos cuja categoria seja de natureza **Saída**.

### Campos e preenchimento

| Seção | Campo | Obrigatório | Regras e orientações |
| --- | --- | --- | --- |
| **1. Identificação** | **Tipo de pessoa** | Sim | Escolha `Pessoa Física` ou `Pessoa Jurídica` (habilita o campo de documento) |
| **1. Identificação** | **CPF/CNPJ** | Sim | Documento único com dígitos verificadores válidos (aceita CPF numérico ou CNPJ numérico/alfanumérico) |
| **1. Identificação** | **Nome completo / Razão social** | Sim | Até 150 caracteres |
| **2. Dados da empresa** | **Nome fantasia** | Não | Exibido somente para `Pessoa Jurídica` (até 150 caracteres) |
| **2. Dados da empresa** | **Pessoa de contato** | Não | Exibido somente para `Pessoa Jurídica` (até 150 caracteres) |
| **3. Contato** | **E-mail** | Não | Formato de e-mail válido (até 190 caracteres) |
| **3. Contato** | **Telefone** | Não | Telefone brasileiro válido com DDD (10 ou 11 dígitos) |
| **4. Controle** | **Observações** | Não | Até 255 caracteres (ex.: dados bancários/chave PIX ou horário de entrega) |
| **4. Controle** | **Cadastro ativo** | Sim (padrão marcado) | Desmarque para inativar sem perder o histórico de pagamentos |

---

## 4.5 Doadores e Contribuintes

**Caminho no menu:** `Financeiro → Cadastros → Doadores/Contribuintes`

Cadastro das pessoas físicas ou jurídicas que realizam doações ou contribuições financeiras para a casa. Este cadastro fica disponível automaticamente nos lançamentos de natureza **Entrada** e é **essencial para emissão de Recibos de Doação**.

### Campos e preenchimento

| Seção | Campo | Obrigatório | Regras e orientações |
| --- | --- | --- | --- |
| **1. Identificação** | **Tipo de pessoa** | Sim | `Pessoa Física` ou `Pessoa Jurídica` |
| **1. Identificação** | **CPF/CNPJ** | Sim | Documento único com validação oficial; aparecerá impresso no Recibo de Doação |
| **1. Identificação** | **Nome completo / Razão social** | Sim | Até 150 caracteres; exibido como titular no recibo |
| **1. Identificação** | **Nome fantasia** | Não | Disponível apenas para `Pessoa Jurídica` |
| **2. Contato** | **E-mail / Telefone** | Não | Validação de e-mail e telefone com DDD |
| **3. Endereço** | **CEP + Botão Buscar** | Não | Ao informar 8 dígitos e clicar na lupa, o sistema consulta a API **ViaCEP** e preenche Logradouro, Bairro, Cidade e UF automaticamente |
| **3. Endereço** | **Logradouro, Número, Complemento, Bairro, Cidade, UF** | Não | O endereço completo do doador é incluído automaticamente no Recibo de Doação quando preenchido |
| **4. Controle** | **Observações e Cadastro ativo** | Não / Sim | Notas adicionais (até 255 caracteres) e status do cadastro |

---

# 5. Movimentações Financeiras

## 5.1 Lançamentos Financeiros

**Caminho no menu:** `Financeiro → Movimentações → Lançamentos Financeiros`

Tela principal da rotina financeira. Permite lançar todas as entradas e saídas, gerenciar pendências e emitir recibos de doação.

### Campos e preenchimento

| Seção | Campo | Obrigatório | Regras e como preencher |
| --- | --- | --- | --- |
| **1. Dados do lançamento** | **Data do lançamento** | Sim | Data da movimentação (padrão: data atual) |
| **1. Dados do lançamento** | **Situação** | Sim | `Pendente` (ainda não pago/recebido) ou `Efetivado` (já realizado e contabilizado no saldo) |
| **1. Dados do lançamento** | **Descrição** | Sim | Até 255 caracteres. Seja específico (ex.: *"Compra de hortifruti — NF 482"* ou *"Doação campanha de inverno"*) |
| **1. Dados do lançamento** | **Valor** | Sim | Valor monetário maior que `0,00` (formato brasileiro, ex.: `1.250,50`) |
| **2. Classificação e vínculos** | **Categoria** | Sim | Combobox com pesquisa por ID ou nome. Define se o lançamento é `Entrada` ou `Saída` e habilita o campo de contraparte correspondente |
| **2. Classificação e vínculos** | **Empresa ou pessoa física** | Não | Após escolher a categoria: se for **Saída**, busca em *Fornecedores/Prestadores*; se for **Entrada**, busca em *Doadores/Contribuintes* |
| **2. Classificação e vínculos** | **Fonte de recurso** | Não | Combobox pesquisável com fontes ativas |
| **2. Classificação e vínculos** | **Forma de pagamento** | Não | Combobox pesquisável com formas de pagamento ativas |
| **3. Comprovante e observações** | **Comprovante / Nota Fiscal** | Não | Anexo opcional em **PDF, JPG, PNG ou WebP (até 10 MB)** armazenado em diretório privado, com visualização em modal e download seguro |
| **3. Comprovante e observações** | **Observações** | Não | Até 255 caracteres para anotações internas |

### Como usar os campos com autocomplete (Combobox Pesquisável)

Nos campos **Categoria**, **Empresa/Pessoa Física**, **Fonte de recurso** e **Forma de pagamento**:
1. Clique no campo ou na seta lateral (`▾`) para listar até 50 cadastros ativos.
2. Digite o **ID**, **nome** ou **CPF/CNPJ** para filtrar em tempo real no servidor.
3. Clique na opção desejada (ou navegue com `↑` / `↓` e pressione `Enter`).

> **Atenção:** ao alterar uma categoria de `Entrada` para `Saída` (ou vice-versa), o campo de Empresa/Pessoa Física é limpo automaticamente para evitar vincular um fornecedor a uma entrada ou um doador a uma saída.

### Pesquisa e gestão de lançamentos

Na aba **Pesquisa**, você visualiza todos os lançamentos com barra de busca instantânea, paginação configurável (**10 / 25 / 50 / 100**), coluna **Comprovante** (botão **Ver** para abrir o PDF ou imagem da nota fiscal/recibo em modal na mesma aba) e um aviso no topo informando quantos lançamentos estão elegíveis para emissão de recibo. Na coluna **Ações** é possível:
- **Efetivar (1 clique):** exibido nos lançamentos com situação `Pendente` para dar baixa imediata sem precisar abrir o formulário de edição (registrando automaticamente no Log de Auditoria).
- **Emitir recibo:** disponível para doações efetivadas com doador vinculado.
- **Editar (lápis):** alterar valores, anexar/substituir/remover comprovante, corrigir vínculos ou mudar a situação.
- **Excluir (lixeira):** remover o lançamento (e eventual comprovante anexado) após confirmação.

---

## 5.2 Emissão de Recibo de Doação (PDF)

**Caminho de acesso:** `Financeiro → Movimentações → Lançamentos Financeiros → Aba Pesquisa → Botão "Emitir recibo"`

O sistema gera automaticamente um comprovante formal em **PDF (A4 Retrato)** numerado no formato `REC-AAAA-NNNNNN` (ex.: `REC-2026-000042`).

### Requisitos para o botão "Emitir recibo" aparecer e funcionar

1. A categoria do lançamento deve ter natureza **`Entrada`**.
2. A situação do lançamento deve estar como **`Efetivado`**.
3. Um **`Doador/Contribuinte`** deve estar vinculado ao lançamento.
4. A instituição precisa ter **Razão Social** ou **Nome Fantasia** cadastrados em **Configurações Pessoais → Informações da Instituição** (caso contrário, o sistema exibe um aviso orientando a preencher os dados antes de gerar o PDF).

### Informações incluídas no PDF do Recibo

- Cabeçalho da instituição (Razão Social/Nome Fantasia, CNPJ, endereço, telefone e e-mail).
- Número único do recibo e declaração formal com nome e CPF/CNPJ do doador e valor recebido.
- Quadro destacado com descrição/finalidade, data por extenso, forma de recebimento e endereço do doador (se cadastrado).
- Local, data e linha de assinatura do responsável pelo recebimento.

---

## 5.3 Metas Orçamentárias

**Caminho no menu:** `Financeiro → Movimentações → Metas Orçamentárias`

Permite planejar um teto mensal informativo de despesas (saídas) por ano e mês, acompanhando o quanto já foi gasto e o saldo restante do orçamento.

> **Importante:** as metas orçamentárias são **estritamente informativas**. O sistema **nunca bloqueia** o registro de uma despesa caso o valor orçado seja ultrapassado — o saldo apenas fica destacado em vermelho para alertar a gestão.

### Como definir ou editar uma meta (Aba Manutenção)

| Campo | Obrigatório | Regras |
| --- | --- | --- |
| **Ano** | Sim | Ano entre `2000` e `9998` |
| **Mês** | Sim | Janeiro a Dezembro (apenas uma meta por par Ano/Mês) |
| **Valor orçado** | Sim | Valor igual ou superior a `0,00` |

### Como acompanhar as metas (Aba Pesquisa)

1. Escolha o **Ano** desejado no filtro superior.
2. Defina o modo de cálculo em **Calcular gasto**:
   - **Efetivados:** considera apenas saídas já efetivadas.
   - **Incluir pendentes:** soma saídas efetivadas + pendentes (útil para prever o fechamento do mês).
3. Clique em **Atualizar**.
4. A tabela exibe todos os **12 meses do ano** com: *Orçamento*, *Gasto*, *Saldo do orçamento* e o **Total do ano** no rodapé.
5. Clique no ícone de informação (`i`) ao lado do valor gasto de qualquer mês para abrir o **modal de detalhamento por categoria**, mostrando exatamente onde o dinheiro foi aplicado naquele mês.
6. Use os botões de ação (`+`, lápis ou lixeira) para definir, alterar ou remover o orçamento de qualquer mês diretamente.

---

# 6. Relatórios do Módulo Financeiro

O módulo disponibiliza **7 relatórios** divididos em dois formatos: **relatórios configuráveis de cadastros** (5 relatórios) e **demonstrativos gerenciais** (2 relatórios).

## 6.1 Relatórios Configuráveis de Cadastros (com Exportação em PDF)

As cinco telas abaixo compartilham o mesmo motor configurável com prévia de impressão e download em **PDF via Dompdf**:

- **Categorias de Lançamentos** (`Financeiro → Relatórios → Categorias de Lançamentos`)
- **Fontes de Recursos** (`Financeiro → Relatórios → Fontes de Recursos`)
- **Formas de Pagamento** (`Financeiro → Relatórios → Formas de Pagamento`)
- **Fornecedores/Prestadores** (`Financeiro → Relatórios → Fornecedores/Prestadores`)
- **Doadores/Contribuintes** (`Financeiro → Relatórios → Doadores/Contribuintes`)

### Como gerar qualquer relatório configurável

1. **Aba Filtros:**
   - Clique em **+ Adicionar filtro** para restringir os dados (por texto com *Contém / Igual a / Começa com*, por status/tipo ou por intervalo de datas). Sem filtros, todos os registros são incluídos.
   - Em **Agrupamentos**, clique em **+ Adicionar agrupamento** para escolher as colunas exibidas e a ordem delas (use as setas `▲` / `▼` para reordenar). Ao escolher 2 ou mais atributos, o primeiro vira cabeçalho de grupo.
2. **Aba Ordenação:** escolha ordem **Crescente (A–Z)** ou **Decrescente (Z–A)**.
3. **Aba Configurações:** ajuste tamanho do papel (`A4`, `A3`, `Carta` ou `Personalizado`), orientação (`Retrato` ou `Paisagem`), margens em milímetros, contagem por grupo e linhas zebradas (com cor personalizável).
4. Clique em **Gerar relatório**.
5. Na tela de prévia, utilize a barra superior para **Voltar** (mantendo suas configurações), **Imprimir** ou **Baixar PDF**.

---

## 6.2 Extrato de Lançamentos Financeiros

**Caminho no menu:** `Financeiro → Relatórios → Extrato de Lançamentos Financeiros`

Gera uma folha em formato de extrato contábil (A4 Paisagem) com todas as movimentações do período, ideal para conferência bancária e prestação de contas.

### Como gerar

1. Preencha **Data inicial** e **Data final** (padrão: primeiro dia do mês atual até hoje).
2. Opcionalmente, filtre por **Natureza** (`Todas`, `Entrada`, `Saída`) e **Situação** (`Todos`, `Efetivado`, `Pendente`).
3. Clique em **Gerar extrato**.
4. Confira os totalizadores (*Total de Entradas*, *Total de Saídas* e *Movimento Líquido*) e a listagem cronológica detalhada.
5. Clique em **Imprimir extrato** para imprimir ou salvar como PDF pelo navegador.

---

## 6.3 Resumo de Metas Orçamentárias

**Caminho no menu:** `Financeiro → Relatórios → Resumo de Metas Orçamentárias`

Gera um demonstrativo anual (A4 Retrato) comparando o orçamento previsto mês a mês com as despesas realizadas, incluindo detalhamento por categoria.

### Como gerar

1. Informe o **Ano** de referência.
2. Selecione a **Situação** (`Somente efetivados` ou `Efetivados e pendentes`).
3. Clique em **Gerar resumo**.
4. Analise os indicadores anuais (*Total Orçado*, *Total Gasto no Ano* e *Saldo dos Meses Orçados*), o quadro mensal e o detalhamento por categoria.
5. Clique em **Imprimir resumo** para imprimir ou salvar em PDF.

---

# 7. Boas Práticas e Solução de Problemas Comuns

### Boas práticas no dia a dia

- **Padronize os nomes das categorias:** evite criar categorias duplicadas ou genéricas demais (prefira *"Alimentação"*, *"Medicamentos"*, *"Energia Elétrica"* em vez de *"Diversos"*).
- **Use `Pendente` para contas futuras:** lance contas a vencer como `Pendente` para acompanhar compromissos no Dashboard e nas Metas Orçamentárias; efetive-as na data do pagamento real.
- **Inative em vez de tentar excluir:** se uma categoria, fonte, forma de pagamento, fornecedor ou doador já tiver lançamentos vinculados, o sistema bloqueia a exclusão para preservar a integridade contábil — basta desmarcar a opção **Ativo**.
- **Mantenha os Dados da Instituição atualizados:** eles garantem recibos de doação completos e juridicamente claros.

### Perguntas frequentes

| Situação | Motivo provável | Como resolver |
| --- | --- | --- |
| O campo **Empresa/Pessoa Física** no lançamento está desabilitado | Nenhuma categoria foi selecionada ainda | Selecione primeiro uma **Categoria**; o sistema liberará automaticamente Fornecedores (Saída) ou Doadores (Entrada) |
| O botão **Emitir recibo** não aparece em uma doação | O lançamento está `Pendente`, é de `Saída` ou não tem doador vinculado | Edite o lançamento, vincule o **Doador/Contribuinte** e altere a situação para `Efetivado` |
| Erro *"Recibo não emitido"* ao clicar no recibo | Falta Razão Social ou Nome Fantasia da instituição | Abra **Configurações Pessoais → Informações da Instituição** no topo do sistema e salve os dados |
| Não consigo excluir uma categoria, fonte ou fornecedor | Já existem lançamentos financeiros vinculados a ele | Edite o cadastro e desmarque a caixa **Ativo** para inativá-lo com segurança |
| O saldo do Dashboard não mudou após cadastrar um lançamento | O lançamento foi salvo como `Pendente` | O saldo acumulado e os gráficos mensais consideram apenas lançamentos com situação `Efetivado` |

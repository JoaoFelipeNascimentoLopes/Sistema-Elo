---
title: Manual do Usuário — Módulo Geral
subtitle: Guia Prático de Operação, Administração, Equipe, Plantões, Agenda, Documentos e Auditoria
module: Módulo Geral · Manual do Usuário
system: Sistema Elo — Gerenciamento de Casas de Acolhimento
author: João Felipe
version: 1.0
date: 28/11/2026
toc_heading: Conteúdo do Manual
---

# 1. Visão Geral do Módulo Geral

O **Módulo Geral** é o centro administrativo e operacional do **Sistema Elo**. Ele reúne tudo o que sustenta o funcionamento diário da casa de acolhimento: governança de acessos (usuários e perfis), gestão da equipe e voluntários (com foto e emissão de crachás), controle de documentos e certidões institucionais, mapa interativo de escala de plantões, agenda institucional integrada, livro de plantão com mural de avisos, trilha global de auditoria, preferências pessoais de conta e relatórios gerenciais.

## 1.1 Organização do Menu

As telas do Módulo Geral estão distribuídas em quatro grupos no menu lateral:

| Grupo | Telas Disponíveis | Finalidade Principal |
| --- | --- | --- |
| **Dashboards** | Dashboard Geral | Visão executiva consolidada da instituição, plantões, agenda, alertas e auditoria |
| **Cadastros** | Usuários, Perfis de Acesso, Colaboradores e Voluntários (com Crachá CR80), Documentos e Certidões Institucionais | Controlar logins, permissões, equipe funcional e regularidade documental |
| **Movimentações** | Escala de Plantões, Agenda Institucional, Livro de Plantão e Avisos, Log de Auditoria | Coordenar turnos, compromissos, passagens de turno e rastreabilidade global |
| **Relatórios** | Usuários por Perfis, Matriz de Permissões por Perfil, Colaboradores e Voluntários | Conferência administrativa, governança de acessos e exportação em PDF |

Além das telas do menu lateral, o ícone de **engrenagem** presente na barra superior de todos os módulos dá acesso às **Configurações Pessoais e Institucionais**:

- **Alterar Senha:** permite atualizar a própria senha de acesso seguindo a política de segurança.
- **Informações da Instituição:** mantém CNPJ, razão social, nome fantasia, contato e endereço oficial usados em crachás, recibos e cabeçalhos (controlado pela permissão *Informações da Instituição*).
- **Aparência e Tema:** alterna entre **Tema Claro** e **Tema Escuro**, salvando a preferência individualmente para cada login.

## 1.2 Padrão de Navegação e Pesquisa

- **Abas Pesquisa e Manutenção:** os cadastros estruturados (Usuários, Perfis de Acesso, Colaboradores e Documentos) mantêm uma aba **Pesquisa** para consulta rápida e uma aba **Manutenção** para inclusão ou edição, sempre exibindo os carimbos de auditoria (**Criado por** e **Alterado por**).
- **Busca e Paginação Global:** todas as tabelas de pesquisa contam com filtro instantâneo e seletor configurável de **10, 25, 50 ou 100** registros por página.
- **Sessão Isolada por Aba:** o Sistema Elo mantém sessões independentes por aba do navegador. Evite abrir links internos em nova aba pelo botão direito; todos os visualizadores de documentos e crachás abrem em janelas internas (modais) na própria aba.

> **Dica de Acesso:** a visibilidade de cada tela no menu lateral depende das permissões marcadas para o seu perfil em **Geral → Cadastros → Perfis de Acesso**.

---

# 2. Fluxo Recomendado de Implantação e Rotina

Para extrair o máximo do Módulo Geral desde o início da operação, siga esta sequência recomendada:

```text
[1. Base Institucional] Engrenagem → Informações da Instituição
          │             (CNPJ, Nome Fantasia, telefone e endereço para crachás/recibos)
          ▼
[2. Governança]         Perfis de Acesso → Usuários
          │             (crie perfis por função e cadastre os logins da equipe)
          ▼
[3. Equipe e Docs]      Colaboradores e Voluntários (foto + vínculo com login + crachá)
          │             Documentos e Certidões (alvarás, AVCB, CNDs, estatuto)
          ▼
[4. Operação Diária]    Escala de Plantões + Agenda Institucional + Livro de Plantão
          ▼
[5. Acompanhamento]     Dashboard Geral + Log de Auditoria + Relatórios em PDF
```

---

# 3. Dashboard Geral

**Caminho no menu:** `Geral → Dashboards → Dashboard Geral`

O Dashboard Geral oferece uma visão panorâmica e em tempo real da casa de acolhimento, adaptando automaticamente os blocos exibidos conforme as permissões do usuário logado.

## 3.1 Indicadores de Topo (KPIs)

| Card | Informação Exibida |
| --- | --- |
| **Acolhidos Ativos** | Quantidade atual de acolhidos ativos e percentual de ocupação dos leitos disponíveis |
| **Equipe e Voluntários** | Total de colaboradores, voluntários, estagiários e terceirizados ativos |
| **Plantão em Aberto** | Ocorrências ou pendências do Livro de Plantão ainda não resolvidas (destacando urgentes) |
| **Usuários Ativos** | Total de contas ativas no sistema e contagem de alertas de segurança nos últimos 7 dias |

## 3.2 Painéis Operacionais Dinâmicos

O corpo do Dashboard organiza-se em duas colunas que se ajustam automaticamente ao volume de avisos e eventos:

- **Mural do Plantão e Pendências:** destaca os avisos fixados e ocorrências em aberto do Livro de Plantão, com atalho rápido **Ver / Tratar**.
- **Atividade Recente do Sistema:** posicionada logo abaixo do Mural do Plantão, exibe as últimas movimentações do Log de Auditoria em todos os módulos.
- **Plantonistas de Hoje:** lista quem está escalado na data atual por turno e status, com acesso direto à Escala de Plantões.
- **Próximos Eventos da Agenda:** lista os próximos compromissos institucionais; se o seu login estiver vinculado ao seu cadastro de colaborador, seus compromissos recebem o selo **Você está envolvido**.
- **Alertas de Documentos e Certidões:** aponta documentos vencidos ou próximos do vencimento para ação preventiva.
- **Ocupação de Leitos, Resumo Financeiro e Acesso Rápido:** cartões gerenciais (exibidos conforme permissões do perfil) e atalhos para as principais telas do módulo.

---

# 4. Configurações Pessoais e da Instituição (Menu Engrenagem)

No canto superior direito de qualquer tela do Sistema Elo, clique no ícone de **engrenagem** para acessar as três configurações globais:

## 4.1 Alterar Senha

Permite trocar sua senha atual informando a senha vigente, a nova senha e a confirmação. A nova senha deve atender à política obrigatória do sistema:

- Mínimo de **8 caracteres**;
- Pelo menos **1 letra maiúscula** e **1 letra minúscula**;
- Pelo menos **1 número** e **1 caractere especial** (por exemplo: `!`, `@`, `#`, `$`, `%`, `*`).

## 4.2 Informações da Instituição

Tela central para registrar os dados oficiais da casa de acolhimento (**CNPJ**, **Razão Social**, **Nome Fantasia**, **E-mail**, **Telefone**, **Site** e **Endereço completo** com busca automática por **CEP** via ViaCEP). Essas informações alimentam automaticamente o verso do **Crachá Funcional**, os **Recibos de Doação** e os cabeçalhos institucionais.

## 4.3 Aparência e Tema (Claro e Escuro)

Ao clicar em **Aparência e Tema**, abre-se um seletor visual com duas opções:

- **Tema Claro (Padrão):** paleta clara de alto contraste tradicional do Sistema Elo.
- **Tema Escuro:** interface noturna pensada para reduzir fadiga visual em plantões noturnos e uso prolongado.

A troca acontece **instantaneamente** sem recarregar a página e fica **salva no banco de dados para o seu login**. Assim, ao sair e entrar novamente — ou acessar de outro computador com o mesmo usuário — o tema escolhido já é carregado automaticamente.

> **Impressão Protegida:** mesmo quando o usuário utiliza o **Tema Escuro**, a impressão de relatórios, folhas de plantão, agenda, escala e a geração/prévia de crachás mantêm sempre o padrão claro com cores reais para não desperdiçar tinta nem alterar a arte do documento.

---

# 5. Cadastros do Módulo Geral

## 5.1 Usuários

**Caminho no menu:** `Geral → Cadastros → Usuários`

Controla todas as contas que têm permissão para autenticar no Sistema Elo.

### Campos principais

| Campo | Obrigatório | Regras e Orientações |
| --- | --- | --- |
| **Nome completo** | Sim | Nome de identificação do operador (até 150 caracteres) |
| **E-mail** | Sim | Deve ser único no sistema |
| **Login** | Sim | Identificador único usado na tela de autenticação |
| **Senha** | Na criação | Opcional na edição (preencha apenas se desejar redefinir); segue a política de senha forte |
| **Perfil de acesso** | Sim | Define quais módulos e telas o usuário poderá visualizar e operar |
| **Tempo de expiração da sessão** | Sim | Intervalo entre **30 e 180 minutos** de inatividade antes do encerramento automático |
| **Usuário ativo** | Sim | Desmarque para suspender o acesso mantendo todo o histórico de auditoria |
| **Bloqueado** | Não | Impede login imediatamente sem precisar alterar a senha ou perfil |
| **Forçar troca de senha** | Não | Obriga o usuário a cadastrar uma nova senha no próximo acesso |

> **Integridade Histórica:** usuários que já criaram ou alteraram registros no sistema não podem ser excluídos para preservar a trilha de auditoria. Nesses casos, desative ou bloqueie a conta.

---

## 5.2 Perfis de Acesso

**Caminho no menu:** `Geral → Cadastros → Perfis de Acesso`

Define conjuntos reutilizáveis de permissões por módulo, categoria e tela (modelo RBAC).

### Como configurar um perfil

1. Acesse a aba **Manutenção** e informe o **Nome do perfil** (por exemplo: *Coordenação*, *Equipe de Plantão*, *Financeiro*, *Recepção*).
2. Navegue pelas abas de cada módulo (**Geral**, **Financeiro**, **Gestão de Acolhidos**, **Atendimento**, **Estoque** e **Permissões Gerais**).
3. Marque individualmente as telas permitidas ou utilize a caixa **Selecionar todas as telas do módulo** no topo da aba para marcar ou desmarcar todo o módulo de uma vez.
4. Na aba **Permissões Gerais**, controle o acesso à tela **Informações da Instituição**.
5. Clique em **Salvar Perfil**. As alterações passam a valer para todos os usuários vinculados àquele perfil.

> **Proteção do Administrador Global:** o perfil de **Administração Global** possui acesso irrestrito a todas as telas presentes e futuras e não pode ter suas permissões removidas nem ser excluído.

---

## 5.3 Colaboradores e Voluntários (e Emissão de Crachá CR80)

**Caminho no menu:** `Geral → Cadastros → Colaboradores e Voluntários`

Mantém o cadastro completo da equipe da instituição — sejam funcionários registrados, voluntários, estagiários ou prestadores terceirizados — com suporte a foto funcional, vínculo opcional ao login do sistema e emissão de crachá profissional.

### Campos do cadastro

| Bloco | Campos | Observações |
| --- | --- | --- |
| **Foto Funcional** | Upload de imagem ou Captura por Webcam | Aceita JPG, PNG ou WebP até 5 MB; armazenada em diretório privado |
| **Identificação** | Nome completo, CPF, RG, Data de nascimento | CPF validado e único; código de matrícula gerado automaticamente (`COL-0001`, etc.) |
| **Vínculo e Atuação** | Tipo de vínculo (`Colaborador`, `Voluntário`, `Estagiário`, `Terceirizado`), Cargo/Função, Registro Profissional (ex.: COREN, CRP, CRESS), Turno, Data de início e Data de desligamento | Define como a pessoa aparece na Escala de Plantões, Agenda e Crachá |
| **Contato e Endereço** | E-mail, Telefone, CEP (com busca ViaCEP), Logradouro, Número, Complemento, Bairro, Cidade e UF | Preenchimento assistido ao informar o CEP |
| **Acesso ao Sistema** | Usuário vinculado (opcional) | Permite ao sistema reconhecer o colaborador no Dashboard Geral (destaque de eventos na Agenda) |
| **Status** | Ativo / Inativo e Observações | Somente colaboradores ativos aparecem no dock da Escala de Plantões |

### Como tirar foto pela webcam ou enviar arquivo

- Clique em **Enviar foto** para selecionar uma imagem do computador, ou clique em **Usar câmera** para abrir a captura ao vivo pela webcam do computador/notebook, tirar a foto e confirmar.
- Também é possível marcar a opção de remover a foto atual durante uma edição.

### Como emitir o Crachá de Identificação Funcional (Padrão CR80)

Na aba **Pesquisa** (botão **Crachá** na linha do colaborador) ou dentro da edição do colaborador, abre-se o modal em tela ampla com **prévia fiel em tempo real** no tamanho oficial de cartão PVC **CR80 (54 × 86 mm)**, exibindo frente (nome da instituição, foto enquadrada, nome completo, cargo, matrícula e turno) e verso (matrícula, vínculo, CPF, data de início, dados da instituição e aviso de uso pessoal).

Você pode escolher entre **3 formatos de saída**:

1. **Impressão Caseira (A4 Dobrável):** gera uma folha A4 pronta para imprimir em casa ou no escritório, com frente e verso lado a lado, linha pontilhada central de dobra e borda externa tracejada de corte — basta recortar, dobrar ao meio e plastificar ou colar.
2. **Papel Adesivo (2× Frente em A4):** posiciona duas frentes idênticas lado a lado em A4 para impressão em papel adesivo fotográfico e aplicação direta em cartão PVC branco.
3. **PDF para Gráfica (CR80 + Sangria 3 mm):** baixa um arquivo PDF em alta precisão via Dompdf com página de **60 × 92 mm** (54 × 86 mm + 3 mm de sangria em todos os lados) e marcas de corte profissionais, pronto para envio direto à gráfica.

---

## 5.4 Documentos e Certidões Institucionais

**Caminho no menu:** `Geral → Cadastros → Documentos e Certidões`

Centraliza o controle de validade e o arquivo digital de todos os documentos obrigatórios da instituição, evitando vencimentos despercebidos de alvarás, licenças e certidões.

### Categorias disponíveis

- Alvará Sanitário
- AVCB / Bombeiros
- CMAS / CMDCA
- Certidão Negativa (CND)
- Estatuto / Regimento
- Ata da Diretoria
- Convênio / Parceria
- Licença / Outros

### Cadastro e anexos

- Informe **Título**, **Categoria**, **Número do documento**, **Órgão emissor**, **Data de emissão** e **Data de validade**.
- Para documentos que não vencem (como Estatuto Social ou Atas), marque **Documento permanente (sem data de validade)**.
- Defina em **Alertar com antecedência (dias)** quantos dias antes do vencimento o documento entra no status **Vencendo em breve** (padrão: **30 dias**).
- Anexe o arquivo digital em **PDF, JPG, PNG ou WebP (até 10 MB)**. O arquivo é salvo em pasta privada e pode ser visualizado diretamente em um **modal na mesma aba** (botão **Ver**) ou baixado com segurança.

### Status automáticos

| Selo | Significado |
| --- | --- |
| **Vencido** | Documento ativo cuja data de validade já passou (exibido nos alertas do Dashboard) |
| **Vencendo em breve** | Documento ativo dentro da janela de antecedência configurada (exibido nos alertas do Dashboard) |
| **Válido** | Documento ativo dentro do prazo de validade |
| **Permanente** | Documento ativo sem data de validade |
| **Desatualizado** | Documento antigo/substituído mantido apenas como histórico, **fora dos alertas de vencimento** |

### Como renovar um documento vencido (e tirá-lo do alerta)

Quando um documento vencer e a instituição emitir uma nova via, há duas maneiras práticas de atualizar sem perder o arquivo antigo:

1. **Fluxo guiado "Renovar" (recomendado):** na listagem, clique no botão **Renovar** ao lado do documento vencido (ou vencendo). O sistema abre automaticamente o formulário de novo documento já preenchido com título, categoria, órgão emissor e dias de alerta, e exibe um banner azul confirmando que, ao salvar a nova versão, o documento anterior será **automaticamente marcado como Desatualizado** e removido dos alertas.
2. **Ação rápida de 1 clique:** em qualquer linha da tabela, você pode clicar diretamente em **Desatualizado** (para arquivar um documento antigo e removê-lo imediatamente dos alertas) ou em **Reativar** (caso queira trazê-lo de volta ao monitoramento).

---

# 6. Movimentações do Módulo Geral

## 6.1 Escala de Plantões

**Caminho no menu:** `Geral → Movimentações → Escala de Plantões`

Oferece um quadro visual interativo para planejar, redistribuir e imprimir os plantões de toda a equipe ativa.

### Recursos principais

- **Duas visualizações:** alterne entre a visão **Semanal (Turnos × Dias)** — ideal para montar e revisar a cobertura dos turnos *Manhã*, *Tarde*, *Noite* e *Integral* — e a visão **Mensal** para visão ampla do mês.
- **Dock lateral da equipe:** exibe todos os colaboradores e voluntários ativos com foto, cargo e busca rápida.
- **Arrastar e soltar (Drag-and-Drop):** arraste uma pessoa do dock lateral para qualquer célula de **Turno × Dia** para criar o plantão, ou arraste um card já alocado para outra célula para mover de dia ou turno instantaneamente.
- **Criação e edição detalhada:** clique no botão **+** de qualquer célula ou clique sobre um plantão existente para ajustar **Status** (`Confirmado`, `Troca`, `Folga`, `Falta`, `Férias`), **Hora de início/fim** e **Observações**, ou excluir a alocação.
- **Copiar semana anterior:** replica rapidamente a escala da semana anterior para a semana exibida em um único clique (ignorando duplicidades já existentes).
- **Alertas automáticos:** sinaliza **turnos descobertos** (sem nenhum plantonista confirmado) e **conflitos** (mesma pessoa escalada em dois ou mais turnos no mesmo dia).
- **Impressão para mural:** botão **Imprimir Escala** gera uma folha limpa em **A4 paisagem** pronta para fixação no mural da instituição.

---

## 6.2 Agenda Institucional

**Caminho no menu:** `Geral → Movimentações → Agenda Institucional`

Calendário interativo para organizar reuniões, vistorias de órgãos públicos, audiências judiciais, treinamentos, eventos comemorativos e manutenções prediais, vinculando as pessoas envolvidas.

### Como usar o calendário

- **Navegação mensal:** utilize as setas de mês anterior/próximo ou o botão **Hoje** para voltar rapidamente à data atual.
- **Pauta lateral do dia:** clique em qualquer dia do calendário para listar à direita todos os compromissos daquela data.
- **Criar evento sem digitar data:** dê um **duplo clique** em um dia do calendário ou clique no botão **+** da célula do dia para abrir o modal já preenchido com aquela data.
- **Reagendar arrastando (Drag-and-Drop):** arraste o cartão de um evento para outro dia do calendário para remarcar a data imediatamente.
- **Vincular pessoas envolvidas:** dentro do modal do evento, use o seletor visual com busca e fotos para marcar quantos **colaboradores, voluntários, estagiários ou terceirizados** participarão.
- **Integração personalizada com o Dashboard:** quando qualquer participante vinculado acessar o sistema com o próprio login, o evento aparecerá no **Dashboard Geral** com o selo **Você está envolvido** e também poderá ser filtrado na Agenda pelo botão **Meus eventos**.
- **Filtros e impressão:** filtre por categoria ou status e imprima a agenda mensal em **A4 paisagem** pelo botão **Imprimir**.

---

## 6.3 Livro de Plantão e Avisos

**Caminho no menu:** `Geral → Movimentações → Livro de Plantão e Avisos`

Substitui o caderno físico de ocorrências por um registro rastreável de passagens de turno, ocorrências operacionais e avisos gerais.

### Tipos, prioridades e ciclo de vida

- **Tipos de registro:** `Passagem de Turno`, `Ocorrência` ou `Aviso Geral`.
- **Prioridades:** `Normal`, `Atenção` ou `Urgente`.
- **Status:** `Aberto` → `Em acompanhamento` → `Resolvido` (ao marcar como resolvido, o sistema registra automaticamente quem resolveu, data/hora e a observação de resolução).
- **Fixar no Mural:** registros marcados como **Fixado** aparecem em destaque no topo do Livro de Plantão e no **Mural do Plantão** do Dashboard Geral.
- **Vínculos opcionais:** associe facilmente um **Colaborador responsável** e/ou um **Acolhido** usando os campos com busca dinâmica (autocomplete).
- **Impressão da Folha de Plantão:** utilize o botão **Imprimir / PDF** para gerar uma folha formatada com os filtros ativos.

---

## 6.4 Log de Auditoria (Global)

**Caminho no menu:** `Geral → Movimentações → Log de Auditoria`

Registra de forma centralizada e automática todas as ações críticas de segurança e todas as alterações de dados realizadas no Sistema Elo — abrangendo **o Módulo Geral, o Módulo Financeiro, o Módulo Gestão de Acolhidos e todos os módulos futuros** (como Atendimento e Estoque).

### O que é registrado automaticamente

- **Autenticação e Segurança:** logins bem-sucedidos, tentativas falhas de login, bloqueios, logouts, alterações de senha e troca de tema.
- **Cadastros e Movimentações em todos os módulos:** criações, edições, exclusões, remanejamentos, renovações de documentos e alterações de configuração.
- **Exportações e Comprovantes:** geração de recibos de doação, crachás e relatórios em PDF.
- **Metadados técnicos:** data/hora exata, usuário responsável, módulo, ação, entidade, ID do registro, endereço IP, navegador e JSON de detalhes técnicos (nunca armazenando senhas nem tokens de segurança).

### Consulta, filtros e exportação

- Filtre por **Módulo** (lista populada dinamicamente conforme módulos cadastrados e eventos registrados), **Ação**, **Usuário** (com autocomplete), **Período (Data Inicial e Final)** e **Busca livre**.
- Clique em **Detalhes** em qualquer linha para abrir o modal com o resumo técnico completo em JSON formatado.
- Clique em **Imprimir / PDF** para emitir o relatório visual dos registros filtrados ou em **Exportar .log** para baixar o histórico filtrado no formato de texto estruturado `.log`.

### Gravação em arquivo `.log`, política de retenção e limpeza

Clique no botão **Configurações / Retenção** para gerenciar o armazenamento dos logs:

- **Espelhamento em arquivo `.log` no servidor:** quando ativado (padrão), cada evento gravado no banco também é acrescentado automaticamente no arquivo mensal `logs/auditoria-AAAA-MM.log` em pasta protegida do servidor.
- **Retenção automática (limpeza periódica):** escolha entre **Nunca apagar (manter histórico completo)**, **30**, **60**, **90**, **180**, **365 dias** ou informe um **prazo personalizado (de 7 a 3650 dias)** para remover automaticamente registros mais antigos e manter o banco leve.
- **Limpeza manual imediata:** na mesma janela, você pode executar imediatamente a remoção de registros mais antigos que o prazo configurado.
- **Exclusão seletiva:** na tabela principal, marque as caixas de seleção dos itens desejados (ou o checkbox do cabeçalho para selecionar a página) e clique em **Apagar selecionados** para remover apenas registros específicos.

> **Rastreabilidade da própria limpeza:** toda alteração de política de retenção, limpeza manual ou exclusão seletiva gera um novo evento de auditoria indicando quem executou a operação e quantos itens foram removidos.

---

# 7. Relatórios do Módulo Geral

Todos os relatórios respeitam as permissões configuradas no perfil do usuário e permitem conferência em tela antes de imprimir ou exportar em PDF.

## 7.1 Usuários por Perfis

**Caminho no menu:** `Geral → Relatórios → Usuários por Perfis`

Lista os usuários cadastrados agrupados ou filtrados por perfil de acesso, status (ativo/inativo/bloqueado) e configuração de sessão, permitindo exportação direta em PDF para conferência de contas ativas.

## 7.2 Matriz de Permissões por Perfil

**Caminho no menu:** `Geral → Relatórios → Matriz de Permissões por Perfil`

Ferramenta essencial de auditoria de acessos, oferecendo dois modos de visualização e exportação em PDF via Dompdf:

- **Modo Matriz (Telas × Perfis):** tabela cruzada comparando lado a lado quais perfis possuem acesso a cada tela do sistema.
- **Modo Detalhado por Perfil:** lista expandida de todas as telas liberadas para cada perfil selecionado.

Aceita filtros combinados por **Perfil**, **Módulo** e **Categoria** (Dashboards, Cadastros, Movimentações, Relatórios).

## 7.3 Colaboradores e Voluntários

**Caminho no menu:** `Geral → Relatórios → Colaboradores e Voluntários`

Relatório configurável completo: permite escolher quais colunas exibir, aplicar filtros (tipo de vínculo, turno, status, intervalo de datas de início/desligamento), definir agrupamentos com totalizadores, escolher ordenação, ajustar papel/orientação e gerar prévia ou download em PDF via Dompdf.

---

# 8. Perguntas Frequentes (FAQ)

### Por que uma tela não aparece no meu menu lateral?
O menu lateral é montado automaticamente de acordo com as permissões do seu **Perfil de Acesso**. Solicite ao administrador do sistema a liberação da tela desejada em **Geral → Cadastros → Perfis de Acesso**.

### Por que o Dashboard Geral não está destacando meus eventos da Agenda?
Para que o sistema reconheça quais eventos pertencem ao seu login, o seu registro em **Colaboradores e Voluntários** precisa estar vinculado ao seu usuário no campo **Usuário vinculado**.

### Se eu ativar o Tema Escuro, ele muda para os outros usuários da instituição?
Não. A preferência entre **Tema Claro** e **Tema Escuro** é salva individualmente no banco de dados para cada login.

### Como tirar um alvará ou certidão vencida do alerta do Dashboard sem perder o arquivo antigo?
Na tela **Documentos e Certidões**, clique em **Renovar** no documento vencido para cadastrar a nova via (o documento antigo será marcado como **Desatualizado** automaticamente), ou clique no botão **Desatualizado** na linha do registro antigo.

### Qual formato de crachá devo escolher?
Se você vai imprimir em impressora comum da instituição e plastificar, use **Impressão Caseira (A4 Dobrável)**. Se vai imprimir em papel adesivo para colar no PVC, escolha **Papel Adesivo (2× Frente)**. Se vai enviar o arquivo para uma gráfica produzir em PVC profissional, baixe o **PDF para Gráfica (CR80 + Sangria 3 mm)**.

### O Log de Auditoria também registra ações feitas no Financeiro e na Gestão de Acolhidos?
Sim. O motor de auditoria global do Sistema Elo monitora automaticamente todos os módulos existentes e qualquer módulo que venha a ser criado no futuro, registrando criações, edições, exclusões e exportações de relatórios/comprovantes.

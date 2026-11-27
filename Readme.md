<p align="center">
  <img src="./assets/imgs/logo-elo.png" alt="Logo Sistema Elo" width="110">
</p>

<h1 align="center">Sistema Elo</h1>

<p align="center">
  <strong>Sistema Web de Gerenciamento para Casas de Acolhimento</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.1+">
  <img src="https://img.shields.io/badge/MySQL%20%2F%20MariaDB-InnoDB-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL / MariaDB">
  <img src="https://img.shields.io/badge/Tailwind_CSS-CDN-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS">
  <img src="https://img.shields.io/badge/Dompdf-3.1-E34F26?style=flat-square" alt="Dompdf 3.1">
</p>

---

## Sobre o Projeto

O **Sistema Elo** é uma aplicação web desenvolvida para centralizar e organizar a rotina administrativa, financeira e assistencial de **casas de acolhimento**. A plataforma oferece controle de acesso granular por perfis, gestão financeira completa (incluindo metas orçamentárias e recibos de doação), acompanhamento de acolhidos, fichas médicas, ocupação de leitos e geração de relatórios personalizados em PDF.

---

## Tecnologias Utilizadas

- **Back-end:** PHP 8.1+ com PDO (`utf8mb4`, prepared statements e transações SQL)
- **Banco de Dados:** MySQL / MariaDB (`db_elo`, engine `InnoDB`)
- **Front-end:** HTML5, Tailwind CSS (CDN com tema personalizado), JavaScript Vanilla (ES6+), Google Fonts (*Poppins*) e Phosphor Icons
- **Geração de PDF:** [Dompdf](https://github.com/dompdf/dompdf) (`^3.1`) via Composer
- **Integrações Externas:** API [ViaCEP](https://viacep.com.br/) para preenchimento automático de endereços

---

## Funcionalidades Implementadas

### Segurança, Autenticação e Controle de Acesso (RBAC)

- **Autenticação segura:** login com validação via `password_hash`/`password_verify`, cookies `HttpOnly` + `SameSite=Lax` e proteção **CSRF** em todas as requisições POST.
- **Sessões isoladas por aba:** controle de sessão individual por aba (`tabToken` + `BroadcastChannel`), evitando conflitos entre abas simultâneas.
- **Gestão de sessão e senhas:**
  - Tempo de expiração de sessão configurável por usuário (30 a 180 minutos), com renovação controlada.
  - Política de senha forte (mínimo de 8 caracteres com letra maiúscula, minúscula, número e caractere especial).
  - Suporte a bloqueio de conta, ativação/inativação e troca obrigatória de senha no acesso.
- **Permissões granulares (RBAC):** controle de acesso por módulo e por tela armazenado em JSON (`tbPerfilAcesso`), com perfil protegido de **Administração Global**.
- **Navegação inteligente:** menu lateral gerado dinamicamente conforme as permissões do perfil, com busca em tempo real (sem distinção de acentos).
- **Auditoria:** registro de usuário e data/hora de criação e alteração nos cadastros e movimentações.
- **Configurações Pessoais e da Instituição:** modal para alteração da própria senha e manutenção dos **Dados da Instituição** (CNPJ, razão social, nome fantasia, contato e endereço via ViaCEP), utilizados automaticamente em recibos e documentos.

---

### Módulo Geral

- **Cadastros**
  - **Usuários:** inclusão, edição, ativação/inativação, bloqueio de acesso, definição de perfil, troca forçada de senha e tempo de expiração da sessão.
  - **Perfis de Acesso:** gerenciamento completo de perfis com matriz de permissões por módulo, categoria e tela.
  - **Colaboradores e Voluntários:** cadastro da equipe da instituição (colaboradores, voluntários, estagiários e terceirizados) com validação de CPF, cargo/função, registro profissional, turno, endereço via ViaCEP e vínculo opcional com usuário do sistema.
- **Movimentações**
  - **Log de Auditoria:** trilha somente leitura de eventos de autenticação, segurança e alterações administrativas (logins, falhas, bloqueios, trocas de senha, cadastros/edições/exclusões e alterações da instituição), com cards de resumo, filtros combinados (incluindo combobox de usuário com autocomplete), paginação configurável, modal de detalhes técnicos e impressão/PDF da filtragem.
  - **Livro de Plantão e Avisos:** registro de passagens de turno, ocorrências e avisos gerais com mural de avisos fixados no topo, prioridade, acompanhamento de status/resolução, vínculo opcional com colaborador e acolhido, filtros e impressão/PDF da folha de plantão.
- **Relatórios**
  - **Usuários por Perfis:** consulta filtrada e exportação em PDF.
  - **Matriz de Permissões por Perfil:** relatório em modo comparativo (Telas × Perfis) ou detalhado por perfil, com filtros por perfil, módulo e categoria, prévia de impressão e exportação em PDF via Dompdf.
  - **Colaboradores e Voluntários:** relatório configurável com filtros dinâmicos, agrupamentos, ordenação, prévia de impressão e exportação em PDF via Dompdf.
- **Dashboard**
  - **Dashboard Geral:** visão executiva integrada com indicadores de acolhidos ativos, ocupação de leitos, equipe/voluntários, pendências do plantão, usuários e alertas de segurança, mural de avisos fixados, resumo financeiro do mês (respeitando permissões), atividade recente de auditoria e atalhos rápidos.

---

### Módulo Financeiro

- **Dashboard Financeiro**
  - Indicadores do mês atual: saldo acumulado, entradas efetivadas, saídas efetivadas, saldo mensal e resumo de pendências.
  - Comparativo histórico dos últimos 6 meses (entradas × saídas efetivadas).
  - Gráfico de distribuição de saídas por categoria no mês vigente.
  - Barra de acompanhamento da **meta orçamentária mensal**.
  - Listagem rápida dos lançamentos mais recentes.
- **Cadastros**
  - **Categorias de Lançamentos:** classificação por natureza (`Entrada` ou `Saída`), descrição e status.
  - **Fontes de Recursos:** controle das origens dos recursos financeiros.
  - **Formas de Pagamento:** cadastro dos meios de pagamento e recebimento.
  - **Fornecedores/Prestadores:** cadastro para Pessoa Física ou Jurídica com CPF/CNPJ único, dados de contato e observações.
  - **Doadores/Contribuintes:** cadastro para Pessoa Física ou Jurídica com CPF/CNPJ único e endereço assistido via ViaCEP.
- **Movimentações**
  - **Lançamentos Financeiros:** controle de receitas e despesas com status (`Pendente` ou `Efetivado`) e vínculos com categoria, fonte de recurso, forma de pagamento, fornecedor ou doador.
  - **Recibo de Doação (PDF):** emissão de comprovante numerado (`REC-AAAA-NNNNNN`) via Dompdf para doações efetivadas com doador identificado, preenchido com os dados da instituição e do doador.
  - **Metas Orçamentárias:** definição de valor orçado por competência (ano/mês) com detalhamento de despesas efetivadas e pendentes.
- **Relatórios (com filtros, agrupamentos e exportação em PDF)**
  - Categorias de Lançamentos
  - Fontes de Recursos
  - Formas de Pagamento
  - Fornecedores/Prestadores
  - Doadores/Contribuintes
  - Extrato de Lançamentos Financeiros
  - Resumo de Metas Orçamentárias

---

### Módulo Gestão de Acolhidos

- **Cadastros**
  - **Acolhidos e Fichas Médicas:**
    - Cadastro completo com dados pessoais, validação de CPF, filiação (incluindo marcação de mãe/pai desconhecidos), situação de moradia/rua e endereço com busca ViaCEP.
    - Upload validado de foto do acolhido (JPEG, PNG ou WebP até 5 MB), armazenada em diretório privado (`uploads/acolhidos/`) e servida apenas para sessões autenticadas.
    - Histórico de **Fichas Médicas** integrado ao cadastro do acolhido, registrando tipo sanguíneo, alergias, dependências e relevâncias clínicas em formato de tags.
  - **Blocos:** organização estrutural dos blocos da casa de acolhimento.
  - **Quartos:** gerenciamento de quartos vinculados aos blocos.
  - **Leitos:** controle individual de leitos vinculados aos quartos com status (`Ativo`, `Manutenção` ou `Inativo`).
- **Movimentações**
  - **Acolhimentos:** registro de admissões e desligamentos (datas e motivos de entrada/saída), inventário de pertences, dados do responsável judicial, número do processo judicial e observações gerais, com busca dinâmica de acolhidos.
  - **Acomodações dos Acolhidos:** alocação e transferência transacional de acolhidos ativos em leitos disponíveis, com bloqueio de concorrência (`FOR UPDATE`) e *snapshot* de bloco, quarto e leito para preservação do histórico de ocupação.
- **Relatórios (motor configurável com exportação em PDF)**
  - Suporte a filtros dinâmicos (texto, opções e intervalo de datas), seleção de atributos/colunas, agrupamento com totalizador, ordenação, estilização em zebra e configuração de papel, orientação e margens:
  - Acolhidos
  - Fichas Médicas
  - Acolhimentos
  - Blocos
  - Quartos
  - Leitos
  - Acomodações dos Acolhidos
- **Dashboard**
  - Estrutura inicial do **Dashboard Gestão de Acolhidos** criada para futuros indicadores de ocupação e acolhimentos.

---

### Módulos Previstos / Em Estruturação

- **Atendimento** (`pages/atendimento/`): estrutura de navegação, permissões e dashboard inicial prontos para desenvolvimento.
- **Estoque** (`pages/estoque/`): módulo previsto no catálogo de permissões e estrutura de pastas.

---

## Estrutura do Projeto

```text
Sistema_Elo/
├── assets/
│   ├── css/                  # Folhas de estilo específicas
│   ├── imgs/                 # Logotipo e imagens estáticas
│   └── js/                   # Scripts de interface, sessões por aba, combobox e Tailwind config
├── auth/                     # Login, logout, troca de senha, controle RBAC e entrega segura de fotos
├── components/               # Navbars, sidebars modulares, toasts, modais e motor de relatórios
├── db/
│   ├── migrations/           # Scripts SQL incrementais de evolução do banco
│   ├── conexao.php           # Configuração da conexão PDO com MySQL/MariaDB
│   ├── schema.sql            # Estrutura completa das tabelas do sistema
│   └── setup.php             # Script CLI para criar tabelas e o Administrador Global
├── pages/
│   ├── atendimento/          # Módulo de Atendimento (em estruturação)
│   ├── estoque/              # Módulo de Estoque (em estruturação)
│   ├── financeiro/           # Dashboard, cadastros, movimentações, recibos e relatórios financeiros
│   ├── geral/                # Dashboard, usuários, perfis de acesso e relatórios gerais
│   ├── gestao-acolhidos/     # Dashboard, acolhidos, fichas médicas, estrutura física, acolhimentos, acomodações e relatórios
│   └── informacoes-instituicao.php
├── uploads/
│   └── acolhidos/            # Armazenamento privado das fotos dos acolhidos (.htaccess protegido)
├── composer.json             # Dependências PHP (dompdf/dompdf)
└── Readme.md
```

CREATE TABLE IF NOT EXISTS tbPerfilAcesso (
    idPerfil INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nomePerfil VARCHAR(100) NOT NULL,
    permissoes JSON NOT NULL,
    administradorGlobal TINYINT(1) NOT NULL DEFAULT 0,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idPerfil),
    UNIQUE KEY uq_tbPerfilAcesso_nomePerfil (nomePerfil)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbUsuario (
    idUsuario INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nomeCompleto VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    login VARCHAR(80) NOT NULL,
    senhaHash VARCHAR(255) NOT NULL,
    idPerfil INT UNSIGNED NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    bloqueado TINYINT(1) NOT NULL DEFAULT 0,
    forcarTrocaSenha TINYINT(1) NOT NULL DEFAULT 0,
    tempoExpiracaoSessao SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idUsuario),
    UNIQUE KEY uq_tbUsuario_email (email),
    UNIQUE KEY uq_tbUsuario_login (login),
    KEY idx_tbUsuario_idPerfil (idPerfil),
    CONSTRAINT fk_tbUsuario_tbPerfilAcesso
        FOREIGN KEY (idPerfil) REFERENCES tbPerfilAcesso (idPerfil)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbCategoriaLancamentos(
    idCategoria INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nomeCategoria VARCHAR(100) NOT NULL,
    naturezaCategoria ENUM('Entrada', 'Saída') NOT NULL,
    descricaoCategoria VARCHAR(255) NULL,
    statusCategoria TINYINT(1) NOT NULL DEFAULT 1,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idCategoria),
    UNIQUE KEY uq_tbCategoriaLancamentos_nomeNatureza (nomeCategoria, naturezaCategoria),
    KEY idx_tbCategoriaLancamentos_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbCategoriaLancamentos_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbCategoriaLancamentos_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbCategoriaLancamentos_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbFonteRecurso (
    idFonte INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nomeFonte VARCHAR(100) NOT NULL,
    descricaoFonte VARCHAR(255) NULL,
    statusFonte TINYINT(1) NOT NULL DEFAULT 1,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idFonte),
    UNIQUE KEY uq_tbFonteRecurso_nomeFonte (nomeFonte),
    KEY idx_tbFonteRecurso_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbFonteRecurso_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbFonteRecurso_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbFonteRecurso_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbInformacoesInstituicao (
    idInformacoes TINYINT UNSIGNED NOT NULL,
    cnpj VARCHAR(14) NULL,
    razaoSocial VARCHAR(150) NULL,
    nomeFantasia VARCHAR(150) NULL,
    email VARCHAR(190) NULL,
    telefone VARCHAR(30) NULL,
    cep VARCHAR(8) NULL,
    logradouro VARCHAR(150) NULL,
    numero VARCHAR(20) NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    uf CHAR(2) NULL,
    camposEnderecoViaCep JSON NULL,
    site VARCHAR(255) NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idInformacoes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbFormaPagamento (
    idFormaPagamento INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nomeFormaPagamento VARCHAR(100) NOT NULL,
    descricaoFormaPagamento VARCHAR(255) NULL,
    statusFormaPagamento TINYINT(1) NOT NULL DEFAULT 1,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idFormaPagamento),
    UNIQUE KEY uq_tbFormaPagamento_nomeFormaPagamento (nomeFormaPagamento),
    KEY idx_tbFormaPagamento_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbFormaPagamento_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbFormaPagamento_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbFormaPagamento_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbFornecedorPrestador (
    idFornecedorPrestador INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipoPessoa ENUM('fisica', 'juridica') NOT NULL,
    documento VARCHAR(14) NOT NULL,
    nomeRazaoSocial VARCHAR(150) NOT NULL,
    nomeFantasia VARCHAR(150) NULL,
    nomeContato VARCHAR(150) NULL,
    email VARCHAR(190) NULL,
    telefone VARCHAR(20) NULL,
    observacoes VARCHAR(255) NULL,
    statusFornecedor TINYINT(1) NOT NULL DEFAULT 1,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idFornecedorPrestador),
    UNIQUE KEY uq_tbFornecedorPrestador_documento (documento),
    KEY idx_tbFornecedorPrestador_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbFornecedorPrestador_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbFornecedorPrestador_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbFornecedorPrestador_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbDoadorContribuinte (
    idDoadorContribuinte INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipoPessoa ENUM('fisica', 'juridica') NOT NULL,
    documento VARCHAR(14) NOT NULL,
    nomeRazaoSocial VARCHAR(150) NOT NULL,
    nomeFantasia VARCHAR(150) NULL,
    email VARCHAR(190) NULL,
    telefone VARCHAR(20) NULL,
    cep VARCHAR(8) NULL,
    logradouro VARCHAR(150) NULL,
    numero VARCHAR(20) NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    uf CHAR(2) NULL,
    observacoes VARCHAR(255) NULL,
    statusDoador TINYINT(1) NOT NULL DEFAULT 1,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idDoadorContribuinte),
    UNIQUE KEY uq_tbDoadorContribuinte_documento (documento),
    KEY idx_tbDoadorContribuinte_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbDoadorContribuinte_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbDoadorContribuinte_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbDoadorContribuinte_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbLancamentoFinanceiro (
    idLancamento INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dataLancamento DATE NOT NULL,
    descricaoLancamento VARCHAR(255) NOT NULL,
    valorLancamento DECIMAL(14,2) UNSIGNED NOT NULL,
    statusLancamento ENUM('Pendente', 'Efetivado') NOT NULL DEFAULT 'Pendente',
    idCategoria INT UNSIGNED NOT NULL,
    idFonte INT UNSIGNED NULL,
    idFormaPagamento INT UNSIGNED NULL,
    idFornecedorPrestador INT UNSIGNED NULL,
    idDoadorContribuinte INT UNSIGNED NULL,
    observacoes VARCHAR(255) NULL,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idLancamento),
    KEY idx_tbLancamentoFinanceiro_data_status (dataLancamento, statusLancamento),
    KEY idx_tbLancamentoFinanceiro_idCategoria_data (idCategoria, dataLancamento),
    KEY idx_tbLancamentoFinanceiro_idFonte (idFonte),
    KEY idx_tbLancamentoFinanceiro_idFormaPagamento (idFormaPagamento),
    KEY idx_tbLancamentoFinanceiro_idFornecedorPrestador (idFornecedorPrestador),
    KEY idx_tbLancamentoFinanceiro_idDoadorContribuinte (idDoadorContribuinte),
    KEY idx_tbLancamentoFinanceiro_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbLancamentoFinanceiro_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT chk_tbLancamentoFinanceiro_valor CHECK (valorLancamento > 0),
    CONSTRAINT fk_tbLancamentoFinanceiro_categoria
        FOREIGN KEY (idCategoria) REFERENCES tbCategoriaLancamentos (idCategoria)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbLancamentoFinanceiro_fonte
        FOREIGN KEY (idFonte) REFERENCES tbFonteRecurso (idFonte)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbLancamentoFinanceiro_formaPagamento
        FOREIGN KEY (idFormaPagamento) REFERENCES tbFormaPagamento (idFormaPagamento)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbLancamentoFinanceiro_fornecedorPrestador
        FOREIGN KEY (idFornecedorPrestador) REFERENCES tbFornecedorPrestador (idFornecedorPrestador)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbLancamentoFinanceiro_doadorContribuinte
        FOREIGN KEY (idDoadorContribuinte) REFERENCES tbDoadorContribuinte (idDoadorContribuinte)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbLancamentoFinanceiro_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbLancamentoFinanceiro_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbMetaOrcamentaria (
    idMetaOrcamentaria INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ano SMALLINT UNSIGNED NOT NULL,
    mes TINYINT UNSIGNED NOT NULL,
    valorOrcado DECIMAL(14,2) UNSIGNED NOT NULL,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idMetaOrcamentaria),
    UNIQUE KEY uq_tbMetaOrcamentaria_ano_mes (ano, mes),
    KEY idx_tbMetaOrcamentaria_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbMetaOrcamentaria_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT chk_tbMetaOrcamentaria_mes CHECK (mes BETWEEN 1 AND 12),
    CONSTRAINT chk_tbMetaOrcamentaria_valor CHECK (valorOrcado >= 0),
    CONSTRAINT fk_tbMetaOrcamentaria_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbMetaOrcamentaria_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbAcolhido (
    idAcolhido INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nomeCompleto VARCHAR(150) NOT NULL,
    cpf CHAR(11) NULL,
    rg VARCHAR(30) NULL,
    dataNascimento DATE NOT NULL,
    sexo ENUM('Feminino', 'Masculino', 'Outro', 'Não informado') NOT NULL DEFAULT 'Não informado',
    nomeMae VARCHAR(150) NULL,
    maeDesconhecida TINYINT(1) NOT NULL DEFAULT 0,
    nomePai VARCHAR(150) NULL,
    paiDesconhecido TINYINT(1) NOT NULL DEFAULT 0,
    caminhoFoto VARCHAR(255) NULL,
    moradorRua TINYINT(1) NOT NULL DEFAULT 0,
    localMoradia VARCHAR(255) NULL,
    cep CHAR(8) NULL,
    logradouro VARCHAR(150) NULL,
    numero VARCHAR(20) NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    uf CHAR(2) NULL,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idAcolhido),
    UNIQUE KEY uq_tbAcolhido_cpf (cpf),
    KEY idx_tbAcolhido_nomeCompleto (nomeCompleto),
    KEY idx_tbAcolhido_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbAcolhido_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbAcolhido_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbAcolhido_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbAcolhimento (
    idAcolhimento INT UNSIGNED NOT NULL AUTO_INCREMENT,
    idAcolhido INT UNSIGNED NOT NULL,
    dataEntrada DATE NOT NULL,
    dataSaida DATE NULL,
    motivoEntrada VARCHAR(500) NOT NULL,
    motivoSaida VARCHAR(500) NULL,
    inventarioPertences TEXT NOT NULL,
    responsavelJudicial VARCHAR(150) NULL,
    parentescoResponsavel VARCHAR(80) NULL,
    documentoResponsavel VARCHAR(30) NULL,
    telefoneResponsavel VARCHAR(30) NULL,
    processoJudicial VARCHAR(100) NULL,
    observacoesGerais TEXT NULL,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idAcolhimento),
    KEY idx_tbAcolhimento_acolhido_entrada (idAcolhido, dataEntrada, idAcolhimento),
    KEY idx_tbAcolhimento_dataSaida (dataSaida),
    KEY idx_tbAcolhimento_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbAcolhimento_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbAcolhimento_acolhido
        FOREIGN KEY (idAcolhido) REFERENCES tbAcolhido (idAcolhido)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbAcolhimento_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbAcolhimento_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbBloco (
    idBloco INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nomeBloco VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idBloco),
    UNIQUE KEY uq_tbBloco_nomeBloco (nomeBloco),
    KEY idx_tbBloco_ativo (ativo),
    KEY idx_tbBloco_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbBloco_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbBloco_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbBloco_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbQuarto (
    idQuarto INT UNSIGNED NOT NULL AUTO_INCREMENT,
    idBloco INT UNSIGNED NOT NULL,
    identificacaoQuarto VARCHAR(30) NOT NULL,
    descricao VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idQuarto),
    UNIQUE KEY uq_tbQuarto_bloco_identificacao (idBloco, identificacaoQuarto),
    KEY idx_tbQuarto_bloco_ativo (idBloco, ativo),
    KEY idx_tbQuarto_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbQuarto_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbQuarto_bloco
        FOREIGN KEY (idBloco) REFERENCES tbBloco (idBloco)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbQuarto_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbQuarto_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbLeito (
    idLeito INT UNSIGNED NOT NULL AUTO_INCREMENT,
    idQuarto INT UNSIGNED NOT NULL,
    identificacaoLeito VARCHAR(30) NOT NULL,
    statusLeito ENUM('Ativo', 'Manutenção', 'Inativo') NOT NULL DEFAULT 'Ativo',
    descricao VARCHAR(255) NULL,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idLeito),
    UNIQUE KEY uq_tbLeito_quarto_identificacao (idQuarto, identificacaoLeito),
    KEY idx_tbLeito_quarto_status (idQuarto, statusLeito),
    KEY idx_tbLeito_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbLeito_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbLeito_quarto
        FOREIGN KEY (idQuarto) REFERENCES tbQuarto (idQuarto)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbLeito_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbLeito_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbAcomodacaoAcolhido (
    idAcomodacao INT UNSIGNED NOT NULL AUTO_INCREMENT,
    idAcolhimento INT UNSIGNED NOT NULL,
    idLeito INT UNSIGNED NOT NULL,
    blocoSnapshot VARCHAR(100) NOT NULL,
    quartoSnapshot VARCHAR(30) NOT NULL,
    leitoSnapshot VARCHAR(30) NOT NULL,
    inicioEm DATETIME NOT NULL,
    fimEm DATETIME NULL,
    observacoes VARCHAR(500) NULL,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idAcomodacao),
    KEY idx_tbAcomodacaoAcolhido_acolhimento_inicio (idAcolhimento, inicioEm),
    KEY idx_tbAcomodacaoAcolhido_leito_fim (idLeito, fimEm),
    KEY idx_tbAcomodacaoAcolhido_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbAcomodacaoAcolhido_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbAcomodacaoAcolhido_acolhimento
        FOREIGN KEY (idAcolhimento) REFERENCES tbAcolhimento (idAcolhimento)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbAcomodacaoAcolhido_leito
        FOREIGN KEY (idLeito) REFERENCES tbLeito (idLeito)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbAcomodacaoAcolhido_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbAcomodacaoAcolhido_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbFichaMedica (
    idFichaMedica INT UNSIGNED NOT NULL AUTO_INCREMENT,
    idAcolhido INT UNSIGNED NOT NULL,
    tipoSanguineo ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Não informado') NOT NULL DEFAULT 'Não informado',
    alergias TEXT NOT NULL,
    dependencias TEXT NOT NULL,
    relevanciasClinicas TEXT NOT NULL,
    dataRegistro DATE NOT NULL,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idFichaMedica),
    KEY idx_tbFichaMedica_acolhido_data (idAcolhido, dataRegistro, idFichaMedica),
    KEY idx_tbFichaMedica_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbFichaMedica_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbFichaMedica_acolhido
        FOREIGN KEY (idAcolhido) REFERENCES tbAcolhido (idAcolhido)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbFichaMedica_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbFichaMedica_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
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

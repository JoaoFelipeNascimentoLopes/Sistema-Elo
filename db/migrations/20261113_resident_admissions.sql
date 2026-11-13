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

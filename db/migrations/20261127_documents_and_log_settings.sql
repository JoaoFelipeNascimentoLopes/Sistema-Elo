CREATE TABLE IF NOT EXISTS tbDocumentoInstitucional (
    idDocumento INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(150) NOT NULL,
    categoria ENUM('Alvará Sanitário', 'AVCB / Bombeiros', 'CMAS / CMDCA', 'Certidão Negativa (CND)', 'Estatuto / Regimento', 'Ata da Diretoria', 'Convênio / Parceria', 'Licença / Outros') NOT NULL DEFAULT 'Certidão Negativa (CND)',
    numeroDocumento VARCHAR(80) NULL,
    orgaoEmissor VARCHAR(120) NULL,
    dataEmissao DATE NULL,
    dataValidade DATE NULL,
    semValidade TINYINT(1) NOT NULL DEFAULT 0,
    diasAlertaAntes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    caminhoArquivo VARCHAR(255) NULL,
    nomeOriginalArquivo VARCHAR(255) NULL,
    observacoes VARCHAR(500) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idDocumento),
    KEY idx_tbDocumentoInstitucional_validade_ativo (ativo, semValidade, dataValidade),
    KEY idx_tbDocumentoInstitucional_categoria (categoria),
    KEY idx_tbDocumentoInstitucional_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbDocumentoInstitucional_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbDocumentoInstitucional_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbDocumentoInstitucional_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbConfiguracaoAuditoria (
    idConfiguracao TINYINT UNSIGNED NOT NULL,
    gravarArquivoLog TINYINT(1) NOT NULL DEFAULT 1,
    retencaoDias SMALLINT UNSIGNED NULL DEFAULT NULL,
    ultimaLimpezaEm DATETIME NULL DEFAULT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL DEFAULT NULL,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idConfiguracao),
    CONSTRAINT fk_tbConfiguracaoAuditoria_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tbConfiguracaoAuditoria (idConfiguracao, gravarArquivoLog, retencaoDias)
VALUES (1, 1, NULL);

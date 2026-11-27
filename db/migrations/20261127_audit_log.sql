CREATE TABLE IF NOT EXISTS tbLogAuditoria (
    idLog BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dataHora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    idUsuario INT UNSIGNED NULL,
    loginInformado VARCHAR(80) NULL,
    modulo VARCHAR(50) NOT NULL,
    acao VARCHAR(50) NOT NULL,
    entidade VARCHAR(80) NULL,
    idRegistro VARCHAR(64) NULL,
    descricao VARCHAR(255) NOT NULL,
    detalhesJson JSON NULL,
    enderecoIp VARCHAR(45) NULL,
    userAgent VARCHAR(255) NULL,
    PRIMARY KEY (idLog),
    KEY idx_tbLogAuditoria_dataHora (dataHora),
    KEY idx_tbLogAuditoria_usuario_data (idUsuario, dataHora),
    KEY idx_tbLogAuditoria_modulo_acao (modulo, acao),
    CONSTRAINT fk_tbLogAuditoria_usuario
        FOREIGN KEY (idUsuario) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

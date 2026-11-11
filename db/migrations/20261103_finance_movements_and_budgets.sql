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

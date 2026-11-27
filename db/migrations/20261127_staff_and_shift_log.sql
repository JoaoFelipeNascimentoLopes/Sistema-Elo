CREATE TABLE IF NOT EXISTS tbColaborador (
    idColaborador INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nomeCompleto VARCHAR(150) NOT NULL,
    cpf CHAR(11) NOT NULL,
    rg VARCHAR(30) NULL,
    dataNascimento DATE NULL,
    tipoVinculo ENUM('Colaborador', 'Voluntário', 'Estagiário', 'Terceirizado') NOT NULL DEFAULT 'Colaborador',
    cargoFuncao VARCHAR(100) NOT NULL,
    registroProfissional VARCHAR(50) NULL,
    turno ENUM('Manhã', 'Tarde', 'Noite', 'Integral', 'Plantão 12x36', 'Flexível') NOT NULL DEFAULT 'Integral',
    dataInicio DATE NOT NULL,
    dataDesligamento DATE NULL,
    email VARCHAR(190) NULL,
    telefone VARCHAR(20) NULL,
    cep CHAR(8) NULL,
    logradouro VARCHAR(150) NULL,
    numero VARCHAR(20) NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    uf CHAR(2) NULL,
    idUsuario INT UNSIGNED NULL,
    observacoes VARCHAR(500) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idColaborador),
    UNIQUE KEY uq_tbColaborador_cpf (cpf),
    UNIQUE KEY uq_tbColaborador_idUsuario (idUsuario),
    KEY idx_tbColaborador_tipo_ativo (tipoVinculo, ativo),
    KEY idx_tbColaborador_nomeCompleto (nomeCompleto),
    KEY idx_tbColaborador_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbColaborador_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbColaborador_usuario
        FOREIGN KEY (idUsuario) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_tbColaborador_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbColaborador_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbRegistroPlantao (
    idRegistroPlantao INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipoRegistro ENUM('Passagem de Turno', 'Ocorrência', 'Aviso Geral') NOT NULL DEFAULT 'Passagem de Turno',
    prioridade ENUM('Normal', 'Atenção', 'Urgente') NOT NULL DEFAULT 'Normal',
    statusRegistro ENUM('Aberto', 'Em acompanhamento', 'Resolvido') NOT NULL DEFAULT 'Aberto',
    fixado TINYINT(1) NOT NULL DEFAULT 0,
    dataOcorrencia DATE NOT NULL,
    turno ENUM('Manhã', 'Tarde', 'Noite', 'Integral') NOT NULL DEFAULT 'Manhã',
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT NOT NULL,
    idColaborador INT UNSIGNED NULL,
    idAcolhido INT UNSIGNED NULL,
    observacaoResolucao VARCHAR(500) NULL,
    resolvidoPor INT UNSIGNED NULL,
    resolvidoEm DATETIME NULL,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idRegistroPlantao),
    KEY idx_tbRegistroPlantao_data_turno (dataOcorrencia, turno),
    KEY idx_tbRegistroPlantao_status_prioridade (statusRegistro, prioridade),
    KEY idx_tbRegistroPlantao_fixado (fixado),
    KEY idx_tbRegistroPlantao_idColaborador (idColaborador),
    KEY idx_tbRegistroPlantao_idAcolhido (idAcolhido),
    KEY idx_tbRegistroPlantao_resolvidoPor (resolvidoPor),
    KEY idx_tbRegistroPlantao_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbRegistroPlantao_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbRegistroPlantao_colaborador
        FOREIGN KEY (idColaborador) REFERENCES tbColaborador (idColaborador)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_tbRegistroPlantao_acolhido
        FOREIGN KEY (idAcolhido) REFERENCES tbAcolhido (idAcolhido)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbRegistroPlantao_resolvidoPor
        FOREIGN KEY (resolvidoPor) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_tbRegistroPlantao_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbRegistroPlantao_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

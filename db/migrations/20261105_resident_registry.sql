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

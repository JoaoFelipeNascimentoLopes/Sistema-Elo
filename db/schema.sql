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

ALTER TABLE tbColaborador
    ADD COLUMN caminhoFoto VARCHAR(255) NULL AFTER dataNascimento;

CREATE TABLE IF NOT EXISTS tbEscalaPlantao (
    idEscala INT UNSIGNED NOT NULL AUTO_INCREMENT,
    idColaborador INT UNSIGNED NOT NULL,
    dataPlantao DATE NOT NULL,
    turno ENUM('Manhã', 'Tarde', 'Noite', 'Integral') NOT NULL DEFAULT 'Manhã',
    statusEscala ENUM('Confirmado', 'Troca', 'Folga', 'Falta', 'Férias') NOT NULL DEFAULT 'Confirmado',
    horaInicio TIME NULL,
    horaFim TIME NULL,
    observacoes VARCHAR(255) NULL,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idEscala),
    UNIQUE KEY uq_tbEscalaPlantao_colab_data_turno (idColaborador, dataPlantao, turno),
    KEY idx_tbEscalaPlantao_data_turno (dataPlantao, turno),
    KEY idx_tbEscalaPlantao_status (statusEscala),
    KEY idx_tbEscalaPlantao_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbEscalaPlantao_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbEscalaPlantao_colaborador
        FOREIGN KEY (idColaborador) REFERENCES tbColaborador (idColaborador)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbEscalaPlantao_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbEscalaPlantao_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

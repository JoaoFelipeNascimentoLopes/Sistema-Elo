CREATE TABLE IF NOT EXISTS tbAgendaEvento (
    idEvento INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(150) NOT NULL,
    categoria ENUM('Reunião', 'Vistoria / Órgão Público', 'Audiência / Judicial', 'Treinamento', 'Evento / Comemorativo', 'Manutenção', 'Outro') NOT NULL DEFAULT 'Reunião',
    statusEvento ENUM('Agendado', 'Confirmado', 'Concluído', 'Cancelado') NOT NULL DEFAULT 'Agendado',
    dataEvento DATE NOT NULL,
    diaInteiro TINYINT(1) NOT NULL DEFAULT 0,
    horaInicio TIME NULL,
    horaFim TIME NULL,
    localEvento VARCHAR(150) NULL,
    descricao TEXT NULL,
    idUsuarioCriacao INT UNSIGNED NOT NULL,
    idUsuarioAlteracao INT UNSIGNED NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idEvento),
    KEY idx_tbAgendaEvento_data_status (dataEvento, statusEvento),
    KEY idx_tbAgendaEvento_categoria (categoria),
    KEY idx_tbAgendaEvento_idUsuarioCriacao (idUsuarioCriacao),
    KEY idx_tbAgendaEvento_idUsuarioAlteracao (idUsuarioAlteracao),
    CONSTRAINT fk_tbAgendaEvento_usuarioCriacao
        FOREIGN KEY (idUsuarioCriacao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tbAgendaEvento_usuarioAlteracao
        FOREIGN KEY (idUsuarioAlteracao) REFERENCES tbUsuario (idUsuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbAgendaEventoParticipante (
    idEvento INT UNSIGNED NOT NULL,
    idColaborador INT UNSIGNED NOT NULL,
    criadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (idEvento, idColaborador),
    KEY idx_tbAgendaEventoParticipante_colaborador (idColaborador),
    CONSTRAINT fk_tbAgendaEventoParticipante_evento
        FOREIGN KEY (idEvento) REFERENCES tbAgendaEvento (idEvento)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_tbAgendaEventoParticipante_colaborador
        FOREIGN KEY (idColaborador) REFERENCES tbColaborador (idColaborador)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tbUsuario
    ADD COLUMN tema ENUM('claro', 'escuro') NOT NULL DEFAULT 'claro' AFTER tempoExpiracaoSessao;

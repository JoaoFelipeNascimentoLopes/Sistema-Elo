-- Adiciona anexo de comprovante / nota fiscal aos lançamentos financeiros
ALTER TABLE tbLancamentoFinanceiro
    ADD COLUMN IF NOT EXISTS caminhoComprovante VARCHAR(255) NULL AFTER observacoes,
    ADD COLUMN IF NOT EXISTS nomeOriginalComprovante VARCHAR(255) NULL AFTER caminhoComprovante;

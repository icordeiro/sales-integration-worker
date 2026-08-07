CREATE UNIQUE INDEX IF NOT EXISTS
    uq_exportacao_normal_movimento_ativo
ON exportacao_execucao (
    data_movimento
)
WHERE
    tipo_execucao = 'NORMAL'
    AND status IN (
        'AGUARDANDO',
        'CONSULTANDO',
        'GERANDO_ARQUIVO',
        'VALIDANDO',
        'ENVIANDO',
        'CONFIRMANDO_ENVIO',
        'CONCLUIDO'
    );
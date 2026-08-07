CREATE TABLE IF NOT EXISTS exportacao_execucao
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    data_movimento TEXT NOT NULL,

    tipo_execucao TEXT NOT NULL
        DEFAULT 'NORMAL',

    execucao_origem_id INTEGER NULL,

    status TEXT NOT NULL,

    arquivo_nome TEXT NULL,

    quantidade_registros INTEGER NULL,

    tamanho_bytes INTEGER NULL,

    sha256 TEXT NULL,

    caminho_local TEXT NULL,

    caminho_remoto TEXT NULL,

    erro_mensagem TEXT NULL,

    iniciado_em TEXT NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    concluido_em TEXT NULL,

    duracao_milisegundos INTEGER NULL,

    criado_em TEXT NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    atualizado_em TEXT NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (
        execucao_origem_id
    )
    REFERENCES exportacao_execucao (id),

    CHECK (
        tipo_execucao IN (
            'NORMAL',
            'REPROCESSAMENTO',
            'REENVIO'
        )
    ),

    CHECK (
        status IN (
            'AGUARDANDO',
            'CONSULTANDO',
            'GERANDO_ARQUIVO',
            'VALIDANDO',
            'ENVIANDO',
            'CONFIRMANDO_ENVIO',
            'CONCLUIDO',
            'FALHOU',
            'CANCELADO'
        )
    )
);

CREATE INDEX IF NOT EXISTS
    idx_exportacao_execucao_data_movimento
ON exportacao_execucao (
    data_movimento
);

CREATE INDEX IF NOT EXISTS
    idx_exportacao_execucao_status
ON exportacao_execucao (
    status
);

CREATE INDEX IF NOT EXISTS
    idx_exportacao_execucao_iniciado_em
ON exportacao_execucao (
    iniciado_em
);


CREATE TABLE IF NOT EXISTS exportacao_execucao_evento
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    exportacao_execucao_id INTEGER NOT NULL,

    status TEXT NOT NULL,

    mensagem TEXT NULL,

    ocorrido_em TEXT NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (
        exportacao_execucao_id
    )
    REFERENCES exportacao_execucao (id)
    ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS
    idx_exportacao_evento_execucao
ON exportacao_execucao_evento (
    exportacao_execucao_id,
    ocorrido_em
);
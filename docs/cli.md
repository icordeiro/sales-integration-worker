# CLI unificada

A v1.1.0 introduz uma entrada única para tarefas operacionais do SIW:

```bash
php bin/console
```

O objetivo é concentrar diagnóstico e operação sem remover imediatamente os scripts já homologados em `bin/`.

## Comandos

### `status`

Exibe uma leitura operacional rápida usando o mesmo conjunto de probes leves do endpoint `GET /api/health`:

```bash
php bin/console status
```

Também pode retornar JSON:

```bash
php bin/console status --json
```

O comando não abre uma sessão SFTP real. A configuração SFTP é validada, mas conexão e autenticação profundas ficam para `doctor`.

Códigos de saída:

- `0`: `healthy` ou `degraded`;
- `1`: `unhealthy`;
- `2`: uso inválido do comando.

### `doctor`

Executa um diagnóstico profundo do ambiente:

```bash
php bin/console doctor
```

O comando verifica:

- PHP e extensões obrigatórias;
- presença e estrutura do `.env`;
- SQLite;
- PostgreSQL;
- configuração SFTP;
- conexão/autenticação SFTP real e validação da host key;
- storage local;
- lock do worker;
- estado recente das exportações;
- migrations SQLite pendentes.

O `doctor` **não faz upload de arquivo**. O teste SFTP abre uma sessão, valida host key, autenticação e diretório remoto, mas não envia conteúdo.

Também há saída JSON:

```bash
php bin/console doctor --json
```

Códigos de saída:

- `0`: todas as verificações estão `healthy`;
- `1`: existe `warning` ou `error`;
- `2`: uso inválido do comando.

### `migrate`

Executa o fluxo de migrations SQLite já existente:

```bash
php bin/console migrate
```

Nesta entrega, o CLI delega para `bin/migrate.php`. Isso preserva exatamente o comportamento já homologado enquanto a interface operacional passa a ser unificada.

### `export:daily`

Executa o processamento diário D-1:

```bash
php bin/console export:daily
```

Nesta entrega, o CLI delega para `bin/export-sales-daily.php`. Portanto, continuam valendo as mesmas regras de idempotência, lock, geração de arquivo, validação e envio SFTP da rotina automática.

## Ajuda

```bash
php bin/console
php bin/console help
php bin/console --help
```

## Compatibilidade

Os scripts anteriores continuam disponíveis e não precisam ser alterados no agendamento já instalado. Por exemplo, o Windows Task Scheduler pode continuar chamando `bin/export-sales-daily.php` normalmente.

A CLI unificada é uma nova fachada operacional, não uma quebra de compatibilidade.

## Segurança

Os relatórios de health não exibem senha, usuário, host, fingerprint ou caminho absoluto sensível. A opção `--json` segue a mesma regra.

O `doctor` realiza conexão SFTP real. Use-o conscientemente em ambientes onde abertura de sessão remota de diagnóstico precise ser controlada.

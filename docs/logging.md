# Logging estruturado

O SIW mantém o histórico funcional e auditável das exportações no SQLite. Os logs desta seção têm outra finalidade: diagnóstico técnico e observabilidade operacional.

## Arquivo da aplicação

A aplicação grava logs estruturados em JSON Lines (`JSONL`) dentro de `storage/logs`:

```text
storage/logs/siw-2026-08.log
storage/logs/siw-2026-09.log
```

O nome muda automaticamente quando o mês muda. Não é necessário renomear o arquivo manualmente nem reiniciar o worker.

Cada linha é um objeto JSON independente. Exemplo fictício:

```json
{"timestamp":"2026-08-10T09:30:00-03:00","level":"info","channel":"siw","event":"export.completed","message":"Exportação concluída com sucesso.","context":{"duration_ms":18542,"execution_id":42,"file_name":"MV20260809_COMPANY.txt","final_status":"CONCLUIDO","movement_date":"2026-08-09","operation_type":"NORMAL","record_count":12587,"size_bytes":936210}}
```

## Eventos principais

```text
worker.daily_started
worker.daily_completed
worker.daily_noop
worker.daily_failed
worker.lock_failed
worker.lock_timeout

export.started
export.file_ready
export.completed
export.failed
export.skipped_existing

resend.requested
resend.rejected
resend.started
resend.completed
resend.failed

reprocess.requested
reprocess.rejected
reprocess.accepted
```

O reprocessamento, depois de aceito, reutiliza `ProcessarExportacaoVendasService`; por isso a nova execução aparece nos eventos `export.*` com `operation_type=REPROCESSAMENTO`.

## Contexto padronizado

Quando aplicável, os eventos carregam campos como:

```text
execution_id
origin_execution_id
movement_date
operation_type
file_name
record_count
size_bytes
duration_ms
content_changed
final_status
exception_class
error_message
```

Credenciais, senha SFTP, senha PostgreSQL e fingerprint não são adicionadas deliberadamente ao contexto.

## Níveis

A variável opcional `LOG_LEVEL` controla o nível mínimo registrado:

```dotenv
LOG_LEVEL=info
```

Valores suportados: `debug`, `info`, `warning` e `error`. Quando a variável não existe, `info` é utilizado.

## Falha no logger

O logger de produção é envolvido por `SafeLogger`. Uma falha ao criar ou gravar o arquivo de log não interrompe a exportação, o reenvio ou o reprocessamento.

Problemas de escrita no diretório continuam visíveis através do Health Check e do comando:

```bash
php bin/console doctor
```

## Windows Task Scheduler

O wrapper PowerShell mantém um log humano separado do log JSONL da aplicação:

```text
storage/logs/scheduler-2026-08.log
```

Assim, no Windows:

```text
siw-YYYY-MM.log        -> aplicação / eventos estruturados
scheduler-YYYY-MM.log  -> invocação pelo Task Scheduler
```

## Linux

O `siw-YYYY-MM.log` continua sendo produzido pela aplicação. A saída do processo do serviço pode continuar sendo coletada pelo `journald`/systemd, que possui política própria de retenção e rotação.

## Retenção

Esta entrega implementa rotação mensal, mas não exclusão automática de arquivos antigos. Retenção configurável será tratada separadamente para evitar remover evidências operacionais sem uma política explícita.

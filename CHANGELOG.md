# Changelog

Todas as mudanças relevantes do Sales Integration Worker (SIW) serão documentadas neste arquivo.

O projeto segue versionamento semântico.

## [1.1.0] - 2026-08-10

### Added

- Health Check em `GET /api/health` com estados `healthy`, `degraded` e `unhealthy`.
- Área de saúde da integração no dashboard.
- Probes para PHP, SQLite, PostgreSQL, configuração SFTP, storage, lock e histórico operacional.
- CLI unificada em `php bin/console`.
- Comandos `status`, `doctor`, `migrate` e `export:daily`.
- Diagnóstico profundo de SFTP sem upload via `doctor`.
- Verificação de migrations pendentes.
- Saída JSON para comandos de health.
- Logging estruturado em JSON Lines.
- Rotação mensal em `storage/logs/siw-YYYY-MM.log`.
- Logging mensal do Windows Task Scheduler em `scheduler-YYYY-MM.log`.
- Contexto estruturado para processamento, reenvio e reprocessamento.
- `SafeLogger` para impedir que uma falha de logging interrompa a exportação.
- Modo demonstração isolado com `APP_DEMO=true`.
- Comandos `demo:seed` e `demo:reset`.
- Dataset fictício com fluxos NORMAL, REENVIO, REPROCESSAMENTO e FALHOU.
- Bloqueio de conexões externas e operações de envio no modo demo.
- Documentação dedicada para health, CLI, logging e demo mode.
- Cobertura automatizada adicional para os recursos da v1.1.0.

### Changed

- Health Check e CLI passaram a compartilhar a mesma composição de probes.
- Task Scheduler passou a usar log mensal em vez de um arquivo único crescente.
- O SQLite do dashboard passa a ser selecionado de forma isolada quando o modo demo está ativo.

### Security

- O Health Check não expõe host, usuário, senha, fingerprint ou caminhos absolutos sensíveis.
- O modo demonstração bloqueia reenvio e reprocessamento reais.
- O `doctor` não realiza upload durante a validação SFTP.

### Compatibility

- Sem breaking changes para a rotina de produção existente.
- `APP_DEMO` é opcional e assume `false` quando ausente.
- `DEMO_DB_PATH` é opcional e assume `storage/database/siw-demo.sqlite` quando ausente.
- O Task Scheduler atual não precisa ser reinstalado.

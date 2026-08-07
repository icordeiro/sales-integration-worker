# NielsenIQ Sales Export Worker

Worker e painel web em PHP para exportar vendas de um ERP PostgreSQL para arquivo TXT delimitado por `|`, manter histórico/auditoria local em SQLite e realizar o envio por SFTP de forma controlada.

> Projeto independente e open source. Não é um produto oficial, não é afiliado e não é endossado pela NielsenIQ. NielsenIQ e outras marcas citadas pertencem aos seus respectivos proprietários.

## Visão geral

O projeto foi desenhado para executar uma integração diária com foco em previsibilidade operacional, rastreabilidade e recuperação segura de falhas.

Fluxo padrão:

```text
ERP PostgreSQL
      │
      ▼
Consulta de vendas
      │
      ▼
Geração do TXT
      │
      ├── SHA-256 / tamanho / quantidade
      ▼
Upload SFTP .part
      │
      ▼
Validação do tamanho remoto
      │
      ▼
Rename para arquivo final
      │
      ▼
SQLite + Dashboard
```

A rotina automática processa sempre o movimento **D-1**. Por padrão, os templates de agendamento incluídos no repositório estão configurados para **14:00**, mas o horário pode ser alterado no agendador do sistema operacional.

## Principais recursos

- PHP 8.5 e Composer.
- PostgreSQL como fonte operacional de vendas.
- SQLite local para histórico, eventos e auditoria.
- SFTP com `phpseclib`.
- Validação da host key SSH antes do envio das credenciais.
- Upload remoto em arquivo temporário `.part`.
- Conferência de tamanho antes do rename final.
- Proteção contra sobrescrita silenciosa de arquivo remoto existente.
- SHA-256 para integridade do arquivo local.
- Idempotência do processamento `NORMAL` por data de movimento.
- Lock de processo compartilhado entre rotina automática e operações manuais.
- Histórico de estados da execução.
- Reenvio do mesmo arquivo sem nova consulta ao ERP.
- Reprocessamento do período com nova consulta ao ERP.
- Comparação do reprocessamento com a execução de origem.
- Dashboard com atualização automática, detalhes, timeline e exportação Excel/PDF.
- Templates de agendamento para Windows Task Scheduler e Linux systemd.

## Tipos de execução

| Tipo | Consulta ERP novamente? | Gera novo arquivo? | Uso recomendado |
| --- | --- | --- | --- |
| `NORMAL` | Sim | Sim | Processamento automático diário D-1 |
| `REENVIO` | Não | Não | Reenviar exatamente o mesmo conteúdo já gerado |
| `REPROCESSAMENTO` | Sim | Sim | Gerar novamente o período com os dados atuais do ERP |

Veja detalhes em [Operações](docs/operations.md).

## Formato do arquivo

O formatter de referência gera:

```text
STORE|BARCODE|DESCRIPTION|DAY|UNIT_SALES|VALUE_SALES|PROMO
```

O nome segue o padrão configurado pelo projeto, por exemplo:

```text
MV20260807_COMPANY.txt
```

Valores de texto são normalizados para evitar que `|`, quebras de linha e tabulações corrompam o layout.

## Arquitetura

A aplicação separa responsabilidades entre domínio, aplicação e infraestrutura. O gateway de vendas isola a consulta do ERP, o gerador isola a criação do arquivo e a abstração `RemoteFileStorage` isola o transporte remoto.

```text
src/
├── Core/
├── Http/
├── Infrastructure/
├── Modules/
│   └── Exportacao/Vendas/
│       ├── Application/
│       ├── Domain/
│       └── Infrastructure/
└── Shared/
    ├── Application/
    └── Infrastructure/
```

Leia a descrição completa em [Arquitetura](docs/architecture.md).

## Requisitos

- PHP 8.5 compatível com o sistema operacional utilizado.
- Composer 2.
- Extensões PHP:
  - `pdo`
  - `pdo_pgsql`
  - `pdo_sqlite`
  - `openssl`
  - `mbstring`
- Acesso de leitura ao PostgreSQL do ERP.
- Acesso ao servidor SFTP de destino.

O dashboard atualmente carrega algumas bibliotecas front-end por CDN. Portanto, o **worker não depende da internet pública para renderização**, mas o navegador precisa alcançar os CDNs para exibir o painel com todos os recursos visuais. Em uma instalação fechada/offline, recomenda-se vendorizar esses assets localmente.

## Instalação rápida

Clone o projeto e instale as dependências:

```bash
git clone <repository-url>
cd <repository-directory>
composer install --no-dev --optimize-autoloader --no-interaction
```

Crie o arquivo de ambiente:

### Linux/macOS

```bash
cp .env.example .env
```

### Windows PowerShell

```powershell
Copy-Item .env.example .env
```

Edite `.env` com as credenciais e configurações do ambiente.

Depois execute as migrations do SQLite:

```bash
php bin/migrate.php
```

Valide as conexões:

```bash
php bin/test-database.php
php bin/test-portal-database.php
php bin/test-sftp-connection.php
```

> `bin/test-sftp-connection.php` testa conexão/autenticação, mas não envia arquivo. Já `bin/test-sftp-upload.php` e `bin/test-process-sales-export.php` podem realizar upload real e devem ser usados conscientemente.

## Configuração

O `.env.example` documenta as variáveis disponíveis. As principais são:

```dotenv
APP_ENV=development
APP_DEBUG=true
APP_TIMEZONE=America/Fortaleza

DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=database
DB_USER=readonly_user
DB_PASS=change_me

PORTAL_DB_PATH=storage/database/portal.sqlite
EXPORT_COMPANY=COMPANY

SFTP_HOST=sftp.example.com
SFTP_PORT=22
SFTP_USER=username
SFTP_PASS=change_me
SFTP_REMOTE_DIR=/DELIVERY
SFTP_HOST_KEY_FINGERPRINT=SHA256:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
SFTP_TIMEOUT=30
```

### Segurança recomendada

Em produção:

- use um usuário PostgreSQL dedicado e somente leitura;
- mantenha `.env` fora do Git;
- não publique o SQLite real;
- não publique TXT exportados ou logs;
- valide a fingerprint da host key SSH por canal confiável;
- não exponha o dashboard diretamente à Internet sem adicionar autenticação e controles apropriados.

## Execução manual

A rotina diária pode ser executada manualmente com:

```bash
php bin/export-sales-daily.php
```

Ela calcula D-1 usando `APP_TIMEZONE`, utiliza lock exclusivo e retorna código de saída `0` quando a operação é concluída ou quando o movimento `NORMAL` já foi processado.

## Dashboard

Para consulta local simples:

```bash
php -S 127.0.0.1:8080 -t public
```

Acesse:

```text
http://127.0.0.1:8080/
```

O servidor embutido do PHP é conveniente para uso local e desenvolvimento. Para disponibilidade permanente, prefira Apache, Nginx ou IIS apontando o document root para `public/`.

> O dashboard atual não possui autenticação. Mantenha-o em localhost ou rede interna confiável.

## Agendamento

### Windows

Consulte [Instalação no Windows](docs/installation-windows.md).

O projeto inclui:

```text
deploy/windows/
├── install-scheduled-task.ps1
├── run-daily-export.ps1
└── uninstall-scheduled-task.ps1
```

### Linux

Consulte [Instalação no Linux](docs/installation-linux.md).

O projeto inclui templates systemd em:

```text
deploy/linux/systemd/
```

## Estados da execução

```text
AGUARDANDO
   ↓
CONSULTANDO
   ↓
GERANDO_ARQUIVO
   ↓
VALIDANDO
   ↓
ENVIANDO
   ↓
CONFIRMANDO_ENVIO
   ↓
CONCLUIDO
```

Estados terminais adicionais:

```text
FALHOU
CANCELADO
```

Cada mudança relevante também é registrada em `exportacao_execucao_evento`.

## Banco local

O SQLite armazena metadados de execução e eventos. Entre os dados registrados estão:

- data do movimento;
- tipo e status da execução;
- execução de origem;
- nome e caminho do arquivo;
- quantidade de registros;
- tamanho em bytes;
- SHA-256;
- caminho remoto;
- erro;
- duração;
- timestamps.

O arquivo SQLite real é ignorado pelo Git.

## Testes utilitários existentes

O diretório `bin/` contém scripts de diagnóstico e homologação que antecedem a suíte PHPUnit:

```text
bin/test-database.php
bin/test-portal-database.php
bin/test-sales-query.php
bin/test-generate-sales-file.php
bin/test-sftp-connection.php
bin/test-dashboard-query.php
bin/test-process-lock.php
bin/test-resend-eligibility.php
bin/test-reprocess-eligibility.php
```

Scripts que podem realizar operações reais devem ser usados com atenção:

```text
bin/test-sftp-upload.php
bin/test-process-sales-export.php
```

A próxima etapa do projeto é migrar as regras críticas para testes automatizados com PHPUnit e CI.

## Screenshots

Antes de publicar screenshots, utilize dados fictícios. Não publique nomes de clientes, CNPJs, endereços de infraestrutura, fingerprints, nomes de arquivos reais ou qualquer outro dado operacional.

Veja [Guia de screenshots](docs/screenshots.md).

## Documentação

- [Arquitetura](docs/architecture.md)
- [Instalação no Windows](docs/installation-windows.md)
- [Instalação no Linux](docs/installation-linux.md)
- [Operações: normal, reenvio e reprocessamento](docs/operations.md)
- [Guia de screenshots](docs/screenshots.md)

## Licença

Distribuído sob a licença MIT. Consulte [LICENSE](LICENSE).

## Testes automatizados

A suíte utiliza PHPUnit e não acessa o PostgreSQL ou SFTP reais.

Instale as dependências de desenvolvimento e execute:

```bash
composer require --dev phpunit/phpunit:^13.2 --with-all-dependencies
vendor/bin/phpunit --configuration phpunit.xml.dist
```

Os testes são executados automaticamente no GitHub Actions em Windows e Linux com PHP 8.5.

Consulte [Testes automatizados](docs/testing.md) para detalhes sobre a suíte e seus limites.

# Instalação no Windows

## 1. Pré-requisitos

Instale e valide:

- PHP 8.5 x64 compatível com sua versão do Windows;
- Composer 2;
- Microsoft Visual C++ Redistributable exigido pelo build do PHP;
- extensões PHP `pdo`, `pdo_pgsql`, `pdo_sqlite`, `openssl` e `mbstring`.

Confira:

```powershell
php -v
composer --version
php -m
```

## 2. Instalar o projeto

Exemplo:

```powershell
cd C:\
git clone <repository-url> NielsenIQ
cd C:\NielsenIQ
composer install --no-dev --optimize-autoloader --no-interaction
```

## 3. Configurar o ambiente

```powershell
Copy-Item .env.example .env
```

Edite `.env`.

Use preferencialmente um usuário PostgreSQL exclusivo e somente leitura.

## 4. Criar o SQLite

```powershell
php bin\migrate.php
```

O caminho é controlado por:

```dotenv
PORTAL_DB_PATH=storage/database/portal.sqlite
```

## 5. Testes básicos

```powershell
php bin\test-database.php
php bin\test-portal-database.php
php bin\test-dashboard-query.php
php bin\test-sftp-connection.php
```

Para validar uma consulta de vendas sem fazer upload:

```powershell
php bin\test-sales-query.php 2026-08-06 20
```

Ajuste a data para um movimento existente no ERP.

## 6. Dashboard local

```powershell
php -S 127.0.0.1:8080 -t public
```

Abra:

```text
http://127.0.0.1:8080/
```

O servidor embutido do PHP é suficiente quando o painel é consultado ocasionalmente em uma única máquina. Para serviço permanente, utilize Apache/IIS/Nginx com document root em `public/`.

## 7. Instalar a tarefa agendada

Abra PowerShell como Administrador.

Se o PHP já estiver no `PATH`:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\windows\install-scheduled-task.ps1 -ScheduleTime "14:00"
```

Ou informe explicitamente o executável:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\windows\install-scheduled-task.ps1 `
    -PhpPath "C:\php\php.exe" `
    -ScheduleTime "14:00"
```

O instalador configura por padrão:

- execução diária;
- conta `SYSTEM`;
- `StartWhenAvailable`;
- exigência de rede;
- `IgnoreNew` para múltiplas instâncias;
- limite de uma hora.

O `FileProcessLock` da aplicação continua sendo a proteção principal contra concorrência entre processos diferentes.

## 8. Validar a tarefa

```powershell
Get-ScheduledTask -TaskName "NielsenIQ - Exportacao Diaria"
```

```powershell
Get-ScheduledTask -TaskName "NielsenIQ - Exportacao Diaria" |
    Get-ScheduledTaskInfo
```

Para disparar manualmente pelo próprio Task Scheduler:

```powershell
Start-ScheduledTask -TaskName "NielsenIQ - Exportacao Diaria"
```

Depois verifique:

```text
LastTaskResult : 0
```

E consulte:

```text
storage/logs/export-sales-daily.log
```

## 9. Remover a tarefa

```powershell
powershell -ExecutionPolicy Bypass -File deploy\windows\uninstall-scheduled-task.ps1
```

## Observação de segurança

O dashboard atual não possui autenticação. Não faça bind em `0.0.0.0` nem libere sua porta no firewall sem avaliar o risco e adicionar controles adequados.

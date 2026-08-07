# Instalação no Linux

## 1. Pré-requisitos

Instale:

- PHP 8.5 CLI;
- Composer 2;
- extensões `pdo`, `pdo_pgsql`, `pdo_sqlite`, `openssl` e `mbstring`;
- Git.

Os nomes exatos dos pacotes variam conforme a distribuição.

Confira:

```bash
php -v
composer --version
php -m
```

## 2. Instalar o projeto

Um local comum para um worker dedicado é `/opt`:

```bash
sudo mkdir -p /opt/nielsen-export
sudo chown "$USER":"$USER" /opt/nielsen-export
git clone <repository-url> /opt/nielsen-export
cd /opt/nielsen-export
composer install --no-dev --optimize-autoloader --no-interaction
```

## 3. Configurar o ambiente

```bash
cp .env.example .env
```

Edite `.env` e restrinja permissões:

```bash
chmod 600 .env
```

Use um usuário PostgreSQL dedicado e somente leitura.

## 4. Preparar storage e SQLite

Garanta que o usuário que executará o serviço tenha escrita em `storage/`:

```bash
mkdir -p storage/database storage/exports storage/logs storage/runtime
```

Depois:

```bash
php bin/migrate.php
```

## 5. Testes básicos

```bash
php bin/test-database.php
php bin/test-portal-database.php
php bin/test-dashboard-query.php
php bin/test-sftp-connection.php
```

Uma consulta de vendas sem upload pode ser testada com:

```bash
php bin/test-sales-query.php 2026-08-06 20
```

## 6. Configurar systemd

O repositório inclui:

```text
deploy/linux/systemd/nielsen-export.service
deploy/linux/systemd/nielsen-export.timer
```

Os templates atuais usam como referência:

```text
WorkingDirectory=/opt/nielsen-export
ExecStart=/usr/bin/php /opt/nielsen-export/bin/export-sales-daily.php
User=orbiit
Group=orbiit
```

**Edite `User`, `Group`, caminho do projeto e caminho do PHP para o seu ambiente antes de instalar.**

Depois copie:

```bash
sudo cp deploy/linux/systemd/nielsen-export.service /etc/systemd/system/
sudo cp deploy/linux/systemd/nielsen-export.timer /etc/systemd/system/
```

Recarregue:

```bash
sudo systemctl daemon-reload
```

Habilite o timer:

```bash
sudo systemctl enable --now nielsen-export.timer
```

## 7. Verificar agendamento

```bash
systemctl status nielsen-export.timer
```

```bash
systemctl list-timers nielsen-export.timer
```

O template atual agenda:

```text
14:00
```

com `Persistent=true`, permitindo que uma execução perdida durante desligamento seja recuperada quando o sistema voltar.

## 8. Testar o serviço

```bash
sudo systemctl start nielsen-export.service
```

Consulte os logs:

```bash
journalctl -u nielsen-export.service -n 100 --no-pager
```

## 9. Dashboard local

Para consulta ocasional:

```bash
php -S 127.0.0.1:8080 -t public
```

Em ambiente permanente, use um servidor web adequado e mantenha o document root em `public/`.

## Segurança

- mantenha `.env` com permissões restritas;
- o usuário systemd precisa somente dos privilégios necessários;
- não execute como `root` sem necessidade;
- mantenha o dashboard em localhost/rede interna enquanto não houver autenticação;
- não coloque SQLite em compartilhamento de rede.

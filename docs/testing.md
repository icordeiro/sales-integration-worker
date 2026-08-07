# Testes automatizados

A suíte automatizada usa PHPUnit e foi desenhada para validar as regras críticas do worker sem acessar o PostgreSQL ou o SFTP reais.

## Instalação

O projeto utiliza PHP 8.5. Adicione PHPUnit como dependência de desenvolvimento:

```bash
composer require --dev phpunit/phpunit:^13.2 --with-all-dependencies
```

Depois versione as alterações geradas pelo Composer em `composer.json` e `composer.lock`.

> Não execute `composer install --no-dev` no ambiente usado para desenvolver ou rodar testes, pois essa opção remove dependências de desenvolvimento como o PHPUnit.

## Executando

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist
```

No Windows PowerShell, o mesmo comando pode ser usado:

```powershell
vendor/bin/phpunit --configuration phpunit.xml.dist
```

Opcionalmente, adicione ao bloco `scripts` do `composer.json` existente:

```json
{
  "scripts": {
    "test": "phpunit --configuration phpunit.xml.dist"
  }
}
```

Com isso:

```bash
composer test
```

## Organização

```text
tests/
├── Unit/
├── Integration/
├── Support/
└── bootstrap.php
```

### Unit

Não dependem de serviços externos. A suíte cobre atualmente:

- criação do período e D-1;
- normalização do DTO de vendas;
- layout e sanitização do TXT;
- geração versionada do arquivo, CRLF, tamanho e SHA-256;
- lock de processo;
- idempotência do processamento normal;
- falha operacional do processamento;
- reenvio do arquivo original;
- validação de integridade antes do reenvio;
- reprocessamento com nova consulta ao gateway;
- vínculo com a execução de origem;
- comparação de SHA-256 entre origem e reprocessamento.

### Integration

O teste do repositório utiliza um SQLite temporário criado em `sys_get_temp_dir()`. Ele não usa `PORTAL_DB_PATH` e nunca toca no SQLite real da instalação.

A suíte aplica as migrations oficiais antes de executar os testes de persistência.

## O que os testes não fazem

Os testes automatizados deliberadamente **não**:

- conectam no PostgreSQL do ERP;
- conectam no SFTP real;
- enviam arquivos;
- carregam o `.env` de produção;
- utilizam o SQLite de produção.

As integrações externas continuam sendo validadas pelos scripts operacionais existentes em `bin/`, quando desejado.

## GitHub Actions

O workflow `.github/workflows/tests.yml` executa a suíte em:

- Ubuntu + PHP 8.5;
- Windows + PHP 8.5.

Ele roda em pushes para `main` e em pull requests.

O fluxo é:

```text
checkout
   ↓
PHP 8.5 + extensões
   ↓
composer validate
   ↓
composer install
   ↓
PHPUnit
```

Para a Action funcionar, `composer.json` e `composer.lock` precisam conter o PHPUnit em `require-dev`.

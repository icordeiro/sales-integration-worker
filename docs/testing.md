# Testes automatizados

A suíte automatizada usa PHPUnit e foi desenhada para validar as regras críticas do worker sem acessar o PostgreSQL ou o SFTP reais.

## Executando

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist
```

No Windows PowerShell:

```powershell
vendor\bin\phpunit --configuration phpunit.xml.dist
```

## Organização

```text
tests/
├── Unit/
├── Integration/
├── Support/
└── bootstrap.php
```

## Cobertura principal

A suíte cobre, entre outros pontos:

- criação do período e D-1;
- normalização do DTO de vendas;
- layout e sanitização do TXT;
- geração versionada, CRLF, tamanho e SHA-256;
- lock de processo;
- idempotência do processamento normal;
- reenvio e integridade do arquivo original;
- reprocessamento e comparação com a origem;
- persistência SQLite;
- agregação do health check (`healthy`, `degraded`, `unhealthy`);
- leitura não invasiva do lock;
- runtime PHP e storage;
- validação estrutural do `.env`;
- verificação de migrations SQLite;
- probe de conexão SFTP usando fake em testes;
- roteamento da CLI unificada;
- códigos de saída de `status` e `doctor`;
- saída JSON dos comandos de health;
- logger JSONL mensal e rotação por mês;
- filtro por nível mínimo de log;
- tolerância a falha de escrita via `SafeLogger`;
- eventos estruturados de processamento, reenvio e reprocessamento;
- geração e reset do dataset demonstrativo;
- isolamento do SQLite demo;
- preservação de hash no reenvio demo;
- alteração de hash no reprocessamento demo;
- bloqueio conceitual de serviços externos no health do modo demo.

## O que os testes não fazem

Os testes automatizados deliberadamente **não**:

- conectam no PostgreSQL real do ERP;
- conectam no SFTP real;
- enviam arquivos;
- carregam o `.env` de produção;
- utilizam o SQLite de produção.

O teste de `SftpConnectionHealthProbe` usa `FakeRemoteFileStorage`. A conexão SFTP real ocorre apenas quando um operador executa conscientemente:

```bash
php bin/console doctor
```

## GitHub Actions

O workflow existente continua executando a suíte em Windows e Linux com PHP 8.5. Como os novos testes não dependem de infraestrutura externa, eles também são apropriados para CI.

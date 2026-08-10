# Health Check

A partir da v1.1.0, o SIW expõe uma verificação operacional leve em:

```text
GET /api/health
```

O mesmo conjunto de probes leves também alimenta:

```bash
php bin/console status
```

A composição compartilhada fica em `config/health.php`, evitando manter uma implementação para HTTP e outra para CLI.

## Verificações leves

O health check cobre:

- versão do PHP e extensões obrigatórias;
- conexão mínima com o SQLite local;
- conexão mínima com o PostgreSQL de origem;
- validade da configuração SFTP, sem abrir sessão remota;
- disponibilidade e permissão de escrita dos diretórios de runtime;
- estado do lock do worker;
- situação da última execução registrada.

## Estados gerais

O relatório retorna um dos seguintes estados:

- `healthy`: todas as verificações estão operacionais;
- `degraded`: a infraestrutura está acessível, mas existe uma condição operacional que merece atenção;
- `unhealthy`: uma dependência necessária está indisponível ou inválida.

## Health leve x diagnóstico profundo

O endpoint HTTP e `php bin/console status` não abrem uma conexão SFTP real.

Para uma verificação completa, use:

```bash
php bin/console doctor
```

O `doctor` acrescenta:

- presença e estrutura do `.env`;
- migrations SQLite;
- conexão e autenticação SFTP reais;
- validação da host key e do diretório remoto.

Nenhum arquivo é enviado durante o diagnóstico.

## Segurança

A resposta não inclui:

- host do PostgreSQL;
- usuário ou senha do banco;
- host ou usuário SFTP;
- senha SFTP;
- fingerprint SSH;
- caminhos locais absolutos.

O dashboard continua devendo ser mantido em localhost ou rede interna confiável enquanto não houver autenticação.

## Frequência no dashboard

O dashboard principal continua atualizando resumo/histórico em seu intervalo normal. O health check possui intervalo próprio de 60 segundos para reduzir consultas desnecessárias ao PostgreSQL.

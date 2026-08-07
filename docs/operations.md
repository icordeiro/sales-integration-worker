# Operações

Este documento descreve as diferenças entre processamento normal, reenvio e reprocessamento.

## 1. Processamento NORMAL

A rotina automática executa `bin/export-sales-daily.php`.

Ela:

1. adquire o lock compartilhado;
2. calcula D-1 em `APP_TIMEZONE`;
3. verifica a idempotência do movimento normal;
4. cria uma execução `NORMAL`;
5. consulta o ERP;
6. gera o TXT;
7. calcula quantidade, tamanho e SHA-256;
8. envia ao SFTP;
9. conclui a execução e registra eventos.

Se já existir um `NORMAL` ativo/concluído para a data, nenhuma nova exportação normal é criada e o CLI encerra com sucesso.

## 2. Reenvio

Use **reenvio** quando o conteúdo original continua correto e o objetivo é transmitir novamente exatamente o mesmo arquivo.

```text
Execução original CONCLUIDO
      ↓
validar metadados
      ↓
validar arquivo local
      ↓
validar tamanho
      ↓
validar SHA-256
      ↓
criar execução REENVIO
      ↓
enviar o mesmo arquivo
```

O reenvio **não consulta o ERP** e **não gera outro conteúdo**.

### Pré-condições

A execução de origem deve:

- existir;
- estar `CONCLUIDO`;
- possuir metadados de arquivo completos;
- possuir arquivo local disponível;
- manter o mesmo tamanho;
- manter o mesmo SHA-256.

Se o arquivo tiver sido alterado depois da execução original, o reenvio é recusado.

### Auditoria

Uma nova execução `REENVIO` é criada e vinculada por:

```text
execucao_origem_id
```

A execução original permanece inalterada.

## 3. Reprocessamento

Use **reprocessamento** quando existe a possibilidade de o ERP ter mudado desde a exportação original.

Exemplos:

- vendas chegaram atrasadas;
- carga de PDV terminou depois do primeiro envio;
- houve correção operacional no período;
- o movimento foi complementado após a rotina diária.

Fluxo:

```text
Execução original CONCLUIDO
      ↓
recuperar data original
      ↓
consultar ERP novamente
      ↓
gerar novo TXT
      ↓
calcular novo SHA-256
      ↓
comparar com origem
      ↓
criar/registrar REPROCESSAMENTO
      ↓
enviar
```

O reprocessamento reutiliza o mesmo orquestrador da exportação normal, evitando duplicar regras de consulta, geração, histórico, SFTP e tratamento de falhas.

### Comparação com a origem

A execução reprocessada pode ser comparada com a origem por:

- quantidade de registros;
- tamanho em bytes;
- SHA-256.

Se o SHA-256 mudou, o conteúdo atual é diferente do conteúdo original.

Se o SHA-256 permaneceu igual, o reprocessamento gerou o mesmo conteúdo, embora continue sendo uma execução independente para fins de auditoria.

### Origem preservada

Exemplo:

```text
#20 NORMAL / CONCLUIDO
  └── #21 REPROCESSAMENTO / CONCLUIDO
```

A execução #20 não é editada nem substituída.

## 4. Arquivo remoto já existente

O storage SFTP não sobrescreve silenciosamente o arquivo final.

Se o destino ainda possui:

```text
/DELIVERY/MV20260806_COMPANY.txt
```

um novo envio do mesmo nome é recusado.

Essa é uma proteção deliberada. A política para substituir/remover arquivos remotos deve ser definida explicitamente de acordo com o contrato operacional do destino.

## 5. Upload atômico

O upload segue:

```text
arquivo.txt.part
      ↓
confere tamanho remoto
      ↓
rename
      ↓
arquivo.txt
```

Isso reduz o risco de outro sistema consumir um arquivo parcialmente transferido.

Se existir um `.part` residual, ele é removido antes de iniciar uma nova transferência. Um arquivo final existente não é removido automaticamente.

## 6. Lock e concorrência

Todas as operações usam o mesmo lock:

```text
storage/runtime/export-sales-daily.lock
```

Ele protege contra situações como:

```text
Task Scheduler/systemd → NORMAL
                  +
Dashboard → REPROCESSAMENTO
```

### Rotina automática

Aguarda até 15 minutos por outra operação liberar o lock.

### Operação manual

Se outra exportação já estiver em andamento, retorna conflito e o usuário deve aguardar.

## 7. Estados

Caminho típico:

```text
AGUARDANDO
CONSULTANDO
GERANDO_ARQUIVO
VALIDANDO
ENVIANDO
CONFIRMANDO_ENVIO
CONCLUIDO
```

Falhas terminam em:

```text
FALHOU
```

Uma execução também pode ser marcada como:

```text
CANCELADO
```

## 8. Dashboard

O detalhe de uma execução concluída permite iniciar operações manuais quando elegíveis.

Endpoints:

```text
POST /api/exportacoes/{id}/reenviar
POST /api/exportacoes/{id}/reprocessar
```

A UI exige confirmação antes da operação.

## 9. Diagnóstico

Leitura do dashboard/SQLite:

```bash
php bin/test-dashboard-query.php
```

Elegibilidade de reenvio:

```bash
php bin/test-resend-eligibility.php <execucao-id>
```

Elegibilidade de reprocessamento:

```bash
php bin/test-reprocess-eligibility.php <execucao-id>
```

Esses testes de elegibilidade não devem ser confundidos com os endpoints do dashboard, que efetivamente executam a operação após confirmação.

## 10. Recomendações operacionais

- não edite TXT de uma execução concluída;
- não reutilize manualmente diretórios de outra execução;
- preserve o SQLite enquanto precisar da trilha de auditoria;
- antes de remover exports antigos, defina uma política de retenção;
- confirme com o destino qual é a regra para reenvios com o mesmo nome de arquivo;
- prefira reprocessamento quando a dúvida é sobre a completude dos dados;
- prefira reenvio quando o conteúdo está correto e apenas a transmissão precisa ser repetida.

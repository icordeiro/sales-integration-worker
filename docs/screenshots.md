# Screenshots para o repositório

## Regra principal

Não publique screenshots capturados com dados reais de produção.

Antes da captura, use dados fictícios no SQLite ou um ambiente de demonstração.

## O que deve ser sanitizado

Revise especialmente:

- nome de cliente ou empresa;
- CNPJ;
- nome real de arquivo contendo identificador do cliente;
- host/IP;
- usuário SFTP;
- fingerprint SSH;
- caminhos locais que revelem nomes de usuário;
- mensagens de erro com infraestrutura;
- qualquer credencial.

## Screenshots sugeridos

### 1. Dashboard principal

Nome sugerido:

```text
docs/images/dashboard.png
```

Mostrar:

- quatro cards de resumo;
- tabela de histórico;
- status variados;
- botões Excel/PDF.

### 2. Detalhe da execução

```text
docs/images/execution-detail.png
```

Mostrar:

- metadados do arquivo;
- timeline;
- status concluído;
- ações de reenvio/reprocessamento.

### 3. Comparação de reprocessamento

```text
docs/images/reprocessing-comparison.png
```

Mostrar, com dados fictícios:

```text
Origem        Reprocessado
12.442        12.587
926 KB        936 KB
```

## Exemplo de dados fictícios

Prefira algo como:

```text
EXPORT_COMPANY=DEMO
MV20260806_DEMO.txt
```

Evite reaproveitar o identificador da implantação real.

## Adicionando ao README

Depois de criar os arquivos, pode ser incluída uma seção como:

```markdown
## Screenshots

![Dashboard](docs/images/dashboard.png)

![Detalhe da execução](docs/images/execution-detail.png)
```

## Próxima evolução recomendada

Criar um pequeno seed/demo para o SQLite permitirá gerar screenshots reproduzíveis sem precisar copiar banco de produção. Esse seed também poderá ser usado futuramente em testes de UI e exemplos da documentação.

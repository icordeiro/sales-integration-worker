# Modo demonstração

O SIW possui um modo de demonstração seguro para apresentar o dashboard sem depender de um ERP PostgreSQL ou de um servidor SFTP real.

## Objetivo

O modo demo foi pensado para:

- demonstrações locais;
- screenshots públicos;
- avaliação do projeto após um clone;
- testes de interface sem infraestrutura externa;
- documentação e workshops.

O dataset é armazenado em um SQLite separado do banco operacional.

## Ativando

No `.env`:

```dotenv
APP_DEMO=true
DEMO_DB_PATH=storage/database/siw-demo.sqlite
```

As demais variáveis podem permanecer com os valores de exemplo do `.env.example`. Elas continuam existindo por compatibilidade estrutural, mas PostgreSQL e SFTP não são acessados pelo dashboard em modo demo.

Depois gere os dados:

```bash
php bin/console demo:seed
```

O comando cria:

- 8 execuções fictícias;
- movimentos `NORMAL`;
- uma falha simulada;
- um `REPROCESSAMENTO` ligado à origem com conteúdo alterado;
- um `REENVIO` ligado à origem mantendo o mesmo hash;
- timelines completas;
- arquivos TXT fictícios locais.

Nenhuma conexão PostgreSQL ou SFTP é realizada pelo `demo:seed`.

Suba o dashboard:

```bash
php -S 127.0.0.1:8080 -t public
```

Acesse:

```text
http://127.0.0.1:8080/
```

O painel exibirá um aviso visível de **Modo demonstração ativo**.

## Resetando

Para recriar o dataset do zero:

```bash
php bin/console demo:reset
```

O comando remove apenas:

- o SQLite configurado em `DEMO_DB_PATH`;
- arquivos em `storage/exports/demo/`.

O banco operacional definido em `PORTAL_DB_PATH` não é alterado.

## Proteções

Quando `APP_DEMO=true`:

- o dashboard utiliza o SQLite demo;
- o health check não conecta ao PostgreSQL;
- o health check não conecta ao SFTP;
- o `doctor` não realiza a conexão SFTP profunda;
- reenvio real é bloqueado;
- reprocessamento real é bloqueado;
- `export:daily` é bloqueado;
- nenhum upload é realizado.

O endpoint de operações também recusa ações externas quando o modo demo está ativo.

## Health Check

Os itens PostgreSQL e SFTP continuam visíveis, mas aparecem como desativados intencionalmente pelo modo demo. Isso preserva a estrutura do dashboard sem sugerir que uma infraestrutura externa está conectada.

## Voltando ao modo normal

No `.env`:

```dotenv
APP_DEMO=false
```

Reinicie o servidor PHP. O SIW voltará a utilizar `PORTAL_DB_PATH`, PostgreSQL e SFTP normalmente.

## Segurança

O modo demo não deve ser tratado como um mecanismo de autenticação ou sandbox para um servidor publicamente exposto. Ele existe para demonstração local e documentação. Continue restringindo o dashboard conforme as recomendações de segurança do projeto.

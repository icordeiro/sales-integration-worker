<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Infrastructure\Database;

use App\Modules\Exportacao\Vendas\Application\Contracts\VendaExportacaoGateway;
use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\DTO\VendaExportacaoDTO;
use App\Modules\Exportacao\Vendas\Infrastructure\Database\Exception\VendaExportacaoQueryException;
use App\Shared\Infrastructure\Database\Contracts\DatabaseConnection;
use PDO;
use PDOException;
use PDOStatement;

final readonly class VrMasterVendaExportacaoGateway implements VendaExportacaoGateway
{
    private const SQL = <<<'SQL'
    SELECT
        venda.id_loja AS store,
        item.codigobarras AS barcode,
        produto.descricaocompleta AS description,
        venda.data AS day,

        ROUND(
            COALESCE(
                SUM(
                    COALESCE(item.quantidade, 0)
                    /
                    COALESCE(
                        NULLIF(produtoautomacao.qtdembalagem, 0),
                        1
                    )
                ),
                0
            ),
            3
        ) AS unit_sales,

        ROUND(
            COALESCE(
                SUM(
                    COALESCE(item.valortotal, 0)
                    + COALESCE(item.valoracrescimo, 0)
                    + COALESCE(item.valoracrescimocupom, 0)
                    - COALESCE(item.valorcancelado, 0)
                    - COALESCE(item.valordesconto, 0)
                    - COALESCE(item.valordescontocupom, 0)
                ),
                0
            ),
            2
        ) AS value_sales,

        CASE
            WHEN item.oferta IS FALSE THEN 'N'
            ELSE 'Y'
        END AS promo

    FROM pdv.venda AS venda

    LEFT JOIN pdv.vendaitem AS item
        ON item.id_venda = venda.id

    INNER JOIN produto
        ON produto.id = item.id_produto

    INNER JOIN produtocomplemento AS pc
        ON pc.id_produto = produto.id
        AND pc.id_loja = venda.id_loja

    INNER JOIN produtoaliquota AS pa
        ON pa.id_produto = produto.id
        AND pa.id_estado = 23

    INNER JOIN aliquota AS a
        ON a.id = pa.id_aliquotadebito

    INNER JOIN tipopiscofins AS tpc
        ON tpc.id = produto.id_tipopiscofins

    INNER JOIN loja
        ON loja.id = venda.id_loja

    LEFT JOIN produtoautomacao
        ON produtoautomacao.codigobarras = item.codigobarras

    WHERE venda.data = CAST(:reference_date AS date)

    GROUP BY
        venda.id_loja,
        item.codigobarras,
        produto.descricaocompleta,
        venda.data,
        CASE
            WHEN item.oferta IS FALSE THEN 'N'
            ELSE 'Y'
        END

    ORDER BY
        venda.data,
        produto.descricaocompleta,
        item.codigobarras,
        venda.id_loja,
        promo
    SQL;

    public function __construct(
        private DatabaseConnection $database
    ) {}

    public function buscarPorPeriodo(
        PeriodoExportacao $periodo
    ): iterable {
        try {
            /*
         * prepareStatement() também executa a query.
         *
         * Isso ocorre agora, antes de retornar o Generator.
         */
            $statement = $this->prepareStatement(
                $periodo
            );

            return $this->iterate(
                $statement
            );
        } catch (\PDOException $exception) {
            throw VendaExportacaoQueryException::couldNotExecute(
                $exception
            );
        }
    }

    /**
     * @return \Generator<int, VendaExportacaoDTO>
     */
    private function iterate(
        \PDOStatement $statement
    ): \Generator {
        try {
            while (
                $row = $statement->fetch(
                    \PDO::FETCH_ASSOC
                )
            ) {
                yield VendaExportacaoDTO::fromDatabaseRow(
                    $row
                );
            }
        } catch (\PDOException $exception) {
            throw VendaExportacaoQueryException::couldNotExecute(
                $exception
            );
        } finally {
            $statement->closeCursor();
        }
    }

    private function prepareStatement(
        PeriodoExportacao $periodo
    ): PDOStatement {
        $connection = $this->database->connection();

        $statement = $connection->prepare(self::SQL);

        $statement->bindValue(
            ':reference_date',
            $periodo->dataReferencia(),
            PDO::PARAM_STR
        );

        $statement->execute();

        return $statement;
    }
}

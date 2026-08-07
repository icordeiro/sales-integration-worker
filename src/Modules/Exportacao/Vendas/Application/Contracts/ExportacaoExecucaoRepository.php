<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\Contracts;

use App\Modules\Exportacao\Vendas\Application\DTO\ArquivoVendaGerado;
use App\Modules\Exportacao\Vendas\Application\DTO\ExportacaoExecucaoDTO;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;
use App\Shared\Application\DTO\RemoteFileUploadResult;

interface ExportacaoExecucaoRepository
{
    public function buscarPorId(
        int $execucaoId
    ): ?ExportacaoExecucaoDTO;

    public function existeExecucaoNormalBloqueante(
        string $dataMovimento
    ): bool;

    public function iniciar(
        string $dataMovimento,
        TipoExecucao $tipoExecucao = TipoExecucao::NORMAL,
        ?int $execucaoOrigemId = null
    ): int;

    public function registrarStatus(
        int $execucaoId,
        StatusExportacao $status,
        ?string $mensagem = null
    ): void;

    public function registrarArquivo(
        int $execucaoId,
        ArquivoVendaGerado $arquivo
    ): void;

    public function concluir(
        int $execucaoId,
        ArquivoVendaGerado $arquivo,
        RemoteFileUploadResult $envio,
        int $duracaoMilisegundos
    ): void;

    public function falhar(
        int $execucaoId,
        string $erro,
        int $duracaoMilisegundos
    ): void;
}
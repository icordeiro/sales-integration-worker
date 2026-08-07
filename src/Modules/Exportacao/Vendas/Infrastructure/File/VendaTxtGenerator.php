<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Infrastructure\File;

use App\Modules\Exportacao\Vendas\Application\Contracts\VendaArquivoGenerator;
use App\Modules\Exportacao\Vendas\Application\DTO\ArquivoVendaGerado;
use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\DTO\VendaExportacaoDTO;
use App\Modules\Exportacao\Vendas\Infrastructure\File\Exception\ArquivoVendaException;

final readonly class VendaTxtGenerator implements VendaArquivoGenerator
{
    private const LINE_ENDING = "\r\n";

    public function __construct(
        private string $baseDirectory,
        private VendaTxtFormatter $formatter,
        private string $companyIdentifier
    ) {
        if (
            $this->companyIdentifier === ''
            || preg_match(
                '/^[A-Z0-9]+$/',
                $this->companyIdentifier
            ) !== 1
        ) {
            throw new \InvalidArgumentException(
                'O identificador da empresa deve conter apenas letras maiúsculas e números.'
            );
        }
    }

    public function gerar(
        PeriodoExportacao $periodo,
        iterable $vendas,
        ?int $execucaoId = null
    ): ArquivoVendaGerado {
        $directory = $this->resolveDirectory(
            periodo: $periodo,
            execucaoId: $execucaoId
        );

        $this->ensureDirectoryExists(
            $directory
        );

        $fileName = $this->buildFileName(
            $periodo
        );

        $finalPath = $directory
            . DIRECTORY_SEPARATOR
            . $fileName;

        $partialPath = $finalPath . '.part';

        if (is_file($finalPath)) {
            throw ArquivoVendaException::fileAlreadyExists(
                $fileName
            );
        }

        if (is_file($partialPath)) {
            @unlink($partialPath);
        }

        $handle = fopen(
            $partialPath,
            'wb'
        );

        if ($handle === false) {
            throw ArquivoVendaException::fileCouldNotBeOpened(
                $fileName
            );
        }

        $recordCount = 0;

        try {
            $this->writeLine(
                $handle,
                $this->formatter->header()
            );

            foreach ($vendas as $venda) {
                if (!$venda instanceof VendaExportacaoDTO) {
                    throw new \UnexpectedValueException(
                        'O gerador recebeu um registro de venda inválido.'
                    );
                }

                $this->writeLine(
                    $handle,
                    $this->formatter->format($venda)
                );

                $recordCount++;
            }

            if (!fflush($handle)) {
                throw ArquivoVendaException::writeFailed();
            }

            fclose($handle);
            $handle = null;

            $hash = hash_file(
                'sha256',
                $partialPath
            );

            if ($hash === false) {
                throw ArquivoVendaException::hashFailed();
            }

            $size = filesize(
                $partialPath
            );

            if ($size === false) {
                throw ArquivoVendaException::sizeCouldNotBeDetermined();
            }

            if (!rename($partialPath, $finalPath)) {
                throw ArquivoVendaException::finalizeFailed();
            }

            return new ArquivoVendaGerado(
                nome: $fileName,
                caminho: $finalPath,
                dataReferencia: $periodo->dataReferencia(),
                quantidadeRegistros: $recordCount,
                tamanhoBytes: $size,
                sha256: $hash
            );
        } catch (\Throwable $exception) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            if (is_file($partialPath)) {
                @unlink($partialPath);
            }

            if ($exception instanceof ArquivoVendaException) {
                throw $exception;
            }

            throw ArquivoVendaException::finalizeFailed(
                $exception
            );
        }
    }

    /**
     * @param resource $handle
     */
    private function writeLine(
        mixed $handle,
        string $content
    ): void {
        $bytesWritten = fwrite(
            $handle,
            $content . self::LINE_ENDING
        );

        if ($bytesWritten === false) {
            throw ArquivoVendaException::writeFailed();
        }
    }

    private function resolveDirectory(
        PeriodoExportacao $periodo,
        ?int $execucaoId
    ): string {
        $directory = $this->baseDirectory
            . DIRECTORY_SEPARATOR
            . $periodo->inicio->format('Y')
            . DIRECTORY_SEPARATOR
            . $periodo->inicio->format('m');

        if ($execucaoId === null) {
            return $directory;
        }

        return $directory
            . DIRECTORY_SEPARATOR
            . 'execucao-'
            . $execucaoId;
    }

    private function buildFileName(
        PeriodoExportacao $periodo
    ): string {
        return sprintf(
            'MV%s_%s.txt',
            $periodo->inicio->format('Ymd'),
            $this->companyIdentifier
        );
    }

    private function ensureDirectoryExists(
        string $directory
    ): void {
        if (is_dir($directory)) {
            return;
        }

        if (
            !mkdir(
                $directory,
                0775,
                true
            )
            && !is_dir($directory)
        ) {
            throw ArquivoVendaException::directoryCouldNotBeCreated(
                $directory
            );
        }
    }
}

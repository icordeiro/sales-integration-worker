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

        // O nome aleatório impede colisões entre execuções e evita
        // reutilizar/remover arquivos parciais pertencentes a terceiros.
        $partialPath = $finalPath . '.' . bin2hex(random_bytes(16)) . '.part';

        // "x+b" cria um arquivo novo e falha se o caminho já existir.
        // Não segue links simbólicos previamente posicionados no caminho final.
        $handle = @fopen($partialPath, 'x+b');

        if ($handle === false) {
            throw ArquivoVendaException::fileCouldNotBeOpened($fileName);
        }

        // A escrita deve ficar restrita ao usuário do processo em Unix.
        // No Windows, a ACL do diretório é a proteção determinante.
        if (DIRECTORY_SEPARATOR === '/' && !@chmod($partialPath, 0600)) {
            fclose($handle);
            @unlink($partialPath);

            throw ArquivoVendaException::fileCouldNotBeOpened($fileName);
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

            if (!fclose($handle)) {
                $handle = null;
                throw ArquivoVendaException::writeFailed();
            }
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

            // link() publica o inode sem substituir um destino existente.
            // Diferentemente de rename(), falha atomicamente se já houver
            // arquivo, diretório ou symlink no destino final. Ambos os
            // caminhos estão no mesmo diretório/volume.
            // Se o filesystem não suportar hard links, falha de forma segura.
            if (!@link($partialPath, $finalPath)) {
                if (file_exists($finalPath) || is_link($finalPath)) {
                    throw ArquivoVendaException::fileAlreadyExists($fileName);
                }

                throw ArquivoVendaException::finalizeFailed();
            }

            // A publicação foi concluída; o link temporário é descartável.
            @unlink($partialPath);

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

            // Só remove o temporário exclusivo desta execução.
            @unlink($partialPath);

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
        $line = $content . self::LINE_ENDING;
        $length = strlen($line);
        $offset = 0;

        while ($offset < $length) {
            $bytesWritten = fwrite($handle, substr($line, $offset));

            if ($bytesWritten === false || $bytesWritten === 0) {
                throw ArquivoVendaException::writeFailed();
            }

            $offset += $bytesWritten;
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
        // Não seguir links simbólicos no caminho da área de exportação.
        // Esta verificação não substitui ACLs que impeçam renomear diretórios.
        $current = $directory;
        while ($current !== dirname($current)) {
            if (is_link($current)) {
                throw ArquivoVendaException::directoryCouldNotBeCreated($directory);
            }
            $current = dirname($current);
        }

        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw ArquivoVendaException::directoryCouldNotBeCreated($directory);
        }

        if (is_link($directory) || !is_dir($directory)) {
            throw ArquivoVendaException::directoryCouldNotBeCreated($directory);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Sftp;

use App\Shared\Infrastructure\Sftp\Exception\SftpException;

final class SftpHostKeyVerifier
{
    public function verify(
        string $serverPublicKey,
        string $expectedFingerprint
    ): void {
        $keyBlob = $this->extractKeyBlob(
            $serverPublicKey
        );

        /*
         * Fingerprint OpenSSH SHA-256:
         *
         * SHA256:<base64 sem padding>
         */
        $sha256 = rtrim(
            base64_encode(
                hash(
                    'sha256',
                    $keyBlob,
                    true
                )
            ),
            '='
        );

        /*
         * Fingerprint MD5 legado:
         *
         * aa:bb:cc:...
         */
        $md5 = implode(
            ':',
            str_split(
                md5($keyBlob),
                2
            )
        );

        if (
            $this->matchesSha256(
                $expectedFingerprint,
                $sha256
            )
            || $this->matchesMd5(
                $expectedFingerprint,
                $md5
            )
        ) {
            return;
        }

        throw SftpException::hostKeyMismatch(
            'SHA256:' . $sha256,
            $md5
        );
    }

    private function extractKeyBlob(
        string $serverPublicKey
    ): string {
        /*
         * Normalmente recebemos:
         *
         * ssh-rsa AAAAB3NzaC1yc2EAAAADAQAB...
         *
         * ou, dependendo da negociação:
         *
         * rsa-sha2-256 AAAAB3NzaC1yc2EAAAADAQAB...
         */
        $parts = preg_split(
            '/\s+/',
            trim($serverPublicKey),
            3
        );

        if (
            !is_array($parts)
            || count($parts) < 2
        ) {
            throw SftpException::hostKeyUnavailable();
        }

        $encodedKey = $parts[1];

        $keyBlob = base64_decode(
            $encodedKey,
            true
        );

        if ($keyBlob === false) {
            throw SftpException::hostKeyUnavailable();
        }

        return $keyBlob;
    }

    private function matchesSha256(
        string $expectedFingerprint,
        string $actualFingerprint
    ): bool {
        $expected = $this->extractExpectedSha256(
            $expectedFingerprint
        );

        if ($expected === null) {
            return false;
        }

        return hash_equals(
            $actualFingerprint,
            $expected
        );
    }

    private function extractExpectedSha256(
        string $fingerprint
    ): ?string {
        $fingerprint = rawurldecode(
            trim($fingerprint)
        );

        if ($fingerprint === '') {
            return null;
        }

        /*
         * Formato:
         *
         * SHA256:xxxx
         */
        $position = stripos(
            $fingerprint,
            'SHA256:'
        );

        if ($position !== false) {
            $hash = substr(
                $fingerprint,
                $position + strlen('SHA256:')
            );

            return $this->normalizeSha256(
                $hash
            );
        }

        /*
         * Formato normal do WinSCP:
         *
         * ssh-rsa 2048 xxxxxxxx
         */
        if (
            preg_match(
                '/^ssh-rsa\s+\d+\s+(.+)$/i',
                $fingerprint,
                $matches
            ) === 1
        ) {
            return $this->normalizeSha256(
                $matches[1]
            );
        }

        /*
         * Formato usado na URL do WinSCP:
         *
         * ssh-rsa-xxxxxxxx
         */
        if (
            preg_match(
                '/^ssh-rsa-(.+)$/i',
                $fingerprint,
                $matches
            ) === 1
        ) {
            /*
             * Se parecer com MD5 em formato URL,
             * não tratamos como SHA-256.
             */
            if (
                preg_match(
                    '/^(?:[0-9a-f]{2}-){15}[0-9a-f]{2}$/i',
                    $matches[1]
                ) === 1
            ) {
                return null;
            }

            return $this->normalizeSha256(
                $matches[1]
            );
        }

        /*
         * Permite também armazenar somente o hash.
         */
        return $this->normalizeSha256(
            $fingerprint
        );
    }

    private function normalizeSha256(
        string $fingerprint
    ): string {
        $fingerprint = trim(
            $fingerprint
        );

        /*
         * Na URL WinSCP:
         *
         * + pode virar -
         * / pode virar _
         */
        $fingerprint = strtr(
            $fingerprint,
            '-_',
            '+/'
        );

        return rtrim(
            $fingerprint,
            '='
        );
    }

    private function matchesMd5(
        string $expectedFingerprint,
        string $actualFingerprint
    ): bool {
        $expected = $this->extractExpectedMd5(
            $expectedFingerprint
        );

        if ($expected === null) {
            return false;
        }

        return hash_equals(
            strtolower($actualFingerprint),
            strtolower($expected)
        );
    }

    private function extractExpectedMd5(
        string $fingerprint
    ): ?string {
        $fingerprint = rawurldecode(
            trim($fingerprint)
        );

        /*
         * Formato tradicional:
         *
         * aa:bb:cc:...
         */
        if (
            preg_match(
                '/([0-9a-f]{2}(?::[0-9a-f]{2}){15})/i',
                $fingerprint,
                $matches
            ) === 1
        ) {
            return strtolower(
                $matches[1]
            );
        }

        /*
         * Formato URL WinSCP para MD5:
         *
         * ssh-rsa-aa-bb-cc-...
         */
        if (
            preg_match(
                '/^ssh-rsa-((?:[0-9a-f]{2}-){15}[0-9a-f]{2})$/i',
                $fingerprint,
                $matches
            ) === 1
        ) {
            return strtolower(
                str_replace(
                    '-',
                    ':',
                    $matches[1]
                )
            );
        }

        return null;
    }
}
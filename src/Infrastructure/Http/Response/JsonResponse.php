<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Response;

use JsonException;

final class JsonResponse
{
    public static function success(
        mixed $data,
        int $status = 200
    ): string {
        return self::send(
            payload: [
                'success' => true,
                'data' => $data,
            ],
            status: $status
        );
    }

    public static function error(
        string $message,
        int $status = 500,
        mixed $details = null
    ): string {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($details !== null) {
            $payload['details'] =
                $details;
        }

        return self::send(
            payload: $payload,
            status: $status
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function send(
        array $payload,
        int $status
    ): string {
        http_response_code(
            $status
        );

        if (!headers_sent()) {
            header(
                'Content-Type: application/json; charset=utf-8'
            );

            header(
                'Cache-Control: no-store, no-cache, must-revalidate'
            );
        }

        try {
            return json_encode(
                $payload,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException) {
            http_response_code(500);

            return '{"success":false,"message":"Não foi possível gerar a resposta JSON."}';
        }
    }
}
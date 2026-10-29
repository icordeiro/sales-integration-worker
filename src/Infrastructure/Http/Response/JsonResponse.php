<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Response;

use JsonException;

/**
 * Utilitário para construção e envio de respostas HTTP no formato JSON.
 *
 * Padroniza o payload de sucesso e erro da API, definindo os cabeçalhos
 * HTTP apropriados e tratando eventuais falhas na serialização do JSON.
 *
 * @package App\Infrastructure\Http\Response
 */
final class JsonResponse
{
    /**
     * Cria e envia uma resposta JSON de sucesso.
     *
     * @param mixed $data Dados a serem retornados no corpo da resposta (`data`).
     * @param int $status Código do status HTTP (padrão: 200 OK).
     * @return string Representação em formato JSON do payload de sucesso.
     */
    public static function success(mixed $data, int $status = 200): string
    {
        return self::send(
            payload: [
                'success' => true,
                'data' => $data,
            ],
            status: $status
        );
    }

    /**
     * Cria e envia uma resposta JSON de erro padronizada.
     *
     * @param string $message Mensagem principal descrevendo a falha.
     * @param int $status Código do status HTTP de erro (padrão: 500 Internal Server Error).
     * @param mixed $details Detalhes adicionais do erro (ex: validações, contexto ou stack traces).
     * @return string Representação em formato JSON do payload de erro.
     */
    public static function error(string $message, int $status = 500, mixed $details = null): string
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($details !== null) {
            $payload['details'] = $details;
        }

        return self::send(
            payload: $payload,
            status: $status
        );
    }

    /**
     * Define os cabeçalhos HTTP necessários e codifica a resposta em JSON.
     *
     * @param array<string, mixed> $payload Estrutura de dados a ser codificada.
     * @param int $status Código do status HTTP a ser aplicado.
     * @return string JSON resultante da codificação ou fallback em caso de exceção.
     */
    private static function send(array $payload, int $status): string
    {
        http_response_code($status);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
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
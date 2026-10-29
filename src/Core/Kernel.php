<?php

declare(strict_types=1);

namespace App\Core;

use App\Infrastructure\Http\Response\JsonResponse;
use App\Infrastructure\Http\Routing\OrbiitRouterAdapter;
use Orbiit\Router\Exceptions\MethodNotAllowedException;
use Orbiit\Router\Exceptions\RouteNotFoundException;
use Throwable;

/**
 * Núcleo da aplicação (HTTP Kernel).
 *
 * Responsável por orquestrar o ciclo de vida da requisição HTTP, despachando
 * a rota correspondente via Roteador e tratando a emissão de respostas ou exceções globais.
 *
 * @package App\Core
 */
final readonly class Kernel
{
    /**
     * Inicializa o Kernel com o adaptador do roteador.
     *
     * @param OrbiitRouterAdapter $router Adaptador responsável por gerenciar e despachar as rotas.
     */
    public function __construct(private OrbiitRouterAdapter $router) {
    }

    /**
     * Processa a requisição HTTP atual, executa o roteamento e emite a resposta adequada.
     *
     * Captura exceções do roteador e erros genéricos da aplicação, adaptando
     * o formato do retorno (JSON para rotas de API e texto/HTML para web).
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            $response = $this->router->dispatch();

            $this->emit($response);
        } catch (RouteNotFoundException) {
            if ($this->isApiRequest()) {
                echo JsonResponse::error(
                    message: 'Rota não encontrada.',
                    status: 404
                );

                return;
            }

            http_response_code(404);

            echo 'Página não encontrada.';
        } catch (MethodNotAllowedException) {
            if ($this->isApiRequest()) {
                echo JsonResponse::error(
                    message: 'Método HTTP não permitido.',
                    status: 405
                );

                return;
            }

            http_response_code(405);

            echo 'Método não permitido.';
        } catch (Throwable) {
            if ($this->isApiRequest()) {
                echo JsonResponse::error(
                    message: 'Erro interno da aplicação.',
                    status: 500
                );

                return;
            }

            http_response_code(500);

            echo 'Erro interno da aplicação.';
        }
    }

    /**
     * Emite o conteúdo da resposta para o buffer de saída do PHP.
     *
     * Converte o retorno em string ou resposta JSON estruturada conforme o tipo de dado fornecido.
     *
     * @param mixed $response O retorno gerado pelo handler/controller da rota.
     * @return void
     */
    private function emit(mixed $response): void
    {
        if ($response === null) {
            return;
        }

        if (is_string($response)) {
            echo $response;
            return;
        }

        if (is_int($response) || is_float($response) || is_bool($response)) {
            echo (string) $response;
            return;
        }

        echo JsonResponse::success($response);
    }

    /**
     * Determina se a requisição atual é voltada para a API baseando-se no prefixo do caminho.
     *
     * @return bool Retorna `true` se a URI começar com '/api/', caso contrário `false`.
     */
    private function isApiRequest(): bool
    {
        $route = $_GET['route']
            ?? $_SERVER['REQUEST_URI']
            ?? '/';

        $path = parse_url((string) $route, PHP_URL_PATH);

        if (!is_string($path)) {
            return false;
        }

        return str_starts_with($path, '/api/');
    }
}
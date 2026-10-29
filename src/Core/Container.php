<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use RuntimeException;

/**
 * Container de Injeção de Dependências e Inversão de Controle (IoC).
 *
 * Responsável por registrar, instanciar e gerenciar o ciclo de vida
 * das dependências e serviços da aplicação via Reflection.
 *
 * @package App\Core
 */
final class Container
{
    /**
     * Mapeamento de identificadores (classes/interfaces) para suas implementações ou factories.
     *
     * @var array<class-string|string, Closure(self): mixed|class-string>
     */
    private array $bindings = [];

    /**
     * Instâncias de objetos já criadas (Singletons / Instâncias concretas).
     *
     * @var array<class-string|string, mixed>
     */
    private array $instances = [];

    /**
     * Registra um binding (mapeamento) no container.
     *
     * @template T
     *
     * @param class-string<T>|string $abstract Nome da interface ou classe abstrata.
     * @param Closure(self): T|class-string<T> $concrete Factory Closure ou nome da classe concreta.
     * @return void
     */
    public function bind(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Registra uma instância de objeto existente no container (Singleton).
     *
     * @template T of object
     *
     * @param class-string<T>|string $abstract Nome do identificador ou da interface.
     * @param T $instance Instância concreta do objeto.
     * @return void
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Verifica se uma dependência está registrada ou se a classe existe.
     *
     * @param class-string|string $abstract Nome da classe ou identificador a verificar.
     * @return bool Retorna true se houver binding, instância prévia ou se for uma classe existente.
     */
    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract])
            || isset($this->bindings[$abstract])
            || class_exists($abstract);
    }

    /**
     * Resolve e retorna a instância da dependência solicitada.
     *
     * @template T
     *
     * @param class-string<T>|string $abstract Nome da classe, interface ou identificador.
     * @return ($abstract is class-string<T> ? T : mixed) Instância resolvida do serviço/classe.
     *
     * @throws RuntimeException Se a classe não puder ser instanciada ou resolvida.
     */
    public function make(string $abstract): mixed
    {
        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        if (array_key_exists($abstract, $this->bindings)) {
            $concrete = $this->bindings[$abstract];

            if ($concrete instanceof Closure) {
                return $concrete($this);
            }

            $abstract = $concrete;
        }

        return $this->build($abstract);
    }

    /**
     * Instancia a classe informada resolvendo recursivamente suas dependências via Reflection.
     *
     * @template T of object
     *
     * @param class-string<T> $className Nome FQCN da classe a ser construída.
     * @return T Instância criada da classe.
     *
     * @throws RuntimeException Se a classe não existir, não for instanciável ou faltar parâmetro obrigatório.
     */
    private function build(string $className): object
    {
        try {
            /** @var ReflectionClass<T> $reflection */
            $reflection = new ReflectionClass($className);
        } catch (ReflectionException $exception) {
            throw new RuntimeException(
                sprintf('Não foi possível resolver a classe "%s".', $className),
                previous: $exception
            );
        }

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException(
                sprintf('A classe "%s" não pode ser instanciada.', $className)
            );
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $arguments[] = $this->make($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException(
                sprintf(
                    'Não foi possível resolver o parâmetro "$%s" de "%s".',
                    $parameter->getName(),
                    $className
                )
            );
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
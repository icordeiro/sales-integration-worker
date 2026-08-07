<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use RuntimeException;

final class Container
{
    /**
     * @var array<string, Closure|string>
     */
    private array $bindings = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    public function bind(
        string $abstract,
        Closure|string $concrete
    ): void {
        $this->bindings[$abstract] = $concrete;
    }

    public function instance(
        string $abstract,
        mixed $instance
    ): void {
        $this->instances[$abstract] = $instance;
    }

    public function has(
        string $abstract
    ): bool {
        return isset($this->instances[$abstract])
            || isset($this->bindings[$abstract])
            || class_exists($abstract);
    }

    public function make(
        string $abstract
    ): mixed {
        if (
            array_key_exists(
                $abstract,
                $this->instances
            )
        ) {
            return $this->instances[$abstract];
        }

        if (
            array_key_exists(
                $abstract,
                $this->bindings
            )
        ) {
            $concrete =
                $this->bindings[$abstract];

            if ($concrete instanceof Closure) {
                return $concrete($this);
            }

            $abstract = $concrete;
        }

        return $this->build(
            $abstract
        );
    }

    private function build(
        string $className
    ): object {
        try {
            $reflection =
                new ReflectionClass(
                    $className
                );
        } catch (ReflectionException $exception) {
            throw new RuntimeException(
                sprintf(
                    'Não foi possível resolver a classe "%s".',
                    $className
                ),
                previous: $exception
            );
        }

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException(
                sprintf(
                    'A classe "%s" não pode ser instanciada.',
                    $className
                )
            );
        }

        $constructor =
            $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = [];

        foreach (
            $constructor->getParameters()
            as $parameter
        ) {
            $type =
                $parameter->getType();

            if (
                $type instanceof ReflectionNamedType
                && !$type->isBuiltin()
            ) {
                $arguments[] =
                    $this->make(
                        $type->getName()
                    );

                continue;
            }

            if (
                $parameter->isDefaultValueAvailable()
            ) {
                $arguments[] =
                    $parameter->getDefaultValue();

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

        return $reflection->newInstanceArgs(
            $arguments
        );
    }
}
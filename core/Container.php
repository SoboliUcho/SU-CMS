<?php

namespace Core;

class Container
{
    /** @var array<string, callable|string> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $singletons = [];

    public function bind(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        $this->singletons[$abstract] = null;
    }

    public function make(string $class)
    {
        if (array_key_exists($class, $this->singletons) && $this->singletons[$class] !== null) {
            return $this->singletons[$class];
        }

        $instance = $this->resolve($class);

        if (array_key_exists($class, $this->singletons)) {
            $this->singletons[$class] = $instance;
        }

        return $instance;
    }

    private function resolve(string $class): object
    {
        if (isset($this->bindings[$class])) {
            $binding = $this->bindings[$class];
            if (is_callable($binding)) {
                $resolved = $binding($this);
                if (!is_object($resolved)) {
                    throw new \RuntimeException("Container binding for {$class} must resolve to an object.");
                }
                return $resolved;
            }
            if (is_string($binding)) {
                $class = $binding;
            }
        }

        if (!class_exists($class)) {
            throw new \RuntimeException("Class {$class} not found.");
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return new $class();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }
                throw new \RuntimeException("Unable to resolve constructor parameter {$parameter->getName()} for {$class}.");
            }

            $dependencies[] = $this->make($type->getName());
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}

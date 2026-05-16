<?php

namespace Core\Modules;

use Core\Http\Router;

interface ModuleInterface
{
    public function getName(): string;

    public function getVersion(): string;

    public function boot(): void;

    public function registerRoutes(Router $router): void;

    public function install(): void;

    public function update(): void;

    public function uninstall(): void;

    public function settings(): array;
}
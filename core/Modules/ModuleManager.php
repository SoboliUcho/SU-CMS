<?php

namespace Core\Modules;

use Core\Db;
use Core\Http\Router;
use Core\Logger;

class ModuleManager
{
    private static ?self $instance = null;

    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function bootConfigured(array $moduleNames, Router $router, bool $autoManage = false): void
    {
        $this->ensureRegistryTable();

        foreach ($moduleNames as $moduleName) {
            $module = $this->load($moduleName);
            if ($module === null) {
                Logger::getInstance()->warning('Configured module was not found.', [
                    'module' => $moduleName,
                ]);
                continue;
            }

            if ($autoManage) {
                $this->ensureInstalled($module);
            }

            $module->boot();
            $module->registerRoutes($router);

            if (!$autoManage) {
                $this->upsertRegistry($module);
            }
        }
    }

    public function load(string $moduleName): ?ModuleInterface
    {
        if (isset($this->modules[$moduleName])) {
            return $this->modules[$moduleName];
        }

        $className = 'Modules\\' . $moduleName . '\\Module';
        if (!class_exists($className)) {
            return null;
        }

        $module = new $className();
        if (!$module instanceof ModuleInterface) {
            throw new \RuntimeException("Module {$moduleName} must implement ModuleInterface.");
        }

        $this->modules[$moduleName] = $module;

        return $module;
    }

    public function install(string $moduleName): void
    {
        $module = $this->requireModule($moduleName);
        $this->ensureRegistryTable();
        $module->install();
        $this->upsertRegistry($module);
    }

    public function update(string $moduleName): void
    {
        $module = $this->requireModule($moduleName);
        $this->ensureRegistryTable();
        $module->update();
        $this->upsertRegistry($module);
    }

    public function uninstall(string $moduleName): void
    {
        $module = $this->requireModule($moduleName);
        $module->uninstall();
        $this->ensureRegistryTable();
        Db::queryDelete('DELETE FROM module_registry WHERE module_name = ?', $module->getName());
    }

    public function all(): array
    {
        return array_values($this->modules);
    }

    public function settingsCatalog(): array
    {
        $catalog = [];

        foreach ($this->modules as $module) {
            $catalog[$module->getName()] = [
                'version' => $module->getVersion(),
                'settings' => $module->settings(),
            ];
        }

        return $catalog;
    }

    private function requireModule(string $moduleName): ModuleInterface
    {
        $module = $this->load($moduleName);
        if ($module === null) {
            throw new \RuntimeException("Module {$moduleName} was not found.");
        }

        return $module;
    }

    private function ensureInstalled(ModuleInterface $module): void
    {
        $row = Db::queryOne('SELECT module_name, version FROM module_registry WHERE module_name = ? LIMIT 1', $module->getName());

        if (empty($row)) {
            $module->install();
            $this->upsertRegistry($module);
            return;
        }

        if (($row['version'] ?? null) !== $module->getVersion()) {
            $module->update();
            $this->upsertRegistry($module);
        }
    }

    private function upsertRegistry(ModuleInterface $module): void
    {
        $row = Db::queryOne('SELECT id FROM module_registry WHERE module_name = ? LIMIT 1', $module->getName());

        if (empty($row)) {
            Db::queryInsert(
                'INSERT INTO module_registry (module_name, version, is_active) VALUES (?, ?, ?)',
                $module->getName(),
                $module->getVersion(),
                1
            );
            return;
        }

        Db::queryUpdate(
            'UPDATE module_registry SET version = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            $module->getVersion(),
            1,
            $row['id']
        );
    }

    private function ensureRegistryTable(): void
    {
        Db::query(
            'CREATE TABLE IF NOT EXISTS module_registry (
                id INT NOT NULL AUTO_INCREMENT,
                module_name VARCHAR(120) NOT NULL,
                version VARCHAR(32) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                installed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY module_name_idx (module_name)
            )'
        );
    }
}
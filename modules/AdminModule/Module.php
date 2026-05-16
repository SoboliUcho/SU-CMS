<?php

namespace Modules\AdminModule;

use Core\Db;
use Core\Http\Router;
use Core\Modules\ModuleInterface;
use Modules\AdminModule\Controllers\AdminController;
use Modules\AdminModule\Controllers\AdminWebController;

class Module implements ModuleInterface
{
    public function getName(): string
    {
        return 'AdminModule';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function boot(): void
    {
    }

    public function registerRoutes(Router $router): void
    {
        $router->routing('GET', '/admin', [AdminWebController::class, 'dashboard']);
        $router->routing('GET', '/admin/login', [AdminWebController::class, 'loginPage']);
        $router->routing('POST', '/admin/login', [AdminWebController::class, 'login']);
        $router->routing('POST', '/admin/logout', [AdminWebController::class, 'logout']);
        $router->routing('POST', '/admin/module-action', [AdminWebController::class, 'moduleAction']);
        $router->routing('POST', '/admin/user-action', [AdminWebController::class, 'userAction']);

        $router->routing('GET', '/admin/modules/settings', [AdminController::class, 'index']);
        $router->routing('POST', '/admin/modules/install/{module}', [AdminController::class, 'install']);
        $router->routing('POST', '/admin/modules/update/{module}', [AdminController::class, 'update']);
        $router->routing('DELETE', '/admin/modules/uninstall/{module}', [AdminController::class, 'uninstall']);
    }

    public function install(): void
    {
        Db::constructDb($this->schema());
    }

    public function update(): void
    {
        $this->install();
    }

    public function uninstall(): void
    {
        foreach (array_reverse($this->schema()['tables']) as $table) {
            Db::query('DROP TABLE IF EXISTS `' . $table['name'] . '`');
        }
    }

    public function settings(): array
    {
        return [
            'dashboard' => [
                'label' => 'Admin dashboard',
                'type' => 'module-catalog',
                'description' => 'Sjednocené nastavení aktivních modulů a jejich lifecycle operací.',
            ],
        ];
    }

    private function schema(): array
    {
        return [
            'database' => (string) config('app.database.dbname', 'default_db'),
            'tables' => [
                [
                    'name' => 'module_settings',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'auto_increment' => true],
                        ['name' => 'module_name', 'type' => 'VARCHAR', 'length' => '120', 'null' => 'NOT NULL', 'after' => 'id'],
                        ['name' => 'setting_key', 'type' => 'VARCHAR', 'length' => '120', 'null' => 'NOT NULL', 'after' => 'module_name'],
                        ['name' => 'setting_value', 'type' => 'TEXT', 'null' => 'NULL', 'default' => 'NULL', 'after' => 'setting_key'],
                        ['name' => 'updated_at', 'type' => 'TIMESTAMP', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP', 'after' => 'setting_value'],
                    ],
                    'keys' => [
                        ['name' => 'PRIMARY', 'type' => 'primary', 'columns' => ['id']],
                        ['name' => 'module_settings_module_idx', 'type' => 'index', 'columns' => ['module_name']],
                    ],
                ],
            ],
        ];
    }
}
<?php

namespace Modules\AuthModule;

use Core\Db;
use Core\Http\Router;
use Core\Modules\ModuleInterface;
use Modules\AuthModule\Controllers\AuthController;

class Module implements ModuleInterface
{
    public function getName(): string
    {
        return 'AuthModule';
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
        $router->routing('GET', '/auth/status', [AuthController::class, 'status']);
        $router->routing('GET', '/auth/me', [AuthController::class, 'me']);
        $router->routing('POST', '/auth/login', [AuthController::class, 'login']);
        $router->routing('POST', '/auth/logout', [AuthController::class, 'logout']);
        $router->routing('POST', '/auth/users', [AuthController::class, 'createUser']);
        $router->routing('PUT', '/auth/users/{id}/activate', [AuthController::class, 'activateUser']);
        $router->routing('PUT', '/auth/users/{id}/deactivate', [AuthController::class, 'deactivateUser']);
        $router->routing('DELETE', '/auth/users/{id}', [AuthController::class, 'deleteUser']);
        $router->routing('POST', '/auth/users/{id}/roles', [AuthController::class, 'assignRole']);
        $router->routing('DELETE', '/auth/users/{id}/roles/{roleId}', [AuthController::class, 'revokeRole']);
        $router->routing('POST', '/auth/roles', [AuthController::class, 'createRole']);
        $router->routing('PUT', '/auth/roles/{id}/permissions', [AuthController::class, 'syncRolePermissions']);
    }

    public function install(): void
    {
        Db::constructDb($this->schema());

        $service = new Services\AuthService();
        $service->ensurePermission('admin.panel.view', 'Přístup do administrace');
        $service->ensurePermission('admin.modules.manage', 'Správa modulů a jejich nastavení');
        $service->ensureRole('admin', 'Výchozí administrátorská role', ['*']);
        $service->ensureAdminUser('admin', 'admin@localhost', 'change-me-now');
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
            'providers' => [
                'label' => 'Externí poskytovatelé',
                'type' => 'list',
                'items' => ['twitch', 'discord', 'google'],
                'description' => 'Připravené identifikátory providerů pro budoucí OAuth integrace.',
            ],
            'mfa' => [
                'label' => 'Dvoufaktorové ověření',
                'type' => 'feature-flag',
                'value' => true,
                'description' => 'Datový model pro MFA je připraven, ověřovací flow se doplní nad touto vrstvou.',
            ],
        ];
    }

    private function schema(): array
    {
        return [
            'database' => (string) config('app.database.dbname', 'default_db'),
            'tables' => [
                [
                    'name' => 'auth_users',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'auto_increment' => true],
                        ['name' => 'username', 'type' => 'VARCHAR', 'length' => '100', 'null' => 'NOT NULL', 'after' => 'id'],
                        ['name' => 'email', 'type' => 'VARCHAR', 'length' => '190', 'null' => 'NULL', 'default' => 'NULL', 'after' => 'username'],
                        ['name' => 'password_hash', 'type' => 'VARCHAR', 'length' => '255', 'null' => 'NOT NULL', 'after' => 'email'],
                        ['name' => 'status', 'type' => 'VARCHAR', 'length' => '20', 'null' => 'NOT NULL', 'default' => "'active'", 'after' => 'password_hash'],
                        ['name' => 'is_super_admin', 'type' => 'TINYINT', 'length' => '1', 'null' => 'NOT NULL', 'default' => '0', 'after' => 'status'],
                        ['name' => 'two_factor_enabled', 'type' => 'TINYINT', 'length' => '1', 'null' => 'NOT NULL', 'default' => '0', 'after' => 'is_super_admin'],
                        ['name' => 'created_at', 'type' => 'TIMESTAMP', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP', 'after' => 'two_factor_enabled'],
                        ['name' => 'updated_at', 'type' => 'TIMESTAMP', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP', 'after' => 'created_at'],
                    ],
                    'keys' => [
                        ['name' => 'PRIMARY', 'type' => 'primary', 'columns' => ['id']],
                        ['name' => 'auth_users_username_idx', 'type' => 'index', 'columns' => ['username']],
                    ],
                ],
                [
                    'name' => 'auth_roles',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'auto_increment' => true],
                        ['name' => 'name', 'type' => 'VARCHAR', 'length' => '100', 'null' => 'NOT NULL', 'after' => 'id'],
                        ['name' => 'description', 'type' => 'TEXT', 'null' => 'NULL', 'default' => 'NULL', 'after' => 'name'],
                        ['name' => 'created_at', 'type' => 'TIMESTAMP', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP', 'after' => 'description'],
                    ],
                    'keys' => [
                        ['name' => 'PRIMARY', 'type' => 'primary', 'columns' => ['id']],
                        ['name' => 'auth_roles_name_idx', 'type' => 'index', 'columns' => ['name']],
                    ],
                ],
                [
                    'name' => 'auth_permissions',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'auto_increment' => true],
                        ['name' => 'code', 'type' => 'VARCHAR', 'length' => '150', 'null' => 'NOT NULL', 'after' => 'id'],
                        ['name' => 'description', 'type' => 'TEXT', 'null' => 'NULL', 'default' => 'NULL', 'after' => 'code'],
                    ],
                    'keys' => [
                        ['name' => 'PRIMARY', 'type' => 'primary', 'columns' => ['id']],
                        ['name' => 'auth_permissions_code_idx', 'type' => 'index', 'columns' => ['code']],
                    ],
                ],
                [
                    'name' => 'auth_role_permissions',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'auto_increment' => true],
                        ['name' => 'role_id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'after' => 'id'],
                        ['name' => 'permission_id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'after' => 'role_id'],
                    ],
                    'keys' => [
                        ['name' => 'PRIMARY', 'type' => 'primary', 'columns' => ['id']],
                        ['name' => 'auth_role_permissions_role_idx', 'type' => 'index', 'columns' => ['role_id']],
                    ],
                ],
                [
                    'name' => 'auth_user_roles',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'auto_increment' => true],
                        ['name' => 'user_id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'after' => 'id'],
                        ['name' => 'role_id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'after' => 'user_id'],
                    ],
                    'keys' => [
                        ['name' => 'PRIMARY', 'type' => 'primary', 'columns' => ['id']],
                        ['name' => 'auth_user_roles_user_idx', 'type' => 'index', 'columns' => ['user_id']],
                    ],
                ],
                [
                    'name' => 'auth_external_providers',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'auto_increment' => true],
                        ['name' => 'provider_key', 'type' => 'VARCHAR', 'length' => '50', 'null' => 'NOT NULL', 'after' => 'id'],
                        ['name' => 'provider_name', 'type' => 'VARCHAR', 'length' => '100', 'null' => 'NOT NULL', 'after' => 'provider_key'],
                        ['name' => 'is_enabled', 'type' => 'TINYINT', 'length' => '1', 'null' => 'NOT NULL', 'default' => '0', 'after' => 'provider_name'],
                    ],
                    'keys' => [
                        ['name' => 'PRIMARY', 'type' => 'primary', 'columns' => ['id']],
                        ['name' => 'auth_external_providers_key_idx', 'type' => 'index', 'columns' => ['provider_key']],
                    ],
                ],
                [
                    'name' => 'auth_user_provider_links',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'auto_increment' => true],
                        ['name' => 'user_id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'after' => 'id'],
                        ['name' => 'provider_id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'after' => 'user_id'],
                        ['name' => 'provider_user_id', 'type' => 'VARCHAR', 'length' => '191', 'null' => 'NOT NULL', 'after' => 'provider_id'],
                        ['name' => 'provider_payload', 'type' => 'TEXT', 'null' => 'NULL', 'default' => 'NULL', 'after' => 'provider_user_id'],
                    ],
                    'keys' => [
                        ['name' => 'PRIMARY', 'type' => 'primary', 'columns' => ['id']],
                        ['name' => 'auth_user_provider_links_user_idx', 'type' => 'index', 'columns' => ['user_id']],
                    ],
                ],
                [
                    'name' => 'auth_mfa_methods',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'auto_increment' => true],
                        ['name' => 'user_id', 'type' => 'INT', 'length' => '11', 'null' => 'NOT NULL', 'after' => 'id'],
                        ['name' => 'method_type', 'type' => 'VARCHAR', 'length' => '50', 'null' => 'NOT NULL', 'after' => 'user_id'],
                        ['name' => 'secret', 'type' => 'VARCHAR', 'length' => '255', 'null' => 'NULL', 'default' => 'NULL', 'after' => 'method_type'],
                        ['name' => 'is_verified', 'type' => 'TINYINT', 'length' => '1', 'null' => 'NOT NULL', 'default' => '0', 'after' => 'secret'],
                    ],
                    'keys' => [
                        ['name' => 'PRIMARY', 'type' => 'primary', 'columns' => ['id']],
                        ['name' => 'auth_mfa_methods_user_idx', 'type' => 'index', 'columns' => ['user_id']],
                    ],
                ],
            ],
        ];
    }
}
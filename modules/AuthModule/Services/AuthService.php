<?php

namespace Modules\AuthModule\Services;

use Core\Db;
use Core\Http\Request;

class AuthService
{
    private const SESSION_USER_ID_KEY = 'auth_user_id';

    public function createUser(array $payload): array
    {
        $username = trim((string) ($payload['username'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $roles = $payload['roles'] ?? [];

        if ($username === '' || $password === '') {
            throw new \InvalidArgumentException('username a password jsou povinné.');
        }

        $existing = Db::queryOne('SELECT id FROM auth_users WHERE username = ? LIMIT 1', $username);
        if (!empty($existing)) {
            throw new \InvalidArgumentException('Uživatel se stejným jménem už existuje.');
        }

        $userId = Db::queryInsert(
            'INSERT INTO auth_users (username, email, password_hash, status, is_super_admin, two_factor_enabled) VALUES (?, ?, ?, ?, ?, ?)',
            $username,
            $email !== '' ? $email : null,
            password_hash($password, PASSWORD_DEFAULT),
            'active',
            0,
            0
        );

        foreach ($this->normalizeStringList($roles) as $roleName) {
            $this->assignRoleByName($userId, $roleName);
        }

        return $this->getUserById($userId);
    }

    public function activateUser(int $userId): array
    {
        return $this->setUserStatus($userId, 'active');
    }

    public function deactivateUser(int $userId): array
    {
        return $this->setUserStatus($userId, 'inactive');
    }

    public function deleteUser(int $userId): void
    {
        Db::queryDelete('DELETE FROM auth_user_roles WHERE user_id = ?', $userId);
        Db::queryDelete('DELETE FROM auth_user_provider_links WHERE user_id = ?', $userId);
        Db::queryDelete('DELETE FROM auth_mfa_methods WHERE user_id = ?', $userId);
        Db::queryDelete('DELETE FROM auth_users WHERE id = ?', $userId);
    }

    public function listUsers(): array
    {
        $rows = Db::queryAll(
            'SELECT id, username, email, status, is_super_admin, two_factor_enabled, created_at, updated_at
             FROM auth_users
             ORDER BY id DESC'
        );

        foreach ($rows as &$row) {
            $row['roles'] = $this->getRoleNamesForUser((int) ($row['id'] ?? 0));
        }
        unset($row);

        return $rows;
    }

    public function createRole(string $name, string $description = '', array $permissions = []): array
    {
        return $this->ensureRole($name, $description, $permissions);
    }

    public function syncRolePermissions(int $roleId, array $permissions): array
    {
        Db::queryDelete('DELETE FROM auth_role_permissions WHERE role_id = ?', $roleId);

        foreach ($this->normalizeStringList($permissions) as $permissionCode) {
            $permission = $this->ensurePermission($permissionCode);
            Db::queryInsert(
                'INSERT INTO auth_role_permissions (role_id, permission_id) VALUES (?, ?)',
                $roleId,
                $permission['id']
            );
        }

        return $this->getRoleById($roleId);
    }

    public function assignRole(int $userId, int $roleId): void
    {
        $existing = Db::queryOne(
            'SELECT id FROM auth_user_roles WHERE user_id = ? AND role_id = ? LIMIT 1',
            $userId,
            $roleId
        );

        if (!empty($existing)) {
            return;
        }

        Db::queryInsert('INSERT INTO auth_user_roles (user_id, role_id) VALUES (?, ?)', $userId, $roleId);
    }

    public function revokeRole(int $userId, int $roleId): void
    {
        Db::queryDelete('DELETE FROM auth_user_roles WHERE user_id = ? AND role_id = ?', $userId, $roleId);
    }

    public function hasPermission(int $userId, string $permission): bool
    {
        $user = $this->getUserById($userId);
        if (($user['is_super_admin'] ?? 0) == 1) {
            return true;
        }

        $rows = Db::queryAll(
            'SELECT p.code
             FROM auth_permissions p
             INNER JOIN auth_role_permissions rp ON rp.permission_id = p.id
             INNER JOIN auth_user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = ?',
            $userId
        );

        $codes = array_map(static fn(array $row) => $row['code'] ?? '', $rows);

        return in_array('*', $codes, true) || in_array($permission, $codes, true);
    }

    public function authorizeRequest(Request $request, string $permission): ?array
    {
        $user = $this->currentUser();

        if (empty($user) || ($user['status'] ?? '') !== 'active') {
            return null;
        }

        if (!$this->hasPermission((int) $user['id'], $permission)) {
            return null;
        }

        return $user;
    }

    public function login(string $username, string $password): ?array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return null;
        }

        $user = Db::queryOne(
            'SELECT id, username, email, password_hash, status, is_super_admin, two_factor_enabled FROM auth_users WHERE username = ? LIMIT 1',
            $username
        );

        if (empty($user) || ($user['status'] ?? '') !== 'active') {
            return null;
        }

        if (!password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            return null;
        }

        $this->ensureSession();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID_KEY] = (int) $user['id'];

        return $this->getUserById((int) $user['id']);
    }

    public function logout(): void
    {
        $this->ensureSession();
        unset($_SESSION[self::SESSION_USER_ID_KEY]);
        session_regenerate_id(true);
    }

    public function currentUser(): ?array
    {
        $this->ensureSession();
        $userId = (int) ($_SESSION[self::SESSION_USER_ID_KEY] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $user = Db::queryOne(
            'SELECT id, username, email, status, is_super_admin, two_factor_enabled FROM auth_users WHERE id = ? LIMIT 1',
            $userId
        );

        if (empty($user) || ($user['status'] ?? '') !== 'active') {
            unset($_SESSION[self::SESSION_USER_ID_KEY]);
            return null;
        }

        return $user;
    }

    public function ensurePermission(string $code, string $description = ''): array
    {
        $permission = Db::queryOne('SELECT id, code, description FROM auth_permissions WHERE code = ? LIMIT 1', $code);
        if (!empty($permission)) {
            if ($description !== '' && ($permission['description'] ?? '') !== $description) {
                Db::queryUpdate('UPDATE auth_permissions SET description = ? WHERE id = ?', $description, $permission['id']);
                $permission['description'] = $description;
            }
            return $permission;
        }

        $permissionId = Db::queryInsert(
            'INSERT INTO auth_permissions (code, description) VALUES (?, ?)',
            $code,
            $description !== '' ? $description : null
        );

        return $this->getPermissionById($permissionId);
    }

    public function ensureRole(string $name, string $description = '', array $permissions = []): array
    {
        $role = Db::queryOne('SELECT id, name, description FROM auth_roles WHERE name = ? LIMIT 1', $name);
        if (empty($role)) {
            $roleId = Db::queryInsert(
                'INSERT INTO auth_roles (name, description) VALUES (?, ?)',
                $name,
                $description !== '' ? $description : null
            );
            $role = $this->getRoleById($roleId);
        } elseif ($description !== '' && ($role['description'] ?? '') !== $description) {
            Db::queryUpdate('UPDATE auth_roles SET description = ? WHERE id = ?', $description, $role['id']);
            $role = $this->getRoleById((int) $role['id']);
        }

        if ($permissions !== []) {
            $this->syncRolePermissions((int) $role['id'], $permissions);
            $role = $this->getRoleById((int) $role['id']);
        }

        return $role;
    }

    public function ensureAdminUser(string $username, string $email, string $password): array
    {
        $user = Db::queryOne('SELECT id FROM auth_users WHERE username = ? LIMIT 1', $username);
        if (empty($user)) {
            $userId = Db::queryInsert(
                'INSERT INTO auth_users (username, email, password_hash, status, is_super_admin, two_factor_enabled) VALUES (?, ?, ?, ?, ?, ?)',
                $username,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                'active',
                1,
                0
            );
            $user = ['id' => $userId];
        } else {
            Db::queryUpdate(
                'UPDATE auth_users SET email = ?, status = ?, is_super_admin = ? WHERE id = ?',
                $email,
                'active',
                1,
                $user['id']
            );
        }

        $adminRole = $this->ensureRole('admin', 'Výchozí administrátorská role', ['*']);
        $this->assignRole((int) $user['id'], (int) $adminRole['id']);

        return $this->getUserById((int) $user['id']);
    }

    public function getUserById(int $userId): array
    {
        $user = Db::queryOne(
            'SELECT id, username, email, status, is_super_admin, two_factor_enabled, created_at, updated_at FROM auth_users WHERE id = ? LIMIT 1',
            $userId
        );

        if (empty($user)) {
            throw new \RuntimeException('Uživatel nebyl nalezen.');
        }

        $user['roles'] = $this->getRoleNamesForUser($userId);

        return $user;
    }

    private function getPermissionById(int $permissionId): array
    {
        $permission = Db::queryOne('SELECT id, code, description FROM auth_permissions WHERE id = ? LIMIT 1', $permissionId);
        if (empty($permission)) {
            throw new \RuntimeException('Oprávnění nebylo nalezeno.');
        }

        return $permission;
    }

    private function getRoleById(int $roleId): array
    {
        $role = Db::queryOne('SELECT id, name, description, created_at FROM auth_roles WHERE id = ? LIMIT 1', $roleId);
        if (empty($role)) {
            throw new \RuntimeException('Role nebyla nalezena.');
        }

        $role['permissions'] = $this->getPermissionCodesForRole($roleId);

        return $role;
    }

    private function setUserStatus(int $userId, string $status): array
    {
        Db::queryUpdate('UPDATE auth_users SET status = ? WHERE id = ?', $status, $userId);

        return $this->getUserById($userId);
    }

    private function assignRoleByName(int $userId, string $roleName): void
    {
        $role = $this->ensureRole($roleName);
        $this->assignRole($userId, (int) $role['id']);
    }

    private function getRoleNamesForUser(int $userId): array
    {
        $rows = Db::queryAll(
            'SELECT r.name
             FROM auth_roles r
             INNER JOIN auth_user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = ?',
            $userId
        );

        return array_values(array_filter(array_map(static fn(array $row) => $row['name'] ?? null, $rows)));
    }

    private function getPermissionCodesForRole(int $roleId): array
    {
        $rows = Db::queryAll(
            'SELECT p.code
             FROM auth_permissions p
             INNER JOIN auth_role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?',
            $roleId
        );

        return array_values(array_filter(array_map(static fn(array $row) => $row['code'] ?? null, $rows)));
    }

    private function normalizeStringList(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            $value = trim((string) $item);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
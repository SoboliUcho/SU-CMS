<?php

namespace Modules\AdminModule\Controllers;

use Core\Controller;
use Core\Http\Request;
use Core\Http\Response;
use Core\Modules\ModuleManager;
use Core\View;
use Modules\AdminModule\Services\AdminSettingsService;
use Modules\AuthModule\Services\AuthService;

class AdminWebController extends Controller
{
    private AdminSettingsService $settingsService;
    private AuthService $authService;

    public function __construct()
    {
        $this->settingsService = new AdminSettingsService();
        $this->authService = new AuthService();
    }

    public function loginPage(): Response
    {
        if ($this->authService->currentUser() !== null) {
            return $this->redirect('/admin');
        }

        $flashError = $_SESSION['admin_flash_error'] ?? '';
        unset($_SESSION['admin_flash_error']);

        return new Response(
            View::render('admin/login', [
                'title' => 'Admin Login',
                'base_url' => $this->baseUrl(),
                'error_message' => $flashError,
                'csrf_token' => $this->ensureCsrfToken(),
                'login_form' => form('admin_login', [
                    'fields' => [
                        'csrf_token' => [
                            'value' => $this->ensureCsrfToken(),
                        ],
                    ],
                ]),
            ]),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    public function login(): Response
    {
        $request = Request::capture();
        if (!$this->isValidCsrf((string) $request->input('csrf_token', ''))) {
            $_SESSION['admin_flash_error'] = 'Neplatný CSRF token.';
            return $this->redirect('/admin/login');
        }

        $username = (string) $request->input('username', '');
        $password = (string) $request->input('password', '');
        $user = $this->authService->login($username, $password);

        if ($user === null) {
            $_SESSION['admin_flash_error'] = 'Neplatné přihlašovací údaje.';
            return $this->redirect('/admin/login');
        }

        return $this->redirect('/admin');
    }

    public function logout(): Response
    {
        $request = Request::capture();
        if ($this->isValidCsrf((string) $request->input('csrf_token', ''))) {
            $this->authService->logout();
        }

        return $this->redirect('/admin/login');
    }

    public function dashboard(): Response
    {
        $request = Request::capture();
        $user = $this->authService->authorizeRequest($request, 'admin.panel.view');
        if ($user === null) {
            return $this->redirect('/admin/login');
        }

        $flashMessage = $_SESSION['admin_flash_message'] ?? '';
        $flashError = $_SESSION['admin_flash_error'] ?? '';
        unset($_SESSION['admin_flash_message'], $_SESSION['admin_flash_error']);

        return new Response(
            View::render('admin/dashboard', [
                'title' => 'Administrace',
                'base_url' => $this->baseUrl(),
                'username' => $user['username'] ?? 'admin',
                'flash_message' => $flashMessage,
                'flash_error' => $flashError,
                'csrf_token' => $this->ensureCsrfToken(),
                'module_cards' => $this->renderModuleCards($this->settingsService->catalog()),
                'users_table' => $this->renderUsersTable($this->authService->listUsers()),
                'logout_form' => form('admin_logout', [
                    'fields' => [
                        'csrf_token' => [
                            'value' => $this->ensureCsrfToken(),
                        ],
                    ],
                ]),
                'create_user_form' => form('admin_create_user', [
                    'fields' => [
                        'csrf_token' => [
                            'value' => $this->ensureCsrfToken(),
                        ],
                    ],
                ]),
            ]),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    public function moduleAction(): Response
    {
        $request = Request::capture();
        $user = $this->authService->authorizeRequest($request, 'admin.modules.manage');
        if ($user === null) {
            return $this->redirect('/admin/login');
        }

        if (!$this->isValidCsrf((string) $request->input('csrf_token', ''))) {
            $_SESSION['admin_flash_error'] = 'Neplatný CSRF token.';
            return $this->redirect('/admin');
        }

        $module = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $request->input('module', ''));
        $action = (string) $request->input('action', '');

        if ($module === '' || !in_array($action, ['install', 'update', 'uninstall'], true)) {
            $_SESSION['admin_flash_error'] = 'Neplatná akce modulu.';
            return $this->redirect('/admin');
        }

        try {
            $manager = ModuleManager::instance();
            if ($action === 'install') {
                $manager->install($module);
            } elseif ($action === 'update') {
                $manager->update($module);
            } else {
                $manager->uninstall($module);
            }

            $_SESSION['admin_flash_message'] = 'Akce ' . strtoupper($action) . ' pro modul ' . $module . ' proběhla úspěšně.';
        } catch (\Throwable $e) {
            $_SESSION['admin_flash_error'] = 'Akce selhala: ' . $e->getMessage();
        }

        return $this->redirect('/admin');
    }

    public function userAction(): Response
    {
        $request = Request::capture();
        $user = $this->authService->authorizeRequest($request, 'admin.modules.manage');
        if ($user === null) {
            return $this->redirect('/admin/login');
        }

        if (!$this->isValidCsrf((string) $request->input('csrf_token', ''))) {
            $_SESSION['admin_flash_error'] = 'Neplatný CSRF token.';
            return $this->redirect('/admin#users');
        }

        $action = (string) $request->input('action', '');

        try {
            if ($action === 'create') {
                $roles = $this->parseRoles((string) $request->input('roles', ''));
                $created = $this->authService->createUser([
                    'username' => (string) $request->input('username', ''),
                    'email' => (string) $request->input('email', ''),
                    'password' => (string) $request->input('password', ''),
                    'roles' => $roles,
                ]);
                $_SESSION['admin_flash_message'] = 'Uživatel ' . ($created['username'] ?? '') . ' byl vytvořen.';
                return $this->redirect('/admin#users');
            }

            $targetUserId = (int) $request->input('user_id', 0);
            if ($targetUserId <= 0) {
                throw new \InvalidArgumentException('Neplatné user_id.');
            }

            if ($action === 'activate') {
                $changed = $this->authService->activateUser($targetUserId);
                $_SESSION['admin_flash_message'] = 'Uživatel ' . ($changed['username'] ?? '') . ' byl aktivován.';
            } elseif ($action === 'deactivate') {
                $changed = $this->authService->deactivateUser($targetUserId);
                $_SESSION['admin_flash_message'] = 'Uživatel ' . ($changed['username'] ?? '') . ' byl deaktivován.';
            } elseif ($action === 'delete') {
                $this->authService->deleteUser($targetUserId);
                $_SESSION['admin_flash_message'] = 'Uživatel byl smazán.';
            } else {
                throw new \InvalidArgumentException('Neplatná akce uživatele.');
            }
        } catch (\Throwable $e) {
            $_SESSION['admin_flash_error'] = 'Akce nad uživatelem selhala: ' . $e->getMessage();
        }

        return $this->redirect('/admin#users');
    }

    private function renderModuleCards(array $catalog): string
    {
        if ($catalog === []) {
            return '<p>Žádné aktivní moduly.</p>';
        }

        $cards = [];
        foreach ($catalog as $moduleName => $meta) {
            $safeModule = htmlspecialchars((string) $moduleName, ENT_QUOTES, 'UTF-8');
            $safeVersion = htmlspecialchars((string) ($meta['version'] ?? 'n/a'), ENT_QUOTES, 'UTF-8');
            $safeSettings = htmlspecialchars(
                json_encode($meta['settings'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}',
                ENT_QUOTES,
                'UTF-8'
            );

            $cards[] = '<article class="module-card">'
                . '<h3>' . $safeModule . '</h3>'
                . '<p class="version">Verze: ' . $safeVersion . '</p>'
                . '<details><summary>Nastavení</summary><pre>' . $safeSettings . '</pre></details>'
                . '<div class="actions">'
                . $this->renderActionForm($moduleName, 'install', 'Install')
                . $this->renderActionForm($moduleName, 'update', 'Update')
                . $this->renderActionForm($moduleName, 'uninstall', 'Uninstall', true)
                . '</div>'
                . '</article>';
        }

        return implode('', $cards);
    }

    private function renderActionForm(string $module, string $action, string $label, bool $danger = false): string
    {
        return form('admin_module_action', [
            'fields' => [
                'csrf_token' => [
                    'value' => $this->ensureCsrfToken(),
                ],
                'module' => [
                    'value' => $module,
                ],
                'action' => [
                    'value' => $action,
                ],
                'submit' => [
                    'value' => $label,
                    'class' => $danger ? 'btn danger' : 'btn',
                ],
            ],
        ]);
    }

    private function renderUsersTable(array $users): string
    {
        if ($users === []) {
            return '<p>Žádní uživatelé.</p>';
        }

        $rows = [];
        foreach ($users as $user) {
            $userId = (int) ($user['id'] ?? 0);
            $safeUsername = htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8');
            $safeEmail = htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8');
            $safeStatus = htmlspecialchars((string) ($user['status'] ?? ''), ENT_QUOTES, 'UTF-8');
            $safeRoles = htmlspecialchars(implode(', ', $user['roles'] ?? []), ENT_QUOTES, 'UTF-8');
            $safeCreatedAt = htmlspecialchars((string) ($user['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');

            $actions = $this->renderUserActionForm($userId, 'activate', 'Aktivovat')
                . $this->renderUserActionForm($userId, 'deactivate', 'Deaktivovat')
                . $this->renderUserActionForm($userId, 'delete', 'Smazat', true);

            $rows[] = '<tr>'
                . '<td>' . $userId . '</td>'
                . '<td>' . $safeUsername . '</td>'
                . '<td>' . $safeEmail . '</td>'
                . '<td>' . $safeStatus . '</td>'
                . '<td>' . $safeRoles . '</td>'
                . '<td>' . $safeCreatedAt . '</td>'
                . '<td><div class="user-actions">' . $actions . '</div></td>'
                . '</tr>';
        }

        return '<div class="users-table-wrap"><table class="users-table">'
            . '<thead><tr><th>ID</th><th>Uživatel</th><th>Email</th><th>Stav</th><th>Role</th><th>Vytvořen</th><th>Akce</th></tr></thead>'
            . '<tbody>' . implode('', $rows) . '</tbody>'
            . '</table></div>';
    }

    private function renderUserActionForm(int $userId, string $action, string $label, bool $danger = false): string
    {
        return form('admin_user_action', [
            'fields' => [
                'csrf_token' => [
                    'value' => $this->ensureCsrfToken(),
                ],
                'user_id' => [
                    'value' => (string) $userId,
                ],
                'action' => [
                    'value' => $action,
                ],
                'submit' => [
                    'value' => $label,
                    'class' => $danger ? 'btn danger' : 'btn secondary',
                ],
            ],
        ]);
    }

    private function parseRoles(string $roles): array
    {
        if (trim($roles) === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $roles));
        $parts = array_filter($parts, static fn(string $role) => $role !== '');

        return array_values(array_unique($parts));
    }

    private function ensureCsrfToken(): string
    {
        if (empty($_SESSION['admin_csrf_token'])) {
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['admin_csrf_token'];
    }

    private function isValidCsrf(string $token): bool
    {
        $sessionToken = (string) ($_SESSION['admin_csrf_token'] ?? '');
        if ($sessionToken === '' || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    private function redirect(string $path): Response
    {
        return new Response('', 302, ['Location' => $this->baseUrl() . $path]);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('app.app.base_url', ''), '/');
    }
}

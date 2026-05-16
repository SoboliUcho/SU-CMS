<?php

namespace Modules\AuthModule\Controllers;

use Core\Controller;
use Core\Http\Request;
use Core\Http\Response;
use Modules\AuthModule\Services\AuthService;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function status(): Response
    {
        $currentUser = $this->authService->currentUser();

        return $this->json([
            'module' => 'AuthModule',
            'status' => 'ready',
            'authenticated' => $currentUser !== null,
            'user' => $currentUser,
            'features' => [
                'users',
                'roles',
                'permissions',
                'oauth-preparation',
                'mfa-preparation',
            ],
        ]);
    }

    public function login(): Response
    {
        $request = $this->request();
        $username = (string) $request->input('username', '');
        $password = (string) $request->input('password', '');

        $user = $this->authService->login($username, $password);
        if ($user === null) {
            return $this->json(['ok' => false, 'message' => 'Neplatné přihlašovací údaje.'], 401);
        }

        return $this->json(['ok' => true, 'user' => $user]);
    }

    public function logout(): Response
    {
        $this->authService->logout();

        return $this->json(['ok' => true]);
    }

    public function me(): Response
    {
        $user = $this->authService->currentUser();
        if ($user === null) {
            return $this->json(['ok' => false, 'message' => 'Nepřihlášený uživatel.'], 401);
        }

        return $this->json(['ok' => true, 'user' => $user]);
    }

    public function createUser(): Response
    {
        try {
            $user = $this->authService->createUser($this->request()->all());
            return $this->json(['ok' => true, 'user' => $user], 201);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function activateUser(string $id): Response
    {
        return $this->json(['ok' => true, 'user' => $this->authService->activateUser((int) $id)]);
    }

    public function deactivateUser(string $id): Response
    {
        return $this->json(['ok' => true, 'user' => $this->authService->deactivateUser((int) $id)]);
    }

    public function deleteUser(string $id): Response
    {
        $this->authService->deleteUser((int) $id);
        return $this->json(['ok' => true]);
    }

    public function assignRole(string $id): Response
    {
        $roleId = (int) $this->request()->input('role_id', 0);
        if ($roleId <= 0) {
            return $this->json(['ok' => false, 'message' => 'role_id je povinné.'], 422);
        }

        $this->authService->assignRole((int) $id, $roleId);
        return $this->json(['ok' => true, 'user' => $this->authService->getUserById((int) $id)]);
    }

    public function revokeRole(string $id, string $roleId): Response
    {
        $this->authService->revokeRole((int) $id, (int) $roleId);
        return $this->json(['ok' => true, 'user' => $this->authService->getUserById((int) $id)]);
    }

    public function createRole(): Response
    {
        try {
            $request = $this->request();
            $role = $this->authService->createRole(
                (string) $request->input('name', ''),
                (string) $request->input('description', ''),
                (array) $request->input('permissions', [])
            );

            return $this->json(['ok' => true, 'role' => $role], 201);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function syncRolePermissions(string $id): Response
    {
        $permissions = (array) $this->request()->input('permissions', []);
        $role = $this->authService->syncRolePermissions((int) $id, $permissions);

        return $this->json(['ok' => true, 'role' => $role]);
    }

    private function request(): Request
    {
        return Request::capture();
    }

    private function json(array $payload, int $status = 200): Response
    {
        return new Response(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }
}
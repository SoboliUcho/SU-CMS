<?php

namespace Modules\AdminModule\Controllers;

use Core\Controller;
use Core\Http\Request;
use Core\Http\Response;
use Core\Modules\ModuleManager;
use Modules\AdminModule\Services\AdminSettingsService;
use Modules\AuthModule\Services\AuthService;

class AdminController extends Controller
{
    private AdminSettingsService $settingsService;
    private AuthService $authService;

    public function __construct()
    {
        $this->settingsService = new AdminSettingsService();
        $this->authService = new AuthService();
    }

    public function index(): Response
    {
        $authUser = $this->authorize('admin.panel.view');
        if ($authUser === null) {
            return $this->json(['ok' => false, 'message' => 'Přístup zamítnut.'], 403);
        }

        return $this->json([
            'ok' => true,
            'user' => $authUser,
            'modules' => $this->settingsService->catalog(),
        ]);
    }

    public function install(string $module): Response
    {
        return $this->handleLifecycle('admin.modules.manage', function () use ($module) {
            ModuleManager::instance()->install($module);

            return [
                'ok' => true,
                'message' => 'Modul byl nainstalován.',
                'modules' => $this->settingsService->catalog(),
            ];
        });
    }

    public function update(string $module): Response
    {
        return $this->handleLifecycle('admin.modules.manage', function () use ($module) {
            ModuleManager::instance()->update($module);

            return [
                'ok' => true,
                'message' => 'Modul byl aktualizován.',
                'modules' => $this->settingsService->catalog(),
            ];
        });
    }

    public function uninstall(string $module): Response
    {
        return $this->handleLifecycle('admin.modules.manage', function () use ($module) {
            ModuleManager::instance()->uninstall($module);

            return [
                'ok' => true,
                'message' => 'Modul byl odinstalován.',
            ];
        });
    }

    private function handleLifecycle(string $permission, callable $operation): Response
    {
        $authUser = $this->authorize($permission);
        if ($authUser === null) {
            return $this->json(['ok' => false, 'message' => 'Přístup zamítnut.'], 403);
        }

        try {
            $payload = $operation();
            $payload['user'] = $authUser;

            return $this->json($payload);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function authorize(string $permission): ?array
    {
        return $this->authService->authorizeRequest(Request::capture(), $permission);
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
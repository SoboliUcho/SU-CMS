<?php

namespace App\Controllers\Api;

use Core\Controller;
use Core\Http\Request;
use Core\Http\Response;
use Core\Modules\ModuleManager;

class ModuleInstallController extends Controller
{
    public function installAll(): Response
    {
        $request = Request::capture();

        $authError = $this->authorizeByToken($request);
        if ($authError !== null) {
            return $authError;
        }

        $mode = strtolower((string) $request->input('mode', $request->json('mode', 'install')));
        if (!in_array($mode, ['install', 'update'], true)) {
            return $this->json([
                'ok' => false,
                'message' => 'Neplatný režim. Povolené hodnoty: install, update.',
            ], 422);
        }

        $configuredModules = (array) config('app.modules', []);
        $requestedModules = $this->normalizeModules($request->json('modules', $request->input('modules', [])));

        $targetModules = $requestedModules === []
            ? $configuredModules
            : array_values(array_intersect($configuredModules, $requestedModules));

        if ($targetModules === []) {
            return $this->json([
                'ok' => false,
                'message' => 'Nebyly nalezeny žádné cílové moduly k instalaci.',
                'configured_modules' => $configuredModules,
            ], 422);
        }

        $manager = ModuleManager::instance();
        $results = [];
        $okCount = 0;
        $failCount = 0;

        foreach ($targetModules as $moduleName) {
            try {
                if ($mode === 'install') {
                    $manager->install($moduleName);
                } else {
                    $manager->update($moduleName);
                }

                $results[] = [
                    'module' => $moduleName,
                    'ok' => true,
                    'message' => strtoupper($mode) . ' OK',
                ];
                $okCount++;
            } catch (\Throwable $e) {
                $results[] = [
                    'module' => $moduleName,
                    'ok' => false,
                    'message' => $e->getMessage(),
                ];
                $failCount++;
            }
        }

        $status = $failCount > 0 ? 207 : 200;

        return $this->json([
            'ok' => $failCount === 0,
            'mode' => $mode,
            'summary' => [
                'total' => count($targetModules),
                'ok' => $okCount,
                'failed' => $failCount,
            ],
            'results' => $results,
        ], $status);
    }

    private function authorizeByToken(Request $request): ?Response
    {
        $expectedToken = (string) config('app.setup.install_token', '');
        if ($expectedToken === '') {
            return $this->json([
                'ok' => false,
                'message' => 'Chybí APP_INSTALL_TOKEN. Nastav setup token v konfiguraci prostředí.',
            ], 503);
        }

        $providedToken = $this->extractProvidedToken($request);
        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            return $this->json([
                'ok' => false,
                'message' => 'Neplatný nebo chybějící instalační token.',
            ], 401);
        }

        return null;
    }

    private function extractProvidedToken(Request $request): string
    {
        $headerToken = (string) $request->header('X-Install-Token', '');
        if ($headerToken !== '') {
            return $headerToken;
        }

        $authorization = (string) $request->header('Authorization', '');
        if ($authorization !== '' && str_starts_with(strtolower($authorization), 'bearer ')) {
            return trim(substr($authorization, 7));
        }

        return (string) $request->input('token', $request->json('token', ''));
    }

    private function normalizeModules(mixed $modules): array
    {
        if (is_string($modules)) {
            $modules = array_filter(array_map('trim', explode(',', $modules)));
        }

        if (!is_array($modules)) {
            return [];
        }

        $normalized = [];
        foreach ($modules as $module) {
            if (!is_string($module)) {
                continue;
            }
            $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $module);
            if ($clean !== '') {
                $normalized[] = $clean;
            }
        }

        return array_values(array_unique($normalized));
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

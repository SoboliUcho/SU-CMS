<?php

use Core\Container;
use Core\Http\Router;
use Core\Db;
use Core\Logger;
use Core\Modules\ModuleManager;
use App\Services\FormDefinitionService;
use App\Controllers\Web\FormController;

$container = new Container();
$router = new Router();
$config = require __DIR__ . '/../config/app.php';

Db::connect(
    $config['database']['host'],
    $config['database']['dbname'],
    $config['database']['username'],
    $config['database']['password']
);
/*
|--------------------------------------------------------------------------
| ROUTES (dynamic from config + DB + modules)
|--------------------------------------------------------------------------
*/

// 1) File-based routes (config/routes.php)
$rawRoutes = [];
$routesFile = __DIR__ . '/../config/routes.php';
if (file_exists($routesFile)) {
    $rawRoutes = require $routesFile;
}

// Registrace jedné routy do routeru
$register = function(Router $router, array $route) {
    if (!($route['enabled'] ?? true)) return;
    $method = strtoupper($route['method'] ?? 'GET');
    $uri    = $route['uri'] ?? null;
    $action = $route['action'] ?? null;
    if (!$uri || !$action) return;

    // Support "Controller@method" strings
    if (is_string($action) && str_contains($action, '@')) {
        [$controller, $methodName] = explode('@', $action, 2);
        $callable = [ $controller, $methodName ];
    } else {
        $callable = $action; // assume [class, method]
    }

    $router->routing($method, $uri, $callable);
    // if ($method === 'GET') {
    //     $router->get($uri, $callable);
    // } elseif ($method === 'POST') {
    //     $router->post($uri, $callable);
    // }
};

// Normalizace struktury rout: POUZE hierarchie [group][controller][methodName] => def
$fileRoutes = [];
if (!empty($rawRoutes)) {
    foreach ($rawRoutes as $group => $controllers) {
        if (!is_array($controllers)) {
            Logger::getInstance()->warning('routes.php: očekává se hierarchická struktura [group][controller][method]', [
                'group' => $group,
                'type' => gettype($controllers)
            ]);
            continue;
        }
        foreach ($controllers as $controller => $actions) {
            if (!is_array($actions)) {
                Logger::getInstance()->warning('routes.php: controller musí obsahovat pole metod', [
                    'controller' => $controller,
                    'type' => gettype($actions)
                ]);
                continue;
            }
            foreach ($actions as $methodName => $def) {
                if (!is_array($def)) {
                    Logger::getInstance()->warning('routes.php: definice metody musí být pole', [
                        'controller' => $controller,
                        'method' => $methodName,
                        'type' => gettype($def)
                    ]);
                    continue;
                }
                $controllerClass = $def['action'] ?? null; // třída kontroleru
                $uri = $def['uri'] ?? null;
                if (!$controllerClass || !$uri) {
                    Logger::getInstance()->warning('routes.php: chybí action (controller class) nebo uri', [
                        'controller' => $controller,
                        'method' => $methodName,
                        'def' => $def
                    ]);
                    continue;
                }
                $fileRoutes[] = [
                    'method' => strtoupper($def['method'] ?? 'GET'),
                    'uri'    => $uri,
                    'action' => [$controllerClass, $methodName],
                    'enabled'=> $def['enabled'] ?? true,
                ];
            }
        }
    }
}

foreach ($fileRoutes as $r) { $register($router, $r); }

// 1.5) Auto-registered form endpoints (config/forms/*.php|*.json)
try {
    $formDefinitions = (new FormDefinitionService())->all();
    foreach ($formDefinitions as $definition) {
        $action = $definition['action'] ?? null;
        if (!$action) {
            continue;
        }

        $register($router, [
            'method' => strtoupper($definition['method'] ?? 'POST'),
            'uri' => $action,
            'action' => [FormController::class, 'handle'],
            'enabled' => true,
        ]);
    }
} catch (\Throwable $e) {
    Logger::getInstance()->warning('Auto-registrace formulářových endpointů selhala', [
        'error' => $e->getMessage(),
    ]);
}

// 2) DB-based routes (admin-managed) — optional, if table exists
try {
    $tableExists = \Core\Db::query("SHOW TABLES LIKE 'routes'");
    if ($tableExists && $tableExists->num_rows > 0) {
        $rows = \Core\Db::queryAll("SELECT method, uri, action, enabled FROM routes WHERE enabled = 1");
        foreach ($rows as $row) {
            $register($router, $row);
        }
    }
} catch (\Throwable $e) {
    // Silent fallback: if DB not available or no table, just skip.
}

$moduleManager = ModuleManager::instance();
$moduleManager->bootConfigured(
    $config['modules'] ?? [],
    $router,
    (bool)($config['module_auto_manage'] ?? false)
);

return new class($router, $container) {
    public function __construct(
        private Router $router,
        private Container $container
    ) {}

    public function handle($request)
    {
        return $this->router->dispatch($request, $this->container);
    }
};

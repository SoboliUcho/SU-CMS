# Developer Guide (SU CMS)

This document explains how to:

- create a custom module
- create a standard web page endpoint
- create a standard API endpoint

All examples are based on the current project architecture.

## 1. How Routing Works in This CMS

Routes are loaded from two places:

- config routes file: `config/routes.php`
- module registration: each module registers its own routes in `Module::registerRoutes`

The router supports:

- exact routes (example: `/about`)
- dynamic parameters in braces (example: `/api/users/{id}`)

HTTP methods:

- `GET`
- `POST`
- `PUT`
- `DELETE`

## 2. Create a Standard Web Page

### Step 1: Create a controller

Create file: `app/Controllers/Web/AboutController.php`

```php
<?php

namespace App\Controllers\Web;

use Core\Controller;

class AboutController extends Controller
{
    public function index()
    {
        return $this->view('about', [
            'title' => 'About SU CMS',
            'contentText' => 'This is a standard web page endpoint.',
        ]);
    }
}
```

### Step 2: Create a view

Create file: `app/Views/about.php`

```html
<h1>{{ title }}</h1>
<p>{{ contentText }}</p>
```

### Step 3: Register route in `config/routes.php`

Add route definition in the `web` section:

```php
'web' => [
    'AboutController' => [
        'index' => [
            'method' => 'GET',
            'uri' => '/about',
            'action' => 'App\\Controllers\\Web\\AboutController',
            'enabled' => true,
        ],
    ],
],
```

### Step 4: Test

Open:

- `GET /about`

## 3. Create a Standard API Endpoint

### Step 1: Create a controller

Create file: `app/Controllers/Api/PingController.php`

```php
<?php

namespace App\Controllers\Api;

use Core\Controller;
use Core\Http\Response;

class PingController extends Controller
{
    public function index()
    {
        return new Response(
            json_encode([
                'ok' => true,
                'message' => 'pong',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            200,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }
}
```

### Step 2: Register route in `config/routes.php`

Add route definition in the `api` section:

```php
'api' => [
    'PingController' => [
        'index' => [
            'method' => 'GET',
            'uri' => '/api/ping',
            'action' => 'App\\Controllers\\Api\\PingController',
            'enabled' => true,
        ],
    ],
],
```

### Step 3: Test

Open:

- `GET /api/ping`

Expected response:

```json
{"ok":true,"message":"pong"}
```

## 4. Route Parameters (Web/API)

The router supports path parameters wrapped in braces.

Example route:

```php
'uri' => '/api/users/{id}',
```

Controller method:

```php
public function show(string $id)
{
    // Use $id from URL
}
```

The router passes path params by position to the controller method.

## 5. Create a Custom Module

A module must implement `Core\Modules\ModuleInterface` and be located at:

- `modules/YourModuleName/Module.php`

Because autoload maps `Modules\` to `modules/`, the class name must be:

- `Modules\YourModuleName\Module`

### Recommended module structure

```text
modules/
  YourModule/
    Module.php
    Controllers/
      YourModuleController.php
    Services/
      YourModuleService.php
    views/
      your-module/
        index.php
```

### Minimal `Module.php` example

```php
<?php

namespace Modules\DemoModule;

use Core\Http\Router;
use Core\Modules\ModuleInterface;
use Modules\DemoModule\Controllers\DemoController;

class Module implements ModuleInterface
{
    public function getName(): string
    {
        return 'DemoModule';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function boot(): void
    {
        // Optional runtime boot logic
    }

    public function registerRoutes(Router $router): void
    {
        $router->routing('GET', '/demo', [DemoController::class, 'index']);
        $router->routing('GET', '/api/demo/status', [DemoController::class, 'status']);
    }

    public function install(): void
    {
        // Create module tables, defaults, seed data
    }

    public function update(): void
    {
        // Migration/update logic
        $this->install();
    }

    public function uninstall(): void
    {
        // Optional cleanup/drop tables
    }

    public function settings(): array
    {
        return [
            'general' => [
                'label' => 'Demo Settings',
                'type' => 'group',
                'description' => 'Example module settings.',
            ],
        ];
    }
}
```

### Add module to config

Edit `config/app.php`:

```php
'modules' => [
    'AdminModule',
    'AuthModule',
    'DemoModule',
],
```

## 6. Install/Update Module in Runtime

This project includes endpoint:

- `POST /api/modules/install-all`

Use mode:

- `install`
- `update`

If `module_auto_manage` in `config/app.php` is `false` (default), install/update should be triggered explicitly via API or admin actions.

## 7. Module Controller Example

Create file: `modules/DemoModule/Controllers/DemoController.php`

```php
<?php

namespace Modules\DemoModule\Controllers;

use Core\Controller;
use Core\Http\Response;

class DemoController extends Controller
{
    public function index()
    {
        return $this->view('demo/index', [
            'title' => 'Demo Module Page',
        ]);
    }

    public function status()
    {
        return new Response(
            json_encode(['module' => 'DemoModule', 'status' => 'ok']),
            200,
            ['Content-Type' => 'application/json']
        );
    }
}
```

Create file: `modules/DemoModule/views/demo/index.php`

```html
<h1>{{ title }}</h1>
<p>Demo module is active.</p>
```

## 8. What to Use: `config/routes.php` vs `registerRoutes`

Use `config/routes.php` when:

- endpoint belongs to the core app (`app/Controllers/...`)
- endpoint is not tied to a module package

Use module `registerRoutes` when:

- endpoint belongs to a module
- you want enable/disable/install/update lifecycle around that functionality

## 9. Quick Checklist

- controller class created in correct namespace/path
- route registered with correct method and URI
- action class in route points to existing class
- view file exists for web endpoints
- module added to `config/app.php` (for module routes)
- module installed/updated when schema is required

## 10. Common Mistakes

- wrong namespace (autoload cannot find class)
- route method mismatch (calling POST route via GET)
- missing `Content-Type` header for JSON responses
- expecting module DB tables without running module install/update
- forgetting to add module name to `config/app.php`

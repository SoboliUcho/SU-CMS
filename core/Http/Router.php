<?php

namespace Core\Http;

use Core\Logger;

class Router
{
    private array $routes = [];

    private function normalizeUri(string $uri): string
    {
        if (strpos($uri, '/') !== 0) {
            $uri = '/' . $uri;
        }
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }
        return $uri;
    }

    private function compile(string $uri): array
    {
        // Convert /users/{id}/posts/{postId} to regex and capture names
        $paramNames = [];
        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($m) use (&$paramNames) {
            $paramNames[] = $m[1];
            return '([^/]+)';
        }, $uri);
        // Anchor pattern
        $pattern = '#^' . $pattern . '$#';
        return [$pattern, $paramNames];
    }

    public function routing(string $method, string $uri, array $action)
    {
        $method = strtoupper($method);
        $uri = $this->normalizeUri($uri);
        [$pattern, $params] = $this->compile($uri);
        $this->routes[$method][] = [
            'uri' => $uri,
            'action' => $action,
            'pattern' => $pattern,
            'params' => $params,
        ];
    }

    // public function get(string $uri, array $action)
    // {   
    //     $uri = $this->normalizeUri($uri);
    //     [$pattern, $params] = $this->compile($uri);
    //     $this->routes['GET'][] = [
    //         'uri' => $uri,
    //         'action' => $action,
    //         'pattern' => $pattern,
    //         'params' => $params,
    //     ];
    // }

    // public function post(string $uri, array $action)
    // {   
    //     $uri = $this->normalizeUri($uri);
    //     [$pattern, $params] = $this->compile($uri);
    //     $this->routes['POST'][] = [
    //         'uri' => $uri,
    //         'action' => $action,
    //         'pattern' => $pattern,
    //         'params' => $params,
    //     ];
    // }

    // public function put(string $uri, array $action)
    // {   
    //     $uri = $this->normalizeUri($uri);
    //     [$pattern, $params] = $this->compile($uri);
    //     $this->routes['PUT'][] = [
    //         'uri' => $uri,
    //         'action' => $action,
    //         'pattern' => $pattern,
    //         'params' => $params,
    //     ];
    // }
    // public function delete(string $uri, array $action)
    // {   
    //     $uri = $this->normalizeUri($uri);
    //     [$pattern, $params] = $this->compile($uri);
    //     $this->routes['DELETE'][] = [
    //         'uri' => $uri,
    //         'action' => $action,
    //         'pattern' => $pattern,
    //         'params' => $params,
    //     ];
    // }

    public function dispatch(Request $request, $container)
    {
        $method = $request->method();
        $uri = $request->uri();
        // Logger::getInstance()->debug("Dispatching request: $method $uri");

        if (!isset($this->routes[$method])) {

            Logger::getInstance()->debug("No routes defined for method: $method");
            if (config("app.page_settings.404")) {
                $content = file_get_contents(__DIR__ . "/../../app/Views/" . config("app.page_settings.404"));
                return new Response($content, 404, ['Content-Type' => 'text/html']);
            }
            return new Response('Not Found 404', 404, ['Content-Type' => 'text/plain']);
        }

        // Try exact match first
        foreach ($this->routes[$method] as $route) {
            if ($route['uri'] === $uri) {
                [$class, $methodName] = $route['action'];
                $controller = $container->make($class);
                return call_user_func([$controller, $methodName]);
            }
        }

        // Try pattern match with params
        foreach ($this->routes[$method] as $route) {
            if (preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches); // remove full match
                $params = $matches;    // positional params
                [$class, $methodName] = $route['action'];
                $controller = $container->make($class);
                return call_user_func_array([$controller, $methodName], $params);
            }
        }

        if (config("app.page_settings.404")) {
            $content = file_get_contents(__DIR__ . "/../../app/Views/" . config("app.page_settings.404"));
            return new Response($content, 404, ['Content-Type' => 'text/html']);
        }

        return new Response('Not Found 404', 404, ['Content-Type' => 'text/plain']);
    }
}

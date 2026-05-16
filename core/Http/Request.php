<?php

namespace Core\Http;

use Core\Config;

class Request
{
    private string $method;
    private string $uri;
    private array $queryParams = [];
    private array $bodyParams = [];
    private array $jsonParams = [];
    private array $headers = [];
    private string $rawBody = '';

    public static function capture(): self
    {
        $instance = new self();
        $instance->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        // Odstranit query string
        $baseUrl = (string) Config::get('app.app.base_url', '');
        $requestUri = str_replace($baseUrl, '', $requestUri);
        $instance->uri = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        if (strpos($instance->uri, '/') !== 0) {
            $instance->uri = '/' . $instance->uri;
        }

        $instance->queryParams = $_GET ?? [];
        $instance->headers = self::collectHeaders();
        $instance->rawBody = file_get_contents('php://input') ?: '';
        $instance->bodyParams = $_POST ?? [];

        $contentType = strtolower((string)($instance->header('Content-Type') ?? ''));
        if ($instance->rawBody !== '' && str_contains($contentType, 'application/json')) {
            $decoded = json_decode($instance->rawBody, true);
            if (is_array($decoded)) {
                $instance->jsonParams = $decoded;
            }
        }

        if ($instance->rawBody !== '' && empty($instance->bodyParams) && in_array($instance->method, ['PUT', 'PATCH', 'DELETE'], true)) {
            parse_str($instance->rawBody, $parsedBody);
            if (is_array($parsedBody)) {
                $instance->bodyParams = $parsedBody;
            }
        }

        return $instance;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return rtrim($this->uri, '/') ?: '/';
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }
        return $this->queryParams[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->bodyParams;
        }
        return $this->bodyParams[$key] ?? $default;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->jsonParams;
        }
        return $this->jsonParams[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->bodyParams)) {
            return $this->bodyParams[$key];
        }
        if (array_key_exists($key, $this->jsonParams)) {
            return $this->jsonParams[$key];
        }
        return $this->queryParams[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->jsonParams, $this->bodyParams);
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $normalized = $this->normalizeHeaderName($name);
        return $this->headers[$normalized] ?? $default;
    }

    public function isAjax(): bool
    {
        $requestedWith = strtolower((string)$this->header('X-Requested-With', ''));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower((string)$this->header('Accept', ''));
        return str_contains($accept, 'application/json');
    }

    public function ip(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function body(): string
    {
        return $this->rawBody;
    }

    private static function collectHeaders(): array
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $normalized = self::normalizeHeaderNameStatic((string)$name);
                $headers[$normalized] = $value;
            }
            return $headers;
        }

        foreach ($_SERVER as $key => $value) {
            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }
            $name = str_replace('_', '-', substr($key, 5));
            $normalized = self::normalizeHeaderNameStatic($name);
            $headers[$normalized] = $value;
        }

        return $headers;
    }

    private function normalizeHeaderName(string $name): string
    {
        return self::normalizeHeaderNameStatic($name);
    }

    private static function normalizeHeaderNameStatic(string $name): string
    {
        $name = trim($name);
        $name = str_replace('_', '-', strtolower($name));
        $parts = explode('-', $name);
        $parts = array_map(static fn(string $part) => $part === '' ? '' : ucfirst($part), $parts);
        return implode('-', $parts);
    }
}

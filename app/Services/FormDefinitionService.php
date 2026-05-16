<?php

namespace App\Services;

class FormDefinitionService
{
    private string $formsPath;

    public function __construct(?string $formsPath = null)
    {
        $this->formsPath = $formsPath ?? __DIR__ . '/../../config/forms';
    }

    public function all(): array
    {
        $definitions = [];

        foreach (glob($this->formsPath . '/*.php') ?: [] as $filePath) {
            $name = pathinfo($filePath, PATHINFO_FILENAME);
            $loaded = require $filePath;
            if (!is_array($loaded)) {
                continue;
            }
            $formName = (string)($loaded['name'] ?? $name);
            $definitions[$formName] = $this->normalizeDefinition($formName, $loaded);
        }

        foreach (glob($this->formsPath . '/*.json') ?: [] as $filePath) {
            $name = pathinfo($filePath, PATHINFO_FILENAME);
            $raw = file_get_contents($filePath);
            if ($raw === false) {
                continue;
            }
            $loaded = json_decode($raw, true);
            if (!is_array($loaded)) {
                continue;
            }
            $formName = (string)($loaded['name'] ?? $name);
            $definitions[$formName] = $this->normalizeDefinition($formName, $loaded);
        }

        return $definitions;
    }

    public function load(string $name): ?array
    {
        $definitions = $this->all();
        return $definitions[$name] ?? null;
    }

    public function findByEndpoint(string $uri, string $method): ?array
    {
        $normalizedUri = $this->normalizeUri($uri);
        $normalizedMethod = strtoupper($method);

        foreach ($this->all() as $definition) {
            $formMethod = strtoupper((string)($definition['method'] ?? 'POST'));
            $formAction = $this->normalizeUri((string)($definition['action'] ?? ''));

            if ($formMethod === $normalizedMethod && $formAction === $normalizedUri) {
                return $definition;
            }
        }

        return null;
    }

    private function normalizeDefinition(string $name, array $definition): array
    {
        $defaults = [
            'name' => $name,
            'action' => '/forms/submit/' . $name,
            'method' => 'POST',
            'ajax' => false,
            'submit_on_change' => false,
            'debounce_ms' => 500,
            'rate_limit' => [
                'enabled' => true,
                'submit' => [
                    'max_requests' => 5,
                    'window_sec' => 60,
                ],
                'change' => [
                    'max_requests' => 20,
                    'window_sec' => 30,
                ],
            ],
            'fields' => [],
        ];

        $normalized = array_replace_recursive($defaults, $definition);
        $normalized['method'] = strtoupper((string)($normalized['method'] ?? 'POST'));
        $normalized['action'] = $this->normalizeUri((string)($normalized['action'] ?? ''));
        $normalized['name'] = (string)($normalized['name'] ?? $name);

        return $normalized;
    }

    private function normalizeUri(string $uri): string
    {
        $uri = trim($uri);
        if ($uri === '') {
            return '/';
        }
        if (strpos($uri, '/') !== 0) {
            $uri = '/' . $uri;
        }
        return rtrim($uri, '/') ?: '/';
    }
}

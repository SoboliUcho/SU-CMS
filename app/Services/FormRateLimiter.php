<?php

namespace App\Services;

class FormRateLimiter
{
    private string $filePath;

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath ?? __DIR__ . '/../../storage/cache/form_rate_limit.json';
    }

    public function checkAndHit(string $key, int $maxRequests, int $windowSec): array
    {
        if ($maxRequests <= 0 || $windowSec <= 0) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        $now = time();
        $this->ensureStorageDirectory();

        $handle = fopen($this->filePath, 'c+');
        if ($handle === false) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        flock($handle, LOCK_EX);
        $raw = stream_get_contents($handle);
        $state = json_decode($raw ?: '{}', true);
        if (!is_array($state)) {
            $state = [];
        }

        $entry = $state[$key] ?? [
            'count' => 0,
            'reset_at' => $now + $windowSec,
        ];

        if (($entry['reset_at'] ?? 0) <= $now) {
            $entry['count'] = 0;
            $entry['reset_at'] = $now + $windowSec;
        }

        $entry['count'] = (int)($entry['count'] ?? 0) + 1;
        $state[$key] = $entry;

        foreach ($state as $stateKey => $stateEntry) {
            $resetAt = (int)($stateEntry['reset_at'] ?? 0);
            if ($resetAt !== 0 && $resetAt < ($now - 3600)) {
                unset($state[$stateKey]);
            }
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        $allowed = $entry['count'] <= $maxRequests;
        $retryAfter = $allowed ? 0 : max(1, (int)$entry['reset_at'] - $now);

        return [
            'allowed' => $allowed,
            'retry_after' => $retryAfter,
        ];
    }

    private function ensureStorageDirectory(): void
    {
        $directory = dirname($this->filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }
}

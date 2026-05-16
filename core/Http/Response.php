<?php

namespace Core\Http;
/**
 * Represents an HTTP response consisting of a body, status code, and headers.
 *
 * This class provides a simple interface to build and send HTTP responses.
 * You can mutate the response via setter methods and then emit it with send().
 * Note: http_response_code() and header() must be called before any output is sent.
 *
 * @author Soboli Ucho
 */



class Response
{
    /** @var bool Prevent double send */
    private bool $sent = false;
    /**
     * Initialize a new Response instance.
     *
     * @param string $body    The response body content. Defaults to an empty string.
     * @param int    $status  The HTTP status code. Defaults to 200.
     * @param array<string,string> $headers Associative array of header name => value.
     */
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = []
    ) {
    }

/**
     * Send the HTTP response to the client.
     *
     * Sets the HTTP status code, sends headers, and outputs the body.
     * This method uses http_response_code(), header(), and echo internally.
     *
     * @return void
     */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }

        $isCli = PHP_SAPI === 'cli';
        $status = $this->status;
        $sendBody = !in_array($status, [204, 304], true);

        // Default Content-Type if not set
        if (!$this->hasHeader('Content-Type')) {
            $this->set_header('Content-Type', 'text/html; charset=UTF-8');
        }

        // Content-Length if applicable and not set yet
        if ($sendBody && !$this->hasHeader('Content-Length')) {
            $length = strlen($this->body); // bytes
            $this->set_header('Content-Length', (string)$length);
        }
        if (!$isCli) {
            http_response_code($status);

            if (headers_sent($file, $line)) {
                error_log("Headers already sent at $file:$line; skipping header() calls.");
            } else {
                foreach ($this->headers as $name => $value) {
                    $name = $this->normalizeHeaderName($name);
                    if (is_array($value)) {
                        foreach ($value as $v) {
                            header("$name: $v", false);
                        }
                    } else {
                        header("$name: $value", true);
                    }
                }
            }
        }

        if ($sendBody) {
            echo $this->body;
        }

        $this->sent = true;
    }
    /**
     * Set the HTTP status code for the response.
     *
     * @param int $status The HTTP status code (e.g., 200, 404, 500).
     * @return void
     */

    public function set_status(int $status): void
    {
        $this->status = $status;
    }

     /**
     * Set or override a header on the response.
     * If header already exists with multiple values, it will be replaced by a single value.
     *
     * @param string $name  The header name (e.g., "Content-Type").
     * @param string $value The header value.
     * @return void
     */
    public function set_header(string $name, string $value): void
    {
        $this->assertHeaderNameSafe($name);
        $name = $this->normalizeHeaderName($name);
        $this->headers[$name] = $value;
    }
     /**
     * Add a header value without replacing existing ones (e.g., Set-Cookie, Cache-Control).
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    public function add_header(string $name, string $value): void
    {
        $this->assertHeaderNameSafe($name);
        $name = $this->normalizeHeaderName($name);
        if (!isset($this->headers[$name])) {
            $this->headers[$name] = $value;
            return;
        }
        if (is_array($this->headers[$name])) {
            $this->headers[$name][] = $value;
        } else {
            $this->headers[$name] = [$this->headers[$name], $value];
        }
    }

    /**
     * Check if header exists (case-insensitive canonical form).
     */
    public function hasHeader(string $name): bool
    {
        $name = $this->normalizeHeaderName($name);
        return array_key_exists($name, $this->headers);
    }
        /**
     * Normalize header name to canonical case (e.g., content-type -> Content-Type).
     */
    private function normalizeHeaderName(string $name): string
    {
        $name = trim($name);
        $name = str_replace('_', '-', $name);
        // Canonicalize word casing
        $parts = explode('-', strtolower($name));
        $parts = array_map(static fn($p) => $p === '' ? '' : ucfirst($p), $parts);
        return implode('-', $parts);
    }
    
    /**
     * Basic safety: prevent CRLF injection in header names.
     */
    private function assertHeaderNameSafe(string $name): void
    {
        if (strpbrk($name, "\r\n") !== false) {
            throw new \InvalidArgumentException('Invalid header name.');
        }
    }
    /**
     * Replace the response body.
     *
     * @param string $body The response body content.
     * @return void
     */
    public function set_body(string $body): void
    {
        $this->body = $body;
    }
}

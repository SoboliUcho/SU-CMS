<?php

namespace App\Controllers\Web;

use App\Services\FormDefinitionService;
use App\Services\FormRateLimiter;
use App\Services\FormValidator;
use Core\Controller;
use Core\Http\Request;
use Core\Http\Response;

class FormController extends Controller
{
    private FormDefinitionService $formDefinitions;
    private FormValidator $validator;
    private FormRateLimiter $rateLimiter;

    public function __construct()
    {
        $this->formDefinitions = new FormDefinitionService();
        $this->validator = new FormValidator();
        $this->rateLimiter = new FormRateLimiter();
    }

    public function handle(): Response
    {
        $request = Request::capture();
        $definition = $this->formDefinitions->findByEndpoint($request->uri(), $request->method());

        if ($definition === null) {
            return $this->respondError($request, 404, 'Pro danou kombinaci endpointu a metody nebyl nalezen formulář.');
        }

        $payload = $this->resolvePayload($request);
        $trigger = strtolower((string)($payload['__submit_trigger'] ?? ($definition['submit_on_change'] ? 'change' : 'submit')));
        if (!in_array($trigger, ['submit', 'change'], true)) {
            $trigger = 'submit';
        }

        $rateConfig = $this->resolveRateLimitConfig($definition, $trigger);
        if (($rateConfig['enabled'] ?? true) === true) {
            $limiterResult = $this->rateLimiter->checkAndHit(
                $this->buildRateLimitKey($definition, $request, $trigger),
                (int)$rateConfig['max_requests'],
                (int)$rateConfig['window_sec']
            );

            if (!$limiterResult['allowed']) {
                return $this->respond(
                    $request,
                    [
                        'ok' => false,
                        'form' => $definition['name'],
                        'trigger' => $trigger,
                        'message' => 'Byl překročen limit požadavků, opakuj akci později.',
                        'retry_after' => $limiterResult['retry_after'],
                    ],
                    429,
                    ['Retry-After' => (string)$limiterResult['retry_after']]
                );
            }
        }

        $validation = $this->validator->validate($definition, $payload);
        if (!$validation['valid']) {
            return $this->respond(
                $request,
                [
                    'ok' => false,
                    'form' => $definition['name'],
                    'trigger' => $trigger,
                    'message' => 'Formulář obsahuje validační chyby.',
                    'errors' => $validation['errors'],
                ],
                422
            );
        }

        return $this->respond(
            $request,
            [
                'ok' => true,
                'form' => $definition['name'],
                'trigger' => $trigger,
                'message' => 'Formulář byl úspěšně zpracován.',
                'data' => $validation['values'],
            ],
            200
        );
    }

    private function resolvePayload(Request $request): array
    {
        if ($request->method() === 'GET') {
            return $request->query();
        }

        $jsonPayload = $request->json();
        if (is_array($jsonPayload) && !empty($jsonPayload)) {
            return $jsonPayload;
        }

        return $request->post();
    }

    private function resolveRateLimitConfig(array $definition, string $trigger): array
    {
        $rateLimit = $definition['rate_limit'] ?? [];
        if (!is_array($rateLimit)) {
            $rateLimit = [];
        }

        $triggerLimits = $rateLimit[$trigger] ?? [];
        if (!is_array($triggerLimits)) {
            $triggerLimits = [];
        }

        return [
            'enabled' => (bool)($rateLimit['enabled'] ?? true),
            'max_requests' => (int)($triggerLimits['max_requests'] ?? ($trigger === 'change' ? 20 : 5)),
            'window_sec' => (int)($triggerLimits['window_sec'] ?? ($trigger === 'change' ? 30 : 60)),
        ];
    }

    private function buildRateLimitKey(array $definition, Request $request, string $trigger): string
    {
        $sessionId = session_name() ? ($_COOKIE[session_name()] ?? 'guest') : 'guest';
        $formName = (string)($definition['name'] ?? 'unknown');

        return implode('|', [
            'form',
            $formName,
            strtoupper($request->method()),
            $request->ip(),
            $sessionId,
            $trigger,
        ]);
    }

    private function respondError(Request $request, int $status, string $message): Response
    {
        return $this->respond($request, ['ok' => false, 'message' => $message], $status);
    }

    private function respond(Request $request, array $payload, int $status, array $extraHeaders = []): Response
    {
        if ($request->isAjax()) {
            $headers = array_merge([
                'Content-Type' => 'application/json; charset=utf-8',
            ], $extraHeaders);

            return new Response(
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $status,
                $headers
            );
        }

        $lines = [];
        $lines[] = '<h2>' . htmlspecialchars($payload['message'] ?? 'Výsledek formuláře', ENT_QUOTES, 'UTF-8') . '</h2>';

        if (!empty($payload['errors']) && is_array($payload['errors'])) {
            $lines[] = '<ul>';
            foreach ($payload['errors'] as $name => $error) {
                $safeName = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
                $safeError = htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8');
                $lines[] = '<li><strong>' . $safeName . '</strong>: ' . $safeError . '</li>';
            }
            $lines[] = '</ul>';
        }

        return new Response(
            implode("\n", $lines),
            $status,
            array_merge(['Content-Type' => 'text/html; charset=utf-8'], $extraHeaders)
        );
    }
}

<?php

declare(strict_types=1);

namespace app\components;

use Throwable;
use yii\web\Request;

/**
 * Данные для диагностики без пользовательских значений.
 */
final class DiagnosticData
{
    private const int MAX_BODY_BYTES = 8192;
    private const array LOG_EVENTS = [
        'outbox_published',
        'publisher_started',
        'publisher_stopped',
        'worker_started',
        'worker_stopped',
        'task_event_requeued',
    ];

    /**
     * @param Request $request
     * @param string|null $route
     * @return array<string, mixed>
     */
    public static function request(Request $request, ?string $route): array
    {
        $data = ['method' => $request->getMethod()];
        if ($route !== null && $route !== '') {
            $data['route'] = $route;
        }

        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            return $data;
        }

        $contentType = $request->getContentType();
        $contentLength = $request->getHeaders()->get('Content-Length');
        if (
            !str_starts_with($contentType, 'application/json')
            || !ctype_digit($contentLength)
            || (int) $contentLength > self::MAX_BODY_BYTES
        ) {
            return $data;
        }

        try {
            $body = $request->getBodyParams();
        } catch (Throwable) {
            return $data;
        }

        if (is_array($body)) {
            $data['data'] = self::body($body);
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $body
     * @return array<string, mixed>
     */
    public static function body(array $body): array
    {
        $safe = [];
        foreach (['title', 'description', 'fullName', 'phone', 'authorId'] as $name) {
            if (array_key_exists($name, $body)) {
                $value = $body[$name];
                $safe[$name] = [
                    'type' => get_debug_type($value),
                    'length' => is_string($value) ? mb_strlen($value) : null,
                ];
            }
        }

        if (array_key_exists('completed', $body)) {
            $safe['completed'] = is_bool($body['completed'])
                ? $body['completed']
                : ['type' => get_debug_type($body['completed'])];
        }

        $safe['unknownFieldCount'] = count(array_diff(array_keys($body), [
            'title', 'description', 'fullName', 'phone', 'authorId', 'completed',
        ]));

        return $safe;
    }

    /**
     * @param array<array-key, mixed> $context
     * @return array<string, string|int>
     */
    public static function logContext(array $context): array
    {
        $safe = [];
        $event = $context['event'] ?? null;
        if (in_array($event, self::LOG_EVENTS, true)) {
            $safe['event'] = $event;
        }

        foreach (['count', 'eventId', 'attempt'] as $name) {
            if (isset($context[$name]) && is_int($context[$name])) {
                $safe[$name] = $context[$name];
            }
        }

        if (in_array($context['destination'] ?? null, ['retry', 'dead'], true)) {
            $safe['destination'] = $context['destination'];
        }

        return $safe;
    }
}

<?php

declare(strict_types=1);

namespace app\components;

use Throwable;
use yii\db\Exception as DbException;
use yii\log\Logger;
use yii\log\Target;

final class JsonLogTarget extends Target
{
    public ?RequestContext $requestContext = null;

    /**
     * @return void
     */
    public function export(): void
    {
        foreach ($this->messages as [$message, $level, $category, $timestamp]) {
            $record = [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z', (int) $timestamp),
                'level' => Logger::getLevelName($level),
                'component' => $_ENV['APP_COMPONENT'] ?? (defined('YII_CONSOLE') ? 'console' : 'api'),
                'request_id' => $this->requestContext?->getRequestId(),
                'category' => $category,
            ];

            if ($message instanceof Throwable) {
                $record['message'] = $message instanceof DbException
                    ? 'Ошибка базы данных.'
                    : $message->getMessage();
                $record['exception'] = [
                    'type' => $message::class,
                    'code' => $message->getCode(),
                    'file' => $message->getFile(),
                    'line' => $message->getLine(),
                    'trace' => array_map(
                        static fn(array $frame): array => [
                            'file' => $frame['file'] ?? null,
                            'line' => $frame['line'] ?? null,
                            'call' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'],
                        ],
                        $message->getTrace(),
                    ),
                ];
            } elseif (is_array($message)) {
                $record['context'] = $message;
            } else {
                $record['message'] = is_scalar($message) ? (string) $message : get_debug_type($message);
            }

            $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($json !== false) {
                file_put_contents('php://stderr', $json . "\n");
            }
        }
    }
}

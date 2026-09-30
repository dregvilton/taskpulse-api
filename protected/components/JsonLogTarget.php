<?php

declare(strict_types=1);

namespace app\components;

use Throwable;
use yii\log\FileTarget;
use yii\log\Logger;

final class JsonLogTarget extends FileTarget
{
    public ?RequestContext $requestContext = null;

    /**
     * @return void
     */
    public function init(): void
    {
        $this->logFile = 'php://stderr';
        $this->enableRotation = false;
        parent::init();
    }

    /**
     * @param array{0: mixed, 1: int, 2: string, 3: int|float} $message
     * @return string
     */
    public function formatMessage($message): string
    {
        [$value, $level, $category, $timestamp] = $message;
        $record = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z', (int) $timestamp),
            'level' => Logger::getLevelName($level),
            'component' => $_ENV['APP_COMPONENT'] ?? (defined('YII_CONSOLE') ? 'console' : 'api'),
            'request_id' => $this->requestContext?->getRequestId(),
            'category' => $category,
        ];

        if ($value instanceof Throwable) {
            $record['message'] = 'Ошибка приложения.';
            $record['exception'] = [
                'type' => $value::class,
                'code' => $value->getCode(),
                'file' => $value->getFile(),
                'line' => $value->getLine(),
            ];
        } elseif (is_array($value)) {
            $record['context'] = DiagnosticData::logContext($value);
        } else {
            $record['message'] = 'Сообщение скрыто для защиты данных.';
        }

        return json_encode($record, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR)
            ?: '{"message":"Ошибка форматирования лога."}';
    }
}

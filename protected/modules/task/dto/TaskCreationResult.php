<?php

declare(strict_types=1);

namespace app\modules\task\dto;

/**
 * Снимок ответа на создание задачи.
 */
final readonly class TaskCreationResult
{
    /**
     * @param int $taskId
     * @param array<string, mixed> $body
     * @return void
     */
    public function __construct(
        public int $taskId,
        public array $body,
    ) {}
}

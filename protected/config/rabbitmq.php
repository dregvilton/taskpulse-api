<?php

declare(strict_types=1);

use app\services\TaskEventBroker;
use app\services\TaskEventTopology;

return static function (): TaskEventBroker {
    return new TaskEventBroker(
        new TaskEventTopology(),
        $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq',
        (int) ($_ENV['RABBITMQ_PORT'] ?? 5672),
        $_ENV['RABBITMQ_USER'] ?? 'taskpulse',
        $_ENV['RABBITMQ_PASSWORD'] ?? 'taskpulse',
    );
};

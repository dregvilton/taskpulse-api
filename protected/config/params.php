<?php

declare(strict_types=1);

return [
    'appName' => 'TaskPulse API',
    'demoMode' => filter_var($_ENV['APP_DEMO_MODE'] ?? false, FILTER_VALIDATE_BOOL),
    'demoEmail' => mb_strtolower(trim($_ENV['DEMO_EMAIL'] ?? '')),
];

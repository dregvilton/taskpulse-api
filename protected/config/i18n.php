<?php

declare(strict_types=1);

use yii\i18n\PhpMessageSource;

return [
    'translations' => [
        'app*' => [
            'class' => PhpMessageSource::class,
            'basePath' => '@app/messages',
            'fileMap' => [
                'app' => 'app.php',
            ],
        ],
        'user*' => [
            'class' => PhpMessageSource::class,
            'basePath' => '@app/messages',
            'fileMap' => [
                'user' => 'user.php',
            ],
        ],
        'task*' => [
            'class' => PhpMessageSource::class,
            'basePath' => '@app/messages',
            'fileMap' => [
                'task' => 'task.php',
            ],
        ],
    ],
];

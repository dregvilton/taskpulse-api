<?php

declare(strict_types=1);

namespace app\modules\task\exceptions;

use RuntimeException;

/**
 * Задача не найдена.
 */
final class TaskNotFoundException extends RuntimeException {}

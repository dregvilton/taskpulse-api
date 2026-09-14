<?php

declare(strict_types=1);

namespace app\modules\task\exceptions;

use RuntimeException;

/**
 * Не удалось сохранить задачу.
 */
final class TaskSaveException extends RuntimeException {}

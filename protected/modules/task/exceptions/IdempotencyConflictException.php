<?php

declare(strict_types=1);

namespace app\modules\task\exceptions;

use RuntimeException;

/**
 * Ключ уже использован для другого запроса.
 */
final class IdempotencyConflictException extends RuntimeException {}

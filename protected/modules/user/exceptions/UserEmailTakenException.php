<?php

declare(strict_types=1);

namespace app\modules\user\exceptions;

use RuntimeException;

/**
 * Почта уже занята другим пользователем.
 */
final class UserEmailTakenException extends RuntimeException {}

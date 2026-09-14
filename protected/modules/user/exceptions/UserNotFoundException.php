<?php

declare(strict_types=1);

namespace app\modules\user\exceptions;

use RuntimeException;

/**
 * Пользователь не найден.
 */
final class UserNotFoundException extends RuntimeException {}

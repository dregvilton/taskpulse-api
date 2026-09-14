<?php

declare(strict_types=1);

namespace app\modules\user\exceptions;

use RuntimeException;

/**
 * Не удалось сохранить пользователя.
 */
final class UserSaveException extends RuntimeException {}

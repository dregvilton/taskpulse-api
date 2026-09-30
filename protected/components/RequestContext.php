<?php

declare(strict_types=1);

namespace app\components;

use Random\RandomException;

final class RequestContext
{
    private ?string $requestId = null;

    /**
     * @param string|null $incomingId
     * @return void
     * @throws RandomException
     */
    public function start(?string $incomingId): void
    {
        $this->requestId = $incomingId !== null
            && preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/D', $incomingId) === 1
                ? $incomingId
                : bin2hex(random_bytes(16));
    }

    /**
     * @return string|null
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}

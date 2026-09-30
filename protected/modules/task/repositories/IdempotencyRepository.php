<?php

declare(strict_types=1);

namespace app\modules\task\repositories;

use app\extensions\DbConnectTrait;
use RuntimeException;
use yii\base\InvalidConfigException;
use yii\db\Exception;

/**
 * Хранение ключей идемпотентности в PostgreSQL.
 */
final class IdempotencyRepository
{
    use DbConnectTrait;

    /**
     * @param int $userId
     * @param string $key
     * @return array<string, mixed>|false
     * @throws InvalidConfigException|Exception|RuntimeException
     */
    public function getByKey(int $userId, string $key): array|false
    {
        return $this->getDbConnection()
            ->createCommand(__DIR__ . '/sqls/get_idempotency_key.sql')
            ->bindValue(':idempotencyKey', $key)
            ->bindValue(':userId', $userId)
            ->queryOne();
    }

    /**
     * @param int $userId
     * @param string $key
     * @param string $requestHash
     * @return string|false
     * @throws InvalidConfigException|Exception|RuntimeException
     */
    public function claim(int $userId, string $key, string $requestHash): string|false
    {
        return $this->getDbConnection()
            ->createCommand(__DIR__ . '/sqls/claim_idempotency_key.sql')
            ->bindValues([
                ':idempotencyKey' => $key,
                ':userId' => $userId,
                ':requestHash' => $requestHash,
            ])
            ->queryScalar();
    }

    /**
     * @param int $userId
     * @param string $key
     * @param int $taskId
     * @param string $responseBody
     * @return int
     * @throws InvalidConfigException|Exception|RuntimeException
     */
    public function complete(int $userId, string $key, int $taskId, string $responseBody): int
    {
        return $this->getDbConnection()
            ->createCommand(__DIR__ . '/sqls/complete_idempotency_key.sql')
            ->bindValues([
                ':idempotencyKey' => $key,
                ':userId' => $userId,
                ':taskId' => $taskId,
                ':responseBody' => $responseBody,
            ])
            ->execute();
    }
}

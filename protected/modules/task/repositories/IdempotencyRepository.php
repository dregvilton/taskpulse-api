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
     * @param string $key
     * @return array<string, mixed>|false
     * @throws InvalidConfigException|Exception|RuntimeException
     */
    public function getByKey(string $key): array|false
    {
        return $this->getDbConnection()
            ->createCommand(__DIR__ . '/sqls/get_idempotency_key.sql')
            ->bindValue(':idempotencyKey', $key)
            ->queryOne();
    }

    /**
     * @param string $key
     * @param string $requestHash
     * @return string|false
     * @throws InvalidConfigException|Exception|RuntimeException
     */
    public function claim(string $key, string $requestHash): string|false
    {
        return $this->getDbConnection()
            ->createCommand(__DIR__ . '/sqls/claim_idempotency_key.sql')
            ->bindValues([
                ':idempotencyKey' => $key,
                ':requestHash' => $requestHash,
            ])
            ->queryScalar();
    }

    /**
     * @param string $key
     * @param int $taskId
     * @param string $responseBody
     * @return int
     * @throws InvalidConfigException|Exception|RuntimeException
     */
    public function complete(string $key, int $taskId, string $responseBody): int
    {
        return $this->getDbConnection()
            ->createCommand(__DIR__ . '/sqls/complete_idempotency_key.sql')
            ->bindValues([
                ':idempotencyKey' => $key,
                ':taskId' => $taskId,
                ':responseBody' => $responseBody,
            ])
            ->execute();
    }
}

<?php

declare(strict_types=1);

namespace app\extensions;

use RuntimeException;
use yii\db\Command;
use yii\db\Connection;

/**
 * Подключение к базе данных с поддержкой SQL-файлов.
 */
class DbConnection extends Connection
{
    /**
     * @inheritDoc
     * @param string|null $sql
     * @param array<string, mixed> $params
     */
    public function createCommand($sql = null, $params = []): Command
    {
        if (is_string($sql) && str_ends_with($sql, '.sql')) {
            $sql = $this->getSql($sql);
        }

        return parent::createCommand($sql, $params);
    }

    /**
     * Получить SQL-запрос из файла.
     *
     * @param string $file
     * @return string
     */
    public function getSql(string $file): string
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Не удалось прочитать SQL-файл {$file}.");
        }

        return trim($sql);
    }
}

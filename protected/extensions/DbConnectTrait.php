<?php

declare(strict_types=1);

namespace app\extensions;

use Yii;
use yii\base\InvalidConfigException;

/**
 * Подключение к базе данных.
 */
trait DbConnectTrait
{
    private ?DbConnection $dbConnection = null;

    /**
     * Получить подключение к базе данных.
     *
     * @return DbConnection
     * @throws InvalidConfigException
     */
    public function getDbConnection(): DbConnection
    {
        if (!$this->dbConnection instanceof DbConnection) {
            $db = Yii::$app->get('db', false);
            if (!$db instanceof DbConnection) {
                throw new InvalidConfigException('Компонент базы данных не настроен.');
            }

            $this->dbConnection = $db;
        }

        return $this->dbConnection;
    }

    /**
     * Установить подключение к базе данных.
     *
     * @param DbConnection $dbConnection
     * @return void
     */
    public function setDbConnection(DbConnection $dbConnection): void
    {
        $this->dbConnection = $dbConnection;
    }
}

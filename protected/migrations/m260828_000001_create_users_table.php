<?php

declare(strict_types=1);

use yii\db\Exception;
use yii\db\Migration;

/**
 * Создать таблицу пользователей.
 */
final class m260828_000001_create_users_table extends Migration
{
    /**
     * Создать таблицу пользователей.
     *
     * @return void
     * @throws Exception
     */
    public function safeUp(): void
    {
        $this->createTable('{{%users}}', [
            'id' => $this->bigPrimaryKey(),
            'full_name' => $this->string(100)->notNull(),
            'phone' => $this->string(15),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'deleted_at' => $this->timestamp(),
        ]);

        $this->createIndex('idx-users-deleted_at', '{{%users}}', 'deleted_at');
    }

    /**
     * Удалить таблицу пользователей.
     *
     * @return void
     * @throws Exception
     */
    public function safeDown(): void
    {
        $this->dropTable('{{%users}}');
    }
}

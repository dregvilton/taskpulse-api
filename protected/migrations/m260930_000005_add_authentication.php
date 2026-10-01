<?php

declare(strict_types=1);

use yii\db\Exception;
use yii\db\Migration;

/**
 * Добавить учётные данные и отзываемые токены.
 */
final class m260930_000005_add_authentication extends Migration
{
    /**
     * @return void
     * @throws Exception
     */
    public function safeUp(): void
    {
        $this->addColumn('{{%users}}', 'email', (string) $this->string(255));
        $this->addColumn('{{%users}}', 'password_hash', (string) $this->string(255));
        $this->execute(
            'CREATE UNIQUE INDEX "uq-users-active-email" ON {{%users}} (LOWER(email)) WHERE deleted_at IS NULL',
        );

        $this->createTable('{{%auth_tokens}}', [
            'token_hash' => $this->char(64)->notNull(),
            'user_id' => $this->bigInteger()->notNull(),
            'expires_at' => $this->timestamp()->notNull(),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        $this->addPrimaryKey('pk-auth_tokens', '{{%auth_tokens}}', 'token_hash');
        $this->createIndex('idx-auth_tokens-user_id', '{{%auth_tokens}}', 'user_id');
        $this->addForeignKey(
            'fk-auth_tokens-user_id',
            '{{%auth_tokens}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->addColumn('{{%idempotency_keys}}', 'user_id', (string) $this->bigInteger());
        $this->execute(
            'UPDATE {{%idempotency_keys}} AS keys SET user_id = tasks.author_id '
            . 'FROM {{%tasks}} AS tasks WHERE keys.task_id = tasks.id',
        );
        $this->alterColumn('{{%idempotency_keys}}', 'user_id', (string) $this->bigInteger()->notNull());
        $this->dropPrimaryKey('pk-idempotency_keys', '{{%idempotency_keys}}');
        $this->addPrimaryKey(
            'pk-idempotency_keys',
            '{{%idempotency_keys}}',
            ['user_id', 'idempotency_key'],
        );
        $this->addForeignKey(
            'fk-idempotency_keys-user_id',
            '{{%idempotency_keys}}',
            'user_id',
            '{{%users}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
    }

    /**
     * @return void
     * @throws Exception
     */
    public function safeDown(): void
    {
        $this->dropForeignKey('fk-idempotency_keys-user_id', '{{%idempotency_keys}}');
        $this->dropPrimaryKey('pk-idempotency_keys', '{{%idempotency_keys}}');
        $this->addPrimaryKey('pk-idempotency_keys', '{{%idempotency_keys}}', 'idempotency_key');
        $this->dropColumn('{{%idempotency_keys}}', 'user_id');

        $this->dropTable('{{%auth_tokens}}');
        $this->execute('DROP INDEX "uq-users-active-email"');
        $this->dropColumn('{{%users}}', 'password_hash');
        $this->dropColumn('{{%users}}', 'email');
    }
}

<?php

declare(strict_types=1);

use yii\db\Exception;
use yii\db\Migration;

/**
 * Создать таблицу ключей идемпотентности.
 */
final class m260926_000003_create_idempotency_keys_table extends Migration
{
    /**
     * @return void
     * @throws Exception
     */
    public function safeUp(): void
    {
        $this->createTable('{{%idempotency_keys}}', [
            'idempotency_key' => $this->string(255)->notNull(),
            'request_hash' => $this->char(64)->notNull(),
            'task_id' => $this->bigInteger(),
            'response_body' => $this->text(),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->addPrimaryKey('pk-idempotency_keys', '{{%idempotency_keys}}', 'idempotency_key');
        $this->addForeignKey(
            'fk-idempotency_keys-task_id',
            '{{%idempotency_keys}}',
            'task_id',
            '{{%tasks}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
        $this->execute(
            <<<'SQL'
                ALTER TABLE {{%idempotency_keys}}
                ADD CONSTRAINT "chk-idempotency_keys-response"
                CHECK ((task_id IS NULL) = (response_body IS NULL))
                SQL,
        );
    }

    /**
     * @return void
     * @throws Exception
     */
    public function safeDown(): void
    {
        $this->dropTable('{{%idempotency_keys}}');
    }
}

<?php

declare(strict_types=1);

use yii\db\Exception;
use yii\db\Migration;

/**
 * Создать outbox и журнал обработки событий задач.
 */
final class m260929_000004_create_task_events_table extends Migration
{
    /**
     * @return void
     * @throws Exception
     */
    public function safeUp(): void
    {
        $this->createTable('{{%task_events}}', [
            'id' => $this->bigPrimaryKey(),
            'task_id' => $this->bigInteger()->notNull(),
            'event_type' => $this->string(32)->notNull(),
            'payload' => 'JSONB NOT NULL',
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'published_at' => $this->timestamp(),
        ]);
        $this->createIndex('idx-task-events-pending', '{{%task_events}}', ['published_at', 'id']);
        $this->createIndex('idx-task-events-task-id', '{{%task_events}}', 'task_id');

        $this->createTable('{{%processed_task_events}}', [
            'event_id' => $this->bigInteger()->notNull(),
            'processed_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        $this->addPrimaryKey('pk-processed-task-events', '{{%processed_task_events}}', 'event_id');
        $this->addForeignKey(
            'fk-processed-task-events-event-id',
            '{{%processed_task_events}}',
            'event_id',
            '{{%task_events}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    /**
     * @return void
     * @throws Exception
     */
    public function safeDown(): void
    {
        $this->dropTable('{{%processed_task_events}}');
        $this->dropTable('{{%task_events}}');
    }
}

<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260903_000002_create_tasks_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%tasks}}', [
            'id' => $this->bigPrimaryKey(),
            'author_id' => $this->bigInteger()->notNull(),
            'title' => $this->string(255)->notNull(),
            'description' => $this->text(),
            'completed' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'completed_at' => $this->timestamp(),
            'deleted_at' => $this->timestamp(),
        ]);

        $this->addForeignKey(
            'fk-tasks-author_id',
            '{{%tasks}}',
            'author_id',
            '{{%users}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
        $this->execute(
            <<<'SQL'
                ALTER TABLE {{%tasks}}
                ADD CONSTRAINT "chk-tasks-completion-state"
                CHECK ((completed = TRUE AND completed_at IS NOT NULL) OR (completed = FALSE AND completed_at IS NULL))
                SQL,
        );
        $this->execute(
            <<<'SQL'
                ALTER TABLE {{%tasks}}
                ADD CONSTRAINT "chk-tasks-completed_at"
                CHECK (completed_at IS NULL OR completed_at >= created_at)
                SQL,
        );

        $this->createIndex('idx-tasks-author_id', '{{%tasks}}', 'author_id');
        $this->createIndex('idx-tasks-completed', '{{%tasks}}', 'completed');
        $this->createIndex('idx-tasks-created_at', '{{%tasks}}', 'created_at');
        $this->createIndex('idx-tasks-completed_at', '{{%tasks}}', 'completed_at');
        $this->createIndex('idx-tasks-author_id-completed', '{{%tasks}}', ['author_id', 'completed']);
        $this->createIndex('idx-tasks-author_id-created_at', '{{%tasks}}', ['author_id', 'created_at']);
        $this->createIndex('idx-tasks-deleted_at', '{{%tasks}}', 'deleted_at');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%tasks}}');
    }
}

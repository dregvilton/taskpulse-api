<?php

declare(strict_types=1);

namespace tests\unit;

use app\models\Task;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\db\Connection;
use yii\db\IntegrityException;

final class TaskModelTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        /** @var Connection $db */
        $db = Yii::$app->get('db');
        $this->db = $db;
        $this->db->createCommand('TRUNCATE TABLE tasks, users RESTART IDENTITY CASCADE')->execute();
        $this->db->createCommand()->insert('users', ['full_name' => 'Иван Петров'])->execute();
        $this->db->createCommand()->insert('tasks', [
            'author_id' => 1,
            'title' => 'Подготовить отчёт',
        ])->execute();
    }

    public function testAuthorRelation(): void
    {
        $task = Task::findOne(1);

        self::assertInstanceOf(Task::class, $task);
        self::assertSame('Иван Петров', $task->author->full_name);
    }

    public function testCompletedTaskRequiresCompletionDate(): void
    {
        $this->expectException(IntegrityException::class);

        $this->db->createCommand()->insert('tasks', [
            'author_id' => 1,
            'title' => 'Завершённая задача',
            'completed' => true,
        ])->execute();
    }
}

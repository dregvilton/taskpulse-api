<?php

declare(strict_types=1);

namespace tests\unit;

use app\modules\task\forms\TaskForm;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\db\Connection;

final class TaskFormTest extends TestCase
{
    private Connection $db;
    private int $authorId;

    protected function setUp(): void
    {
        /** @var Connection $db */
        $db = Yii::$app->get('db');
        $this->db = $db;
        $this->db->createCommand('TRUNCATE TABLE tasks, users RESTART IDENTITY CASCADE')->execute();
        $this->db->createCommand()->insert('users', ['full_name' => 'Иван Петров'])->execute();
        $this->authorId = (int) $this->db->getLastInsertID();
    }

    public function testCreateScenario(): void
    {
        $form = new TaskForm(['scenario' => TaskForm::SCENARIO_CREATE]);
        $form->load([
            'authorId' => $this->authorId,
            'title' => 'Подготовить отчёт',
            'description' => 'Собрать данные за неделю',
            'completed' => false,
        ], '');

        self::assertTrue($form->validate());
        self::assertSame('Подготовить отчёт', $form->title);
    }

    public function testRequiredFields(): void
    {
        $form = new TaskForm(['scenario' => TaskForm::SCENARIO_CREATE]);
        $form->load([], '');

        self::assertFalse($form->validate());
        self::assertSame('Необходимо указать автора.', $form->getFirstError('authorId'));
        self::assertSame('Необходимо указать заголовок.', $form->getFirstError('title'));
    }

    public function testAuthorMustExist(): void
    {
        $form = new TaskForm(['scenario' => TaskForm::SCENARIO_CREATE]);
        $form->load([
            'authorId' => 999,
            'title' => 'Подготовить отчёт',
        ], '');

        self::assertFalse($form->validate());
        self::assertSame('Автор не найден.', $form->getFirstError('authorId'));
    }

    public function testCompletedMustBeBoolean(): void
    {
        $form = new TaskForm(['scenario' => TaskForm::SCENARIO_CREATE]);
        $form->load([
            'authorId' => $this->authorId,
            'title' => 'Подготовить отчёт',
            'completed' => 1,
        ], '');

        self::assertFalse($form->validate());
        self::assertSame(
            'Признак завершения должен быть логическим значением.',
            $form->getFirstError('completed'),
        );
    }

    public function testUpdateScenarioTracksFields(): void
    {
        $form = new TaskForm(['scenario' => TaskForm::SCENARIO_UPDATE]);
        $form->load(['description' => null], '');

        self::assertTrue($form->validate());
        self::assertTrue($form->hasField('description'));
        self::assertFalse($form->hasField('title'));
    }

    public function testUpdateRequiresChanges(): void
    {
        $form = new TaskForm(['scenario' => TaskForm::SCENARIO_UPDATE]);
        $form->load([], '');

        self::assertFalse($form->validate());
        self::assertSame('Не переданы данные для обновления.', $form->getFirstError('title'));
    }
}

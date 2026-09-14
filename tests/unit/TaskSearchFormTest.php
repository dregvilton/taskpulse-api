<?php

declare(strict_types=1);

namespace tests\unit;

use app\modules\task\forms\TaskSearchForm;
use PHPUnit\Framework\TestCase;

final class TaskSearchFormTest extends TestCase
{
    public function testValidFilters(): void
    {
        $form = new TaskSearchForm();
        $form->load([
            'authorId' => 1,
            'completed' => 'true',
            'createdFrom' => '2026-09-01T00:00:00+05:00',
            'completedTo' => '2026-09-30T23:59:59+05:00',
            'page' => 2,
            'perPage' => 50,
            'sort' => '-completedAt,title',
        ], '');

        self::assertTrue($form->validate());
        self::assertTrue($form->completed);
    }

    public function testInvalidFilters(): void
    {
        $form = new TaskSearchForm();
        $form->load([
            'completed' => 'yes',
            'createdFrom' => '01.09.2026',
            'perPage' => 101,
            'sort' => 'authorId',
        ], '');

        self::assertFalse($form->validate());
        self::assertSame(
            'Признак завершения должен быть логическим значением.',
            $form->getFirstError('completed'),
        );
        self::assertSame(
            'Дата должна быть указана в формате ISO 8601.',
            $form->getFirstError('createdFrom'),
        );
        self::assertSame('Размер страницы должен быть не больше 100.', $form->getFirstError('perPage'));
        self::assertSame('Недопустимое значение сортировки.', $form->getFirstError('sort'));
    }
}

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
        self::assertSame('2026-08-31 19:00:00', $form->createdFrom);
        self::assertSame('2026-09-30 18:59:59', $form->completedTo);
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

    public function testEmptyFiltersAreIgnored(): void
    {
        $form = new TaskSearchForm();
        $form->load([
            'authorId' => '',
            'completed' => '',
            'createdFrom' => '',
            'createdTo' => '',
            'completedFrom' => '',
            'completedTo' => '',
        ], '');

        self::assertTrue($form->validate());
        self::assertNull($form->authorId);
        self::assertNull($form->completed);
        self::assertNull($form->createdFrom);
    }

    public function testReversedDateRangesAreRejected(): void
    {
        $form = new TaskSearchForm();
        $form->load([
            'createdFrom' => '2026-09-02T00:00:00+00:00',
            'createdTo' => '2026-09-01T00:00:00+00:00',
            'completedFrom' => '2026-09-02T00:00:00+00:00',
            'completedTo' => '2026-09-01T00:00:00+00:00',
        ], '');

        self::assertFalse($form->validate());
        self::assertSame('Начало периода не может быть позже конца.', $form->getFirstError('createdFrom'));
        self::assertSame('Начало периода не может быть позже конца.', $form->getFirstError('completedFrom'));
    }
}

<?php

declare(strict_types=1);

namespace tests\unit;

use app\modules\user\forms\UserForm;
use PHPUnit\Framework\TestCase;

final class UserFormTest extends TestCase
{
    public function testCreateScenario(): void
    {
        $form = new UserForm(['scenario' => UserForm::SCENARIO_CREATE]);
        $form->load([
            'fullName' => 'Иван Петров',
            'phone' => '+79991234567',
            'email' => 'ivan@example.test',
            'password' => bin2hex(random_bytes(16)),
        ], '');

        self::assertTrue($form->validate());
        self::assertSame('Иван Петров', $form->fullName);
    }

    public function testNameIsRequiredOnCreate(): void
    {
        $form = new UserForm(['scenario' => UserForm::SCENARIO_CREATE]);
        $form->load(['phone' => '+79991234567'], '');

        self::assertFalse($form->validate());
        self::assertSame('Необходимо указать имя.', $form->getFirstError('fullName'));
    }

    public function testPhoneFormat(): void
    {
        $form = new UserForm(['scenario' => UserForm::SCENARIO_CREATE]);
        $form->load([
            'fullName' => 'Иван Петров',
            'phone' => '89991234567',
        ], '');

        self::assertFalse($form->validate());
        self::assertSame(
            'Телефон должен начинаться с + и содержать только цифры.',
            $form->getFirstError('phone'),
        );
    }

    public function testNameIsTrimmedBeforeValidation(): void
    {
        $form = new UserForm(['scenario' => UserForm::SCENARIO_CREATE]);
        $form->load(['fullName' => ' И '], '');

        self::assertFalse($form->validate());
        self::assertSame('И', $form->fullName);
        self::assertSame(
            'Имя должно содержать не менее 3 символов.',
            $form->getFirstError('fullName'),
        );
    }

    public function testRejectsNonStringValues(): void
    {
        $form = new UserForm(['scenario' => UserForm::SCENARIO_CREATE]);
        $form->load([
            'fullName' => 123,
            'phone' => 79991234567,
        ], '');

        self::assertFalse($form->validate());
        self::assertSame('Имя должно быть строкой.', $form->getFirstError('fullName'));
        self::assertSame('Телефон должен быть строкой.', $form->getFirstError('phone'));
    }

    public function testUpdateScenarioTracksFields(): void
    {
        $form = new UserForm(['scenario' => UserForm::SCENARIO_UPDATE]);
        $form->load(['phone' => null], '');

        self::assertTrue($form->validate());
        self::assertTrue($form->hasField('phone'));
        self::assertFalse($form->hasField('fullName'));
    }

    public function testProvidedNameIsRequiredOnUpdate(): void
    {
        $form = new UserForm(['scenario' => UserForm::SCENARIO_UPDATE]);
        $form->load(['fullName' => ''], '');

        self::assertFalse($form->validate());
        self::assertSame('Необходимо указать имя.', $form->getFirstError('fullName'));
    }

    public function testUpdateRequiresChanges(): void
    {
        $form = new UserForm(['scenario' => UserForm::SCENARIO_UPDATE]);
        $form->load([], '');

        self::assertFalse($form->validate());
        self::assertSame('Не переданы данные для обновления.', $form->getFirstError('fullName'));
    }
}

<?php

declare(strict_types=1);

namespace app\services;

use app\extensions\DbConnectTrait;
use app\models\User;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Expression;

/**
 * Восстановление публичных демонстрационных данных.
 */
final class DemoResetService
{
    use DbConnectTrait;

    /**
     * @param AnalyticsCache $analyticsCache
     */
    public function __construct(private readonly AnalyticsCache $analyticsCache) {}

    /**
     * @return void
     * @throws Throwable
     */
    public function reset(): void
    {
        $email = (string) Yii::$app->params['demoEmail'];
        $name = trim($_ENV['DEMO_FULL_NAME'] ?? '');
        $password = $_ENV['DEMO_PASSWORD'] ?? '';
        if (
            !Yii::$app->params['demoMode']
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || mb_strlen($name) < 3
            || mb_strlen($password) < 12
        ) {
            throw new InvalidConfigException('Демо-режим или данные демо-аккаунта не настроены.');
        }

        $db = $this->getDbConnection();
        $transaction = $db->beginTransaction();

        try {
            $user = User::find()->where(['email' => $email, 'deleted_at' => null])->one();
            if (!$user instanceof User) {
                $user = new User();
                $user->email = $email;
            }

            $user->full_name = $name;
            $user->phone = null;
            $user->password_hash = Yii::$app->security->generatePasswordHash($password);
            if (!$user->save(false)) {
                throw new \RuntimeException('Не удалось восстановить демо-аккаунт.');
            }

            $userId = (int) $user->id;
            $db->createCommand(__DIR__ . '/sqls/lock_demo_user.sql')
                ->bindValue(':userId', $userId)
                ->queryScalar();

            $db->createCommand()->delete('{{%idempotency_keys}}', ['user_id' => $userId])->execute();
            $db->createCommand()->delete('{{%auth_tokens}}', [
                'and',
                ['user_id' => $userId],
                ['<=', 'expires_at', gmdate('Y-m-d H:i:s')],
            ])->execute();
            $db->createCommand()->delete('{{%tasks}}', ['author_id' => $userId])->execute();

            $samples = [
                ['Изучить TaskPulse', 'Откройте задачи и попробуйте фильтры.', false],
                ['Создать свою задачу', 'Данные автоматически сбрасываются каждые 30 минут.', false],
                ['Посмотреть аналитику', 'Выполненная задача уже есть в примере.', true],
            ];
            foreach ($samples as [$title, $description, $completed]) {
                $db->createCommand()->insert('{{%tasks}}', [
                    'author_id' => $userId,
                    'title' => $title,
                    'description' => $description,
                    'completed' => $completed,
                    'completed_at' => $completed ? new Expression('CURRENT_TIMESTAMP') : null,
                ])->execute();
            }

            $transaction->commit();
            $this->analyticsCache->invalidate();
            Yii::info(['event' => 'demo_reset', 'userId' => $userId, 'taskCount' => count($samples)], __METHOD__);
        } catch (Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            throw $exception;
        }
    }
}

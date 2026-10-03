<?php

declare(strict_types=1);

namespace tests\demo;

use app\services\AnalyticsCache;
use app\services\DemoResetService;
use JsonException;
use tests\api\ApiTestCase;
use Yii;
use yii\db\Query;
use yii\redis\Connection as RedisConnection;

final class DemoModeApiTest extends ApiTestCase
{
    /** @var array<string, string|false> */
    private array $previousEnvironment = [];
    private bool $previousDemoMode;
    private string $previousDemoEmail;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['APP_DEMO_MODE', 'DEMO_EMAIL', 'DEMO_FULL_NAME', 'DEMO_PASSWORD'] as $name) {
            $this->previousEnvironment[$name] = $_ENV[$name] ?? false;
        }
        $this->previousDemoMode = (bool) (Yii::$app->params['demoMode'] ?? false);
        $this->previousDemoEmail = (string) (Yii::$app->params['demoEmail'] ?? '');

        $_ENV['APP_DEMO_MODE'] = 'true';
        $_ENV['DEMO_EMAIL'] = 'demo@example.test';
        $_ENV['DEMO_FULL_NAME'] = 'Демо Пользователь';
        $_ENV['DEMO_PASSWORD'] = 'public-demo-password';
        Yii::$app->params['demoMode'] = true;
        Yii::$app->params['demoEmail'] = 'demo@example.test';
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->previousEnvironment as $name => $value) {
            if ($value === false) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $value;
            }
        }
        Yii::$app->params['demoMode'] = $this->previousDemoMode;
        Yii::$app->params['demoEmail'] = $this->previousDemoEmail;

        parent::tearDown();
    }

    /**
     * @throws JsonException
     */
    public function testDemoAccountAllowsTasksButProtectsProfileAndRegistration(): void
    {
        $this->resetDemo();
        $login = $this->request('POST', '/auth/login', [
            'email' => 'demo@example.test',
            'password' => 'public-demo-password',
        ]);
        self::assertSame(200, $login['status']);
        $this->accessToken = (string) $login['body']['accessToken'];

        self::assertSame(403, $this->request('POST', '/users', [
            'fullName' => 'Новый Пользователь',
            'email' => 'new@example.test',
            'password' => 'another-password',
        ])['status']);
        self::assertSame(403, $this->request('PATCH', '/users/1', ['fullName' => 'Другое имя'])['status']);
        self::assertSame(403, $this->request('DELETE', '/users/1')['status']);

        $created = $this->request('POST', '/tasks', ['title' => 'Моя демо-задача']);
        self::assertSame(201, $created['status']);
        $taskId = (int) $created['body']['id'];
        self::assertSame(200, $this->request('PATCH', "/tasks/{$taskId}", ['completed' => true])['status']);
        self::assertSame(204, $this->request('DELETE', "/tasks/{$taskId}")['status']);
        self::assertSame(200, $this->request('GET', '/analytics/tasks')['status']);
        self::assertSame(204, $this->request('POST', '/auth/logout')['status']);
    }

    /**
     * @throws JsonException
     */
    public function testOtherAccountCannotLogInOrUseExistingToken(): void
    {
        $password = 'another-public-password';
        $this->db->createCommand()->insert('{{%users}}', [
            'full_name' => 'Другой Пользователь',
            'email' => 'other@example.test',
            'password_hash' => Yii::$app->security->generatePasswordHash($password),
        ])->execute();
        $userId = (int) $this->db->getLastInsertID();

        self::assertSame(401, $this->request('POST', '/auth/login', [
            'email' => 'other@example.test',
            'password' => $password,
        ])['status']);

        $token = bin2hex(random_bytes(32));
        $this->db->createCommand()->insert('{{%auth_tokens}}', [
            'token_hash' => hash('sha256', $token),
            'user_id' => $userId,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
        ])->execute();
        self::assertSame(403, $this->request('GET', '/users', extraHeaders: [
            'Authorization' => 'Bearer ' . $token,
        ])['status']);
    }

    /**
     * @throws JsonException
     */
    public function testResetIsRepeatableAndTouchesOnlyDemoData(): void
    {
        $this->db->createCommand()->insert('{{%users}}', [
            'full_name' => 'Другой Пользователь',
            'email' => 'other@example.test',
            'password_hash' => Yii::$app->security->generatePasswordHash('another-password'),
        ])->execute();
        $otherUserId = (int) $this->db->getLastInsertID();
        $this->db->createCommand()->insert('{{%tasks}}', [
            'author_id' => $otherUserId,
            'title' => 'Чужая задача',
        ])->execute();

        $this->resetDemo();
        $demoId = (int) $this->db->createCommand(
            "SELECT id FROM users WHERE email = 'demo@example.test'",
        )->queryScalar();
        $this->db->createCommand()->insert('{{%tasks}}', [
            'author_id' => $demoId,
            'title' => 'Временная задача',
        ])->execute();
        $this->db->createCommand()->insert('{{%idempotency_keys}}', [
            'user_id' => $demoId,
            'idempotency_key' => 'reset-test',
            'request_hash' => str_repeat('a', 64),
        ])->execute();

        $this->resetDemo();
        self::assertSame(
            $demoId,
            (int) $this->db->createCommand("SELECT id FROM users WHERE email = 'demo@example.test'")->queryScalar(),
        );
        self::assertSame(3, (int) (new Query())->from('{{%tasks}}')->where(['author_id' => $demoId])->count('*', $this->db));
        self::assertSame(0, (int) (new Query())->from('{{%idempotency_keys}}')->where(['user_id' => $demoId])->count('*', $this->db));
        self::assertSame(1, (int) (new Query())->from('{{%tasks}}')->where(['author_id' => $otherUserId])->count('*', $this->db));
    }

    /**
     * @return void
     */
    private function resetDemo(): void
    {
        /** @var RedisConnection $redis */
        $redis = Yii::$app->get('redis');
        $analyticsCache = new AnalyticsCache($redis);
        (new DemoResetService($analyticsCache))->reset();
    }
}

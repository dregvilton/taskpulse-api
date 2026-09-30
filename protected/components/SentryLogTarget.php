<?php

declare(strict_types=1);

namespace app\components;

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Transport\TransportInterface;
use Throwable;
use Yii;
use yii\log\Logger;
use yii\log\Target;
use yii\web\Application as WebApplication;
use yii\web\Request;

/**
 * Отправка серьёзных ошибок в Sentry.
 */
final class SentryLogTarget extends Target
{
    public string $dsn = '';
    public string $environment = 'prod';
    public ?RequestContext $requestContext = null;
    public ?Request $request = null;
    public ?TransportInterface $transport = null;

    private ?Hub $hub = null;

    /**
     * @return void
     */
    public function init(): void
    {
        parent::init();
        if (!$this->getEnabled() || $this->dsn === '') {
            return;
        }

        $builder = ClientBuilder::create([
            'dsn' => $this->dsn,
            'environment' => $this->environment,
            'default_integrations' => false,
            'send_default_pii' => false,
            'max_request_body_size' => 'never',
            'max_breadcrumbs' => 0,
            'context_lines' => 0,
            'http_connect_timeout' => 1,
            'http_timeout' => 2,
            'before_send' => $this->sanitizeEvent(...),
        ]);
        if ($this->transport !== null) {
            $builder->setTransport($this->transport);
        }

        $this->hub = new Hub($builder->getClient());
    }

    /**
     * @return void
     */
    public function export(): void
    {
        if ($this->hub === null) {
            return;
        }

        foreach ($this->messages as [$message, $level, $category]) {
            if ($level !== Logger::LEVEL_ERROR) {
                continue;
            }

            $this->hub->withScope(function (Scope $scope) use ($message, $category): void {
                $scope->setTag('component', $_ENV['APP_COMPONENT'] ?? (defined('YII_CONSOLE') ? 'console' : 'api'));
                $scope->setTag('category', $category);
                $requestId = $this->requestContext?->getRequestId();
                if ($requestId !== null) {
                    $scope->setTag('request_id', $requestId);
                }
                if (is_array($message)) {
                    foreach (DiagnosticData::logContext($message) as $name => $value) {
                        $scope->setTag($name, (string) $value);
                    }
                }

                if ($message instanceof Throwable) {
                    $this->hub?->captureException($message);
                } else {
                    $this->hub?->captureMessage('Ошибка приложения.', Severity::error());
                }
            });
        }
    }

    /**
     * @param Event $event
     * @param EventHint|null $hint
     * @return Event|null
     */
    private function sanitizeEvent(Event $event, ?EventHint $hint = null): ?Event
    {
        try {
            $request = $this->request;
            $route = null;
            if (Yii::$app instanceof WebApplication) {
                $request ??= Yii::$app->request;
                $route = Yii::$app->requestedRoute;
            }
            $event->setRequest($request === null ? [] : DiagnosticData::request($request, $route));
            $event->setExtra([]);

            if ($event->getMessage() !== null && $event->getMessage() !== 'Ошибка приложения.') {
                $event->setMessage('Ошибка приложения.');
            }
            foreach ($event->getExceptions() as $exception) {
                $exception->setValue('Сообщение скрыто для защиты данных.');
                foreach ($exception->getStacktrace()?->getFrames() ?? [] as $frame) {
                    $frame->setVars([]);
                    $frame->setPreContext([]);
                    $frame->setContextLine(null);
                    $frame->setPostContext([]);
                }
            }

            return $event;
        } catch (Throwable) {
            return null;
        }
    }
}

<?php

declare(strict_types=1);

namespace app\commands;

use app\services\OutboxPublisher;
use app\services\TaskEventBroker;
use app\services\TaskEventConsumer;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Публикация и обработка событий задач.
 */
final class TaskEventController extends Controller
{
    private const int PUBLISH_IDLE_INTERVAL_SECONDS = 1;
    private const int RECONNECT_INTERVAL_SECONDS = 1;
    private const int FAILURE_LOG_INTERVAL_SECONDS = 300;

    private bool $running = true;
    private ?TaskEventBroker $broker = null;
    private int $lastFailureLogAt = 0;
    private ?string $lastFailureSignature = null;

    /**
     * @param int $limit
     * @return int
     */
    public function actionPublish(int $limit = OutboxPublisher::DEFAULT_BATCH_SIZE): int
    {
        /** @var OutboxPublisher $publisher */
        $publisher = Yii::$app->get('outboxPublisher');
        $count = $publisher->publish($limit);
        Yii::info(['event' => 'outbox_published', 'count' => $count], __METHOD__);
        $this->stdout("Опубликовано событий: {$count}\n");

        return ExitCode::OK;
    }

    /**
     * @return int
     */
    public function actionRunPublisher(): int
    {
        $this->listenForStop();
        /** @var OutboxPublisher $publisher */
        $publisher = Yii::$app->get('outboxPublisher');
        Yii::info(['event' => 'publisher_started'], __METHOD__);

        while ($this->running) {
            try {
                $count = $publisher->publish();
                $this->lastFailureLogAt = 0;
                $this->lastFailureSignature = null;
                if ($count === 0) {
                    sleep(self::PUBLISH_IDLE_INTERVAL_SECONDS);
                } else {
                    Yii::info(['event' => 'outbox_published', 'count' => $count], __METHOD__);
                }
            } catch (Throwable $exception) {
                $this->logFailure($exception);
                $this->resetBroker();
                sleep(self::RECONNECT_INTERVAL_SECONDS);
            }
        }

        Yii::info(['event' => 'publisher_stopped'], __METHOD__);

        return ExitCode::OK;
    }

    /**
     * @return int
     */
    public function actionConsume(): int
    {
        /** @var TaskEventBroker $broker */
        $broker = Yii::$app->get('taskEventBroker');
        $this->broker = $broker;
        $this->listenForStop();
        /** @var TaskEventConsumer $consumer */
        $consumer = Yii::$app->get('taskEventConsumer');
        Yii::info(['event' => 'worker_started'], __METHOD__);

        while ($this->running) {
            try {
                $consumer->run();
                $this->lastFailureLogAt = 0;
                $this->lastFailureSignature = null;
            } catch (Throwable $exception) {
                $this->logFailure($exception);
                $this->resetBroker();
                if ($this->running) {
                    sleep(self::RECONNECT_INTERVAL_SECONDS);
                }
            }
        }

        Yii::info(['event' => 'worker_stopped'], __METHOD__);

        return ExitCode::OK;
    }

    /**
     * @return void
     */
    private function listenForStop(): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function (): void {
            $this->running = false;
            $this->broker?->stop();
        });
        pcntl_signal(SIGINT, function (): void {
            $this->running = false;
            $this->broker?->stop();
        });
    }

    /**
     * @param Throwable $exception
     * @return void
     */
    private function logFailure(Throwable $exception): void
    {
        $now = time();
        $signature = $exception::class . ':' . $exception->getFile() . ':' . $exception->getLine();
        if (
            $signature === $this->lastFailureSignature
            && $now - $this->lastFailureLogAt < self::FAILURE_LOG_INTERVAL_SECONDS
        ) {
            return;
        }

        $this->lastFailureLogAt = $now;
        $this->lastFailureSignature = $signature;
        Yii::error($exception, __METHOD__);
    }

    /**
     * @return void
     */
    private function resetBroker(): void
    {
        /** @var TaskEventBroker $broker */
        $broker = Yii::$app->get('taskEventBroker');
        $broker->reset();
    }
}

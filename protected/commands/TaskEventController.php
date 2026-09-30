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

    private bool $running = true;
    private ?TaskEventBroker $broker = null;

    /**
     * @param int $limit
     * @return int
     */
    public function actionPublish(int $limit = OutboxPublisher::DEFAULT_BATCH_SIZE): int
    {
        /** @var OutboxPublisher $publisher */
        $publisher = Yii::$app->get('outboxPublisher');
        $count = $publisher->publish($limit);
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

        while ($this->running) {
            try {
                if ($publisher->publish() === 0) {
                    sleep(self::PUBLISH_IDLE_INTERVAL_SECONDS);
                }
            } catch (Throwable $exception) {
                Yii::error($exception, __METHOD__);
                $this->resetBroker();
                sleep(self::RECONNECT_INTERVAL_SECONDS);
            }
        }

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

        while ($this->running) {
            try {
                $consumer->run();
            } catch (Throwable $exception) {
                Yii::error($exception, __METHOD__);
                $this->resetBroker();
                if ($this->running) {
                    sleep(self::RECONNECT_INTERVAL_SECONDS);
                }
            }
        }

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
     * @return void
     */
    private function resetBroker(): void
    {
        /** @var TaskEventBroker $broker */
        $broker = Yii::$app->get('taskEventBroker');
        $broker->reset();
    }
}

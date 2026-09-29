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
    private bool $running = true;

    /**
     * @param int $limit
     * @return int
     */
    public function actionPublish(int $limit = 100): int
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
                $publisher->publish();
            } catch (Throwable $exception) {
                Yii::error($exception, __METHOD__);
                $this->resetBroker();
            }
            sleep(1);
        }

        return ExitCode::OK;
    }

    /**
     * @return int
     */
    public function actionConsume(): int
    {
        $this->listenForStop();
        /** @var TaskEventConsumer $consumer */
        $consumer = Yii::$app->get('taskEventConsumer');

        while ($this->running) {
            try {
                if (!$consumer->consumeOnce()) {
                    usleep(200000);
                }
            } catch (Throwable $exception) {
                Yii::error($exception, __METHOD__);
                $this->resetBroker();
                sleep(1);
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
        });
        pcntl_signal(SIGINT, function (): void {
            $this->running = false;
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

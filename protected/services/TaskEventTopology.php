<?php

declare(strict_types=1);

namespace app\services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Конфигурация маршрутов событий задач.
 */
final class TaskEventTopology
{
    public const string EXCHANGE = 'task.events';
    public const string RETRY_EXCHANGE = 'task.events.retry';
    public const string DEAD_EXCHANGE = 'task.events.dead';
    public const string QUEUE = 'task.events.main';
    public const string RETRY_QUEUE = 'task.events.retry';
    public const string DEAD_QUEUE = 'task.events.dlq';
    public const string ROUTING_KEY = 'task.changed';
    public const string RETRY_ROUTING_KEY = 'task.retry';
    public const string DEAD_ROUTING_KEY = 'task.failed';
    public const int RETRY_DELAY_MS = 5000;

    /**
     * @param AMQPChannel $channel
     * @return void
     */
    public function declare(AMQPChannel $channel): void
    {
        $channel->exchange_declare(self::EXCHANGE, AMQPExchangeType::DIRECT, false, true, false);
        $channel->exchange_declare(self::RETRY_EXCHANGE, AMQPExchangeType::DIRECT, false, true, false);
        $channel->exchange_declare(self::DEAD_EXCHANGE, AMQPExchangeType::DIRECT, false, true, false);

        $channel->queue_declare(self::QUEUE, false, true, false, false);
        $channel->queue_bind(self::QUEUE, self::EXCHANGE, self::ROUTING_KEY);
        $channel->queue_declare(self::RETRY_QUEUE, false, true, false, false, false, new AMQPTable([
            'x-message-ttl' => self::RETRY_DELAY_MS,
            'x-dead-letter-exchange' => self::EXCHANGE,
            'x-dead-letter-routing-key' => self::ROUTING_KEY,
        ]));
        $channel->queue_bind(self::RETRY_QUEUE, self::RETRY_EXCHANGE, self::RETRY_ROUTING_KEY);
        $channel->queue_declare(self::DEAD_QUEUE, false, true, false, false);
        $channel->queue_bind(self::DEAD_QUEUE, self::DEAD_EXCHANGE, self::DEAD_ROUTING_KEY);
    }
}

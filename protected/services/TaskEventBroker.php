<?php

declare(strict_types=1);

namespace app\services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Очереди событий задач.
 */
final class TaskEventBroker
{
    private const string EXCHANGE = 'task.events';
    private const string RETRY_EXCHANGE = 'task.events.retry';
    private const string DEAD_EXCHANGE = 'task.events.dead';
    private const string QUEUE = 'task.events.main';
    private const string RETRY_QUEUE = 'task.events.retry';
    private const string DEAD_QUEUE = 'task.events.dlq';

    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    /**
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $password
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $password,
    ) {}

    /**
     * @return AMQPChannel
     */
    public function channel(): AMQPChannel
    {
        if ($this->channel instanceof AMQPChannel && $this->channel->is_open()) {
            return $this->channel;
        }

        $this->connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password);
        $this->channel = $this->connection->channel();
        $this->channel->exchange_declare(self::EXCHANGE, 'direct', false, true, false);
        $this->channel->exchange_declare(self::RETRY_EXCHANGE, 'direct', false, true, false);
        $this->channel->exchange_declare(self::DEAD_EXCHANGE, 'direct', false, true, false);

        $this->channel->queue_declare(self::QUEUE, false, true, false, false);
        $this->channel->queue_bind(self::QUEUE, self::EXCHANGE, 'task.changed');
        $this->channel->queue_declare(self::RETRY_QUEUE, false, true, false, false, false, new AMQPTable([
            'x-message-ttl' => 5000,
            'x-dead-letter-exchange' => self::EXCHANGE,
            'x-dead-letter-routing-key' => 'task.changed',
        ]));
        $this->channel->queue_bind(self::RETRY_QUEUE, self::RETRY_EXCHANGE, 'task.retry');
        $this->channel->queue_declare(self::DEAD_QUEUE, false, true, false, false);
        $this->channel->queue_bind(self::DEAD_QUEUE, self::DEAD_EXCHANGE, 'task.failed');
        $this->channel->confirm_select();

        return $this->channel;
    }

    /**
     * @param string $body
     * @param int $eventId
     * @param int $attempt
     * @param string $destination
     * @return void
     */
    public function publish(string $body, int $eventId, int $attempt = 0, string $destination = 'main'): void
    {
        [$exchange, $routingKey] = match ($destination) {
            'retry' => [self::RETRY_EXCHANGE, 'task.retry'],
            'dead' => [self::DEAD_EXCHANGE, 'task.failed'],
            default => [self::EXCHANGE, 'task.changed'],
        };

        $message = new AMQPMessage($body, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => (string) $eventId,
            'application_headers' => new AMQPTable(['retry-count' => $attempt]),
        ]);
        $channel = $this->channel();
        $channel->basic_publish($message, $exchange, $routingKey, true);
        $channel->wait_for_pending_acks_returns(5.0);
    }

    /**
     * @return AMQPMessage|null
     */
    public function getMessage(): ?AMQPMessage
    {
        $message = $this->channel()->basic_get(self::QUEUE, false);

        return $message instanceof AMQPMessage ? $message : null;
    }

    /**
     * @param AMQPMessage $message
     * @return void
     */
    public function acknowledge(AMQPMessage $message): void
    {
        $this->channel()->basic_ack($message->getDeliveryTag());
    }

    /**
     * @return void
     */
    public function reset(): void
    {
        $this->channel = null;
        $this->connection = null;
    }
}

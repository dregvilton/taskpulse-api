<?php

declare(strict_types=1);

namespace app\services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Передача событий задач через RabbitMQ.
 */
final class TaskEventBroker
{
    private const float CONFIRM_TIMEOUT_SECONDS = 5.0;
    private const float CONSUME_MAXIMUM_POLL_SECONDS = 1.0;
    private const int PREFETCH_COUNT = 1;

    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    /**
     * @param TaskEventTopology $topology
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $password
     */
    public function __construct(
        private readonly TaskEventTopology $topology,
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $password,
    ) {}

    /**
     * @return AMQPChannel
     */
    private function getChannel(): AMQPChannel
    {
        if ($this->channel instanceof AMQPChannel && $this->channel->is_open()) {
            return $this->channel;
        }

        $this->connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password);
        $this->channel = $this->connection->channel();
        $this->topology->declare($this->channel);
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
            'retry' => [TaskEventTopology::RETRY_EXCHANGE, TaskEventTopology::RETRY_ROUTING_KEY],
            'dead' => [TaskEventTopology::DEAD_EXCHANGE, TaskEventTopology::DEAD_ROUTING_KEY],
            default => [TaskEventTopology::EXCHANGE, TaskEventTopology::ROUTING_KEY],
        };

        $message = new AMQPMessage($body, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => (string) $eventId,
            'application_headers' => new AMQPTable(['retry-count' => $attempt]),
        ]);
        $channel = $this->getChannel();
        $channel->basic_publish($message, $exchange, $routingKey, true);
        $channel->wait_for_pending_acks_returns(self::CONFIRM_TIMEOUT_SECONDS);
    }

    /**
     * @param callable(AMQPMessage): void $handler
     * @return void
     */
    public function consume(callable $handler): void
    {
        $channel = $this->getChannel();
        $channel->basic_qos(0, self::PREFETCH_COUNT, false);
        $consumerTag = $channel->basic_consume(
            TaskEventTopology::QUEUE,
            '',
            false,
            false,
            false,
            false,
            $handler,
        );

        try {
            $channel->consume(self::CONSUME_MAXIMUM_POLL_SECONDS);
        } finally {
            if ($channel->is_open()) {
                $channel->basic_cancel($consumerTag);
            }
        }
    }

    /**
     * @return void
     */
    public function stop(): void
    {
        $this->channel?->stopConsume();
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

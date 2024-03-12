<?php

namespace Lexide\QueueBall\Redis;

use Lexide\QueueBall\Exception\QueueException;
use Lexide\QueueBall\Message\QueueMessage;
use Lexide\QueueBall\Message\QueueMessageFactoryInterface;
use Lexide\QueueBall\Queue\AbstractQueue;

/**
 * Queue
 */
class Queue extends AbstractQueue
{

    protected LazyRedisWrapper $redis;

    protected QueueMessageFactoryInterface $messageFactory;

    protected array $receivedMessages = [];

    protected int $receivedMessageCounter = 0;

    /**
     * @param LazyRedisWrapper $redis
     * @param QueueMessageFactoryInterface $messageFactory
     * @param ?string $queueId
     */
    public function __construct(LazyRedisWrapper $redis, QueueMessageFactoryInterface $messageFactory, ?string $queueId = null)
    {
        $this->redis = $redis;
        $this->messageFactory = $messageFactory;

        parent::__construct($queueId);

        // make sure the destruct method is called in the event a non-graceful exit
        register_shutdown_function([$this, "__destruct"]);
    }

    /**
     * {@inheritDoc}
     */
    public function createQueue(string $queueId, array $options = []): void
    {
        // Nothing to do, Redis queues are created if they do not exist
    }

    /**
     * {@inheritDoc}
     * @throws QueueException
     */
    public function deleteQueue(?string $queueId = null): void
    {
        $queueId = $this->normaliseQueueId($queueId);

        try {
            $this->redis->del($queueId);
        } catch (\RedisException $e) {
            throw new QueueException("Redis error: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * {@inheritDoc}
     * @throws QueueException
     */
    public function sendMessage(string $messageBody, ?string $queueId = null): void
    {
        $queueId = $this->normaliseQueueId($queueId);
        try {
            $this->redis->rpush($queueId, $messageBody);
        } catch (\RedisException $e) {
            throw new QueueException("Redis error: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * {@inheritDoc}
     * @throws QueueException
     */
    public function receiveMessage(?string$queueId = null, float|int|null $waitTime = 0): ?QueueMessage
    {
        $queueId = $this->normaliseQueueId($queueId);
        if (empty($waitTime)) {
            $waitTime = $this->waitTime;
        }
        try {
            $message = $this->redis->blpop($queueId, $waitTime);
        } catch (\RedisException $e) {
            throw new QueueException("Redis error: {$e->getMessage()}", previous: $e);
        }

        if (empty($message[1])) {
            return null;
        }

        $queueMessage = $this->messageFactory->createMessage($message[1], $queueId);
        
        $index = $this->receivedMessageCounter++;
        $this->receivedMessages[$index] = $queueMessage;
        $queueMessage->setReceiptId($index);
        
        return $queueMessage;
    }

    /**
     * {@inheritDoc}
     */
    public function completeMessage(QueueMessage $message): void
    {
        // all we have to do is remove the reference to this message in receivedMessages
        unset($this->receivedMessages[$message->getReceiptId()]);
    }

    /**
     * {@inheritDoc}
     * @throws QueueException
     */
    public function returnMessage(QueueMessage $message): void
    {
        // re-add the message to the queue, as the first element
        try {
            $this->redis->lpush($message->getQueueId(), $message->getMessage());
        } catch (\RedisException $e) {
            throw new QueueException("Redis error: {$e->getMessage()}", previous: $e);
        }

        // forget we received the message
        $this->completeMessage($message);
    }

    /**
     * @param ?string $queueId
     * @return string
     * @throws QueueException
     */
    public function normaliseQueueId(?string $queueId = null): string
    {
        if (empty($queueId)) {
            return $this->getQueueId();
        }
        return $queueId;
    }

    public function __destruct()
    {
        foreach ($this->receivedMessages as $message) {
            /** @var QueueMessage $message */
            $this->returnMessage($message);
        }
    }

}

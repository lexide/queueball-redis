<?php

namespace Lexide\QueueBall\Redis;

use Lexide\QueueBall\Exception\QueueException;
use Predis\Client;
use Lexide\QueueBall\Message\QueueMessage;
use Lexide\QueueBall\Message\QueueMessageFactoryInterface;
use Lexide\QueueBall\Queue\AbstractQueue;
use Predis\PredisException;

/**
 * Queue
 */
class Queue extends AbstractQueue
{

    protected Client $predis;

    protected QueueMessageFactoryInterface $messageFactory;

    protected array $receivedMessages = [];

    protected int $receivedMessageCounter = 0;

    /**
     * @param Client $predis
     * @param QueueMessageFactoryInterface $messageFactory
     * @param ?string $queueId
     */
    public function __construct(Client $predis, QueueMessageFactoryInterface $messageFactory, ?string $queueId = null)
    {
        $this->predis = $predis;
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
            $this->predis->del($queueId);
        } catch (PredisException $e) {
            throw new QueueException("Predis error: {$e->getMessage()}", previous: $e);
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
            $this->predis->rpush($queueId, [$messageBody]);
        } catch (PredisException $e) {
            throw new QueueException("Predis error: {$e->getMessage()}", previous: $e);
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
            $message = $this->predis->blpop([$queueId], $waitTime);
        } catch (PredisException $e) {
            throw new QueueException("Predis error: {$e->getMessage()}", previous: $e);
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
            $this->predis->lpush($message->getQueueId(), [$message->getMessage()]);
        } catch (PredisException $e) {
            throw new QueueException("Predis error: {$e->getMessage()}", previous: $e);
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

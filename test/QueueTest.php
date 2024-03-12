<?php

namespace Lexide\QueueBall\Redis\Test;

use Lexide\QueueBall\Exception\QueueException;
use Lexide\QueueBall\Message\QueueMessage;
use Lexide\QueueBall\Message\QueueMessageFactoryInterface;
use Lexide\QueueBall\Redis\LazyRedisWrapper;
use Lexide\QueueBall\Redis\Queue;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected LazyRedisWrapper|MockInterface $client;

    protected QueueMessageFactoryInterface|MockInterface $messageFactory;

    protected QueueMessage|MockInterface $message;

    protected array $receiptIds;

    public function setUp(): void
    {
        $this->client = \Mockery::mock(LazyRedisWrapper::class);
        $this->message = \Mockery::mock(QueueMessage::class);
        $this->messageFactory = \Mockery::mock(QueueMessageFactoryInterface::class);
        $this->receiptIds = [];
    }

    public function testProcessingMessages()
    {
        $queueId = "queue";
        $messageBody = "message";

        $this->client->shouldReceive("blpop")->with($queueId, \Mockery::any())->once()->andReturn([$queueId, $messageBody]);

        $this->messageFactory->shouldReceive("createMessage")->with($messageBody, $queueId)->once()->andReturn($this->message);

        $this->message->shouldReceive("setReceiptId")->once()->andReturnUsing(function ($id) {
            $this->receiptIds[] = $id;
        });
        $this->message->shouldReceive("getReceiptId")->once()->andReturnUsing(function () {
            return array_shift($this->receiptIds);
        });

        $queue = new Queue($this->client, $this->messageFactory, $queueId);
        $message = $queue->receiveMessage();
        $queue->completeMessage($message);
    }

    public function testNoMessageToReceive()
    {
        $queueId = "queue";

        $this->client->shouldReceive("blpop")->with($queueId, \Mockery::any())->once()->andReturnNull();

        $this->messageFactory->shouldNotReceive("createMessage");

        $queue = new Queue($this->client, $this->messageFactory, $queueId);
        $this->assertNull($queue->receiveMessage());
    }

    public function testInFlightMessagesReturnedOnExit()
    {
        $queueId = "queue";
        $messageBody = "message";
        $messageCount = 4;

        $queue = new Queue($this->client, $this->messageFactory, $queueId);

        $this->client->shouldReceive("blpop")->with($queueId, \Mockery::any())->times($messageCount)->andReturn([$queueId, $messageBody]);
        $this->client->shouldReceive("lpush")->with($queueId, $messageBody)->times($messageCount);
        $this->messageFactory->shouldReceive("createMessage")->andReturn($this->message);
        $this->message->shouldReceive("getQueueId")->andReturn($queueId);
        $this->message->shouldReceive("getMessage")->andReturn($messageBody);
        $this->message->shouldReceive("setReceiptId")->times($messageCount)->andReturnUsing(function ($id) {
            $this->receiptIds[] = $id;
        });
        $this->message->shouldReceive("getReceiptId")->times($messageCount)->andReturnUsing(function () {
            return array_shift($this->receiptIds);
        });


        for ($i = 0; $i < $messageCount; ++$i) {
            $queue->receiveMessage();
        }

        $queue->__destruct();
        $queue->__destruct(); // ensure messages are tracked properly
    }

    public function testExceptionWhenSending()
    {
        $this->expectException(QueueException::class);
        $this->client->shouldReceive("rpush")->andThrow(\RedisException::class);

        $queue = new Queue($this->client, $this->messageFactory, "foo");
        $queue->sendMessage("blah");
    }

    public function testExceptionWhenReceiving()
    {
        $this->expectException(QueueException::class);
        $this->client->shouldReceive("blpop")->andThrow(\RedisException::class);

        $queue = new Queue($this->client, $this->messageFactory, "foo");
        $queue->receiveMessage();
    }

    public function testExceptionWhenDeleting()
    {
        $this->expectException(QueueException::class);
        $this->client->shouldReceive("del")->andThrow(\RedisException::class);

        $queue = new Queue($this->client, $this->messageFactory, "foo");
        $queue->deleteQueue();
    }

}

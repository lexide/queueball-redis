<?php

namespace Lexide\QueueBall\Redis\Test;

use Lexide\QueueBall\Redis\LazyRedisWrapper;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class LazyRedisWrapperTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected \Redis|MockInterface $redis;

    public function setUp(): void
    {
        $this->redis = \Mockery::mock(\Redis::class);
    }

    /**
     * @dataProvider connectionProvider
     *
     * @param array $parameters
     * @param array $expectedArgs
     * @throws \RedisException
     */
    public function testConnection(array $parameters, array $expectedArgs)
    {
        if (empty($parameters["host"])) {
            $parameters["host"] = "blah";
        }
        $this->redis->shouldIgnoreMissing();
        $this->redis->shouldReceive("connect")->withArgs($expectedArgs)->once();
        $wrapper = new LazyRedisWrapper($this->redis, $parameters);

        $wrapper->del("bar");
    }

    public function testAlreadyConnected()
    {
        $this->redis->shouldIgnoreMissing();
        $this->redis->shouldReceive("isConnected")->andReturnTrue();
        $this->redis->shouldNotReceive("connect");
        $wrapper = new LazyRedisWrapper($this->redis, ["host" => "foo"]);

        $wrapper->del("bar");
    }

    public function testPersistentConnection()
    {
        $parameters = [
            "host" => "blah",
            "persistent" => "foo"
        ];

        $expectedArgs = $this->buildRedisArgs($parameters);

        $this->redis->shouldIgnoreMissing();
        $this->redis->shouldReceive("pconnect")->withArgs($expectedArgs)->once();
        $wrapper = new LazyRedisWrapper($this->redis, $parameters);

        $wrapper->del("bar");
    }

    /**
     * @dataProvider setProvider
     *
     * @param array $extraArgs
     * @param array $expectedOptions
     * @throws \RedisException
     */
    public function testSet(array $extraArgs, array $expectedOptions)
    {
        $key = "foo";
        $value = "bar";

        $this->redis->shouldIgnoreMissing();
        $this->redis->shouldReceive("set")->with($key, $value, $expectedOptions)->once();

        $wrapper = new LazyRedisWrapper($this->redis, ["host" => "foo"]);
        $wrapper->set($key, $value, ...$extraArgs);
    }

    public function connectionProvider(): array
    {
        return [
            "custom port" => [
                ["port" => 1234],
                $this->buildRedisArgs(["port" => 1234])
            ],
            "connect timeout" => [
                ["connectTimeout" => 1.23],
                $this->buildRedisArgs(["connectTimeout" => 1.23])
            ],
            "read timeout" => [
                ["readTimeout" => 3.21],
                $this->buildRedisArgs(["readTimeout" => 3.21])
            ],
            "ssl - ca file" => [
                ["ssl" => ["caFile" => "foo"]],
                $this->buildRedisArgs(["context" => ["ssl" => ["cafile" => "foo"]]])
            ],
            "ssl - local cert" => [
                ["ssl" => ["localCert" => "bar"]],
                $this->buildRedisArgs(["context" => ["ssl" => ["local_cert" => "bar"]]])
            ],
            "ssl - verify peer" => [
                ["ssl" => ["verifyPeer" => "baz"]],
                $this->buildRedisArgs(["context" => ["ssl" => ["verify_peer" => "baz"]]])
            ],
            "auth" => [
                ["username" => "foo", "password" => "bar"],
                $this->buildRedisArgs(["context" => ["auth" => ["foo", "bar"]]])
            ],
            "missing username" => [
                ["password" => "bar"],
                $this->buildRedisArgs(["context" => []])
            ],
            "missing password" => [
                ["username" => "foo"],
                $this->buildRedisArgs(["context" => []])
            ]
        ];
    }

    protected function buildRedisArgs(array $args): array
    {
        $defaultArgs = [
            "host" => \Mockery::any(),
            "port" => \Mockery::any(),
            "connectTimeout" => \Mockery::any(),
            "persistent" => \Mockery::any(),
            "retryInterval" => \Mockery::any(),
            "readTimeout" => \Mockery::any(),
            "context" => \Mockery::any()
        ];

        return array_values(array_replace($defaultArgs, $args));
    }

    public function setProvider(): array
    {
        return [
            "standard" => [
                [],
                []
            ],
            "not exists" => [
                ["NX"],
                ["NX"]
            ],
            "ttl" => [
                ["EX", 123],
                ["EX" => 123]
            ],
            "milli ttl" => [
                ["PX", 123],
                ["PX" => 123]
            ],
            "ttl cast to int" => [
                ["PX", 12.3, "EX", 4.56],
                [
                    "PX" => 12,
                    "EX" => 4
                ]
            ],
            "multiple" => [
                ["XX", "GET", "EX", 345, "KEEPTTL"],
                [
                    "XX",
                    "GET",
                    "EX" => 345,
                    "KEEPTTL"
                ]
            ]
        ];
    }

}

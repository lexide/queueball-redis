<?php

namespace Lexide\QueueBall\Redis;

class LazyRedisWrapper
{

    protected \Redis|\RedisCluster $redis;
    protected array $parameters;

    public function __construct(\Redis|\RedisCluster $redis, array $parameters)
    {
        $this->redis = $redis;
        $this->parameters = $parameters;
    }

    /**
     * @param string $key
     * @return int
     * @throws \RedisException
     */
    public function del(string $key): int {
        $this->connect();
        return $this->redis->del($key);
    }

    /**
     * @param string $key
     * @param string $value
     * @return int
     * @throws \RedisException
     */
    public function lpush(string $key, string $value): int {
        $this->connect();
        return $this->redis->lpush($key, $value);
    }

    /**
     * @param string $key
     * @param string $value
     * @return int
     * @throws \RedisException
     */
    public function rpush(string $key, string $value): int {
        $this->connect();
        return $this->redis->rpush($key, $value);
    }

    /**
     * @param string $key
     * @param int|float $timeout
     * @return array|null
     * @throws \RedisException
     */
    public function blpop(string $key, int|float $timeout): array|null {
        $this->connect();
        return $this->redis->blpop($key, $timeout) ?: null;
    }

    /**
     * @param string $prefix
     * @return array
     * @throws \RedisException
     */
    public function keys(string $prefix): array
    {
        $this->connect();
        return $this->redis->keys($prefix);
    }

    /**
     * @param string $key
     * @return int
     * @throws \RedisException
     */
    public function llen(string $key): int
    {
        $this->connect();
        return $this->redis->llen($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param string|int|float ...$args
     * @return bool
     * @throws \RedisException
     */
    public function set(string $key, mixed $value, string|int|float ...$args): bool
    {
        $options = [];
        $argCount = count($args);
        for ($i = 0; $i < $argCount; ++$i) {
            $arg = $args[$i];
            if (in_array($arg, ["EX", "PX"])) {
                ++$i; // advance the arg counter to the next value
                $options[$arg] = $args[$i];

            } else {
                $options[] = $arg;
            }
        }

        $this->connect();
        return $this->redis->set($key, $value, $options);
    }

    /**
     * @param string $key
     * @return string|false
     * @throws \RedisException
     */
    public function get(string $key): string|false
    {
        $this->connect();
        return $this->redis->get($key);
    }

    /**
     * @throws \RedisException
     */
    protected function connect(): void
    {
        if ($this->redis->isConnected()) {
            return;
        }

        $options = [];
        if (!empty($this->parameters["ssl"])) {
            $options["ssl"] = array_filter([
                "cafile" => $this->parameters["caFile"] ?? null,
                "local_cert" => $this->parameters["localCert"] ?? null,
                "verify_peer" => $this->parameters["verifyPeer"] ?? null
            ], fn($value) => !is_null($value));
        }

        if (!empty($this->parameters["username"]) && !empty($this->parameters["password"])) {
            $options["auth"] = [
                $this->parameters["username"],
                $this->parameters["password"],
            ];
        }

        $this->redis->connect(
            $this->parameters["host"],
            $this->parameters["port"],
            $this->parameters["connectTimeout"],
            '',
            0,
            $this->parameters["readTimeout"],
            $options
        );
    }

}
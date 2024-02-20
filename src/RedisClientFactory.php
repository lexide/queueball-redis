<?php

namespace Lexide\QueueBall\Redis;

use Lexide\QueueBall\Exception\QueueException;

class RedisClientFactory
{

    protected array $defaultParameters;

    /**
     * @param array $defaultParameters
     */
    public function __construct(array $defaultParameters)
    {
        $this->defaultParameters = $defaultParameters;
    }

    /**
     * @param array $parameters
     * @return LazyRedisWrapper
     * @throws QueueException
     * @throws \RedisClusterException
     */
    public function create(array $parameters): LazyRedisWrapper
    {
        $clientParameters = array_merge($this->defaultParameters, $parameters);

        $host = $clientParameters["host"] ?? null;

        if (empty($host)) {
            throw new QueueException("Cannot create Redis client. No host supplied in parameters");
        }

        $client = is_array($host)
            ? new \RedisCluster(null, $host)
            : new \Redis($host);

        return new LazyRedisWrapper($client, $clientParameters);
    }

}
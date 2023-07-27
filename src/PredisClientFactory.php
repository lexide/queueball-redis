<?php

namespace Lexide\QueueBall\Redis;

use Predis\Client;

class PredisClientFactory
{

    protected array $defaultHostParameters;

    protected string $defaultClusterType;

    /**
     * @param array $defaultHostParameters
     * @param string $defaultClusterType
     */
    public function __construct(array $defaultHostParameters, string $defaultClusterType)
    {
        $this->defaultHostParameters = $defaultHostParameters;
        $this->defaultClusterType = $defaultClusterType;
    }

    /**
     * @param array $hosts
     * @param array $hostParameters
     * @param array $options
     * @return Client
     */
    public function create(array $hosts, array $hostParameters = [], array $options = []): Client
    {
        if (count($hosts) > 1 && empty($options["cluster"])) {
            $options["cluster"] = $this->defaultClusterType;
        }

        $clientParameters = [];
        foreach ($hosts as $host) {
            $clientParameters[] = array_merge($this->defaultHostParameters, $hostParameters, ["host" => $host]);
        }

        if (count($clientParameters) == 1) {
            $clientParameters = $clientParameters[0];
        }

        return new Client($clientParameters, $options);
    }

}
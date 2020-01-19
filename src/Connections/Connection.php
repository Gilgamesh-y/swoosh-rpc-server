<?php

namespace Src\RPCServer\Connections;

use Src\RPCServer\Contract\ConnectionInterface;

abstract class Connection implements ConnectionInterface
{
    /**
     * @var string The host of this service
     */
    protected $host = '127.0.0.1';

    /**
     * @var int The port of this service
     */
    protected $port = 9527;

    abstract function services($service_name): array;

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getServerStubHost(): string
    {
        return $this->server_stub_host;
    }

    public function getServerStubPort(): int
    {
        return $this->server_stub_port;
    }
}
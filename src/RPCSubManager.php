<?php

namespace Src\RPCServer;

use Src\App;
use Src\RPCServer\Connections\ConsulConnection;
use Src\RPCServer\Connections\ZookeeperConnection;

class RPCSubManager
{
    /**
     * Service discovery driver
     */
    protected $driver;
    
    /**
     * Witch register center will be used
     */
    public function createConnection()
    {
        $config = $this->getRpcServerConfig();
        switch ($config['driver']) {
            case 'consul':
                return new ConsulConnection;
                break;
            case 'zookeeper':
                return new ZookeeperConnection;
                break;
        }

        throw new \Exception('Unsupported driver [' . $config['driver'] . ']');
    }

    public function getConnection()
    {
        if (!$this->driver) {
            $this->driver = $this->createConnection();
        }
        return $this->driver;
    }

    public function getRpcServerConfig()
    {
        return App::get('config')->get('app.rpc_server');
    }

    public function __call($method, $parameters)
    {
        return $this->getConnection()->$method(...$parameters);
    }

    public static function __callStatic($method, $arguments)
    {
        return (new static)->$method(...$arguments);
    }
}
<?php

namespace Src\RPCServer\Provider;

use Swoole\Server;
use Src\Core\AbstractProvider;
use Src\RPCServer\RPCSubManager;
use Src\ServerEvent\RpcServerEvent;

class RpcServerServiceProvider extends AbstractProvider
{
    protected $onList = [
        'onReceive' => 'Receive',
        'onStart'=>'Start',
        'onShutdown'=>'Shutdown',
        'onWorkerStart'=>'WorkerStart',
        'onWorkerStop'=>'WorkerStop',
        'onConnect'=>'Connect',
        'onClose'=>'Close',
        'onTask'=>'Task',
        'onFinish'=>'Finish',
    ];

    public function register()
    {
        $this->app->set('rpc_stub', function () {
            return (new RPCSubManager)->getConnection();
        });

        $this->app->set('rpc_server', function () {
            $config = $this->app->get('config')->get('app.rpc_server');
            $server = new Server($config['host'], $config['port']);
            $server->set($config['set']);
            $http = new RpcServerEvent($this->app);
            foreach ($this->onList as $function => $event){
                $server->on($event, [$http, $function]);
            }
            
            if ($config['need_registered']) {
                $this->app->get('rpc_stub')->register();
            }
            
            return $server;
        });
    }
}
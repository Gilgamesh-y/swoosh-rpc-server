<?php

namespace Src\RPCServer\Connections;

use Src\App;

class ConsulConnection extends Connection
{
    /**
     * @var string The id of this service
     */
    protected $service_id;

    /**
     * @var string The name of this service
     */
    protected $name;

    /**
     * @var array The tags of this service
     */
    protected $tags = [];

    /**
     * @var string The address of this register server
     */
    protected $server_stub_host = '127.0.0.1';

    /**
     * @var int The port of this register server
     */
    protected $server_stub_port = 8502;

    /**
     * @var string Health check url
     */
    protected $health_check_url = 'http://127.0.0.1/health_check';

    /**
     * @var string Health check interval
     */
    protected $health_check_interval = '10s';

    public function __construct()
    {
        $consul_config = App::get('config')->get('app.consul');
        $rpc_server_config = App::get('config')->get('app.rpc_server');
        $this->service_id = $consul_config['id'];
        $this->name = $consul_config['name'];
        $this->tags = $consul_config['tags'];
        $this->host = $rpc_server_config['host'];
        $this->port = (int)$rpc_server_config['port'];
        $this->server_stub_host = $consul_config['server_stub_host'];
        $this->health_check_url = $consul_config['health_check_url'];
        $this->health_check_interval = $consul_config['health_check_interval'];
    }

    /**
     * Generate the data required by register
     * @return array
     */
    public function generateRegisterData()
    {
        return [
            'ID' => $this->service_id,
            'Name' => $this->name,
            'Tags' => $this->tags,
            'Address' => $this->host,
            'Port' => $this->port,
            'Check' => [
                'HTTP' => $this->health_check_url,
                'Interval' => $this->health_check_interval
            ]
        ];
    }

    /**
     * Register service
     * @return bool
     */
    public function register()
    {
        return $this->put('/v1/agent/service/register', $this->generateRegisterData());
    }

    /**
     * Deregister service
     * @return bool
     */
    public function destruct()
    {
        return $this->put('/v1/agent/service/deregister/'.$this->service_id);
    }

    /**
     * Get catalog of the service
     *
     * @param int|string $service_name
     * @param bool $must_health
     * @return array
     */
    public function services($service_name, $must_health = false): array
    {
        if ($must_health) {
            return json_decode($this->get('/v1/catalog/service/'.$service_name.'?passing'), true);
        }

        return json_decode($this->get('/v1/catalog/service/'.$service_name), true);
    }

    /**
     * @param string $uri
     * @return bool
     */
    public function getCh($uri)
    {
        $ch = curl_init();
        $header[] = 'Content-type:application/json';

        curl_setopt($ch, CURLOPT_URL, $this->server_stub_host.':'.$this->server_stub_port.$uri);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        return $ch;
    }

    /**
     * consul put api
     * @param string $uri
     * @param string $params It can be a detail of service
     * @return bool
     */
    public function put($uri, array $params = [])
    {
        try {
            $ch = $this->getCh($uri);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST,"PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
    
            $res = curl_exec($ch);
            curl_close($ch);

            if ($res === '') {
                return true;
            }
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * consul get api
     * @param string $uri
     * @return string
     */
    public function get($uri): string
    {
        try {
            $ch = $this->getCh($uri);
    
            $res = curl_exec($ch);
            curl_close($ch);

            return $res;
        } catch(\Exception $e) {
            throw $e;
        }
    }
}
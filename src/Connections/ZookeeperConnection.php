<?php

namespace Src\RPCServer\Connections;

use Src\App;
use Zookeeper;

class ZookeeperConnection extends Connection
{
    /**
     * Undocumented variable
     *
     * @var Zookeeper
     */
    protected $connection;

    /**
     * The id of this service
     * 
     * @var string
     */
    protected $service_id;

    /**
     * The name of this service
     * 
     * @var string
     */
    protected $name;

    /**
     * The tags of this service
     * 
     * @var array
     */
    protected $tags = [];

    /**
     * The address of this register server
     * 
     * @var string
     */
    protected $server_stub_host = '127.0.0.1';

    /**
     * The port of this register server
     * 
     * @var int
     */
    protected $server_stub_port = 2181;

    /**
     * Health check url
     * 
     * @var string
     */
    protected $health_check_url = 'http://127.0.0.1';

    /**
     * Health check interval
     * 
     * @var string
     */
    protected $health_check_interval = '10s';

    public function __construct()
    {
        if (!extension_loaded('zookeeper')) {
            throw new \Exception('zookeeper扩展未安装');
        }
        $consul_config = App::get('config')->get('app.consul');
        $rpc_server_config = App::get('config')->get('app.rpc_server');
        $this->service_id = $consul_config['id'];
        $this->name = $consul_config['name'];
        $this->tags = $consul_config['tags'];
        $this->host = $rpc_server_config['host'];
        $this->port = (int)$rpc_server_config['port'];
        $this->server_stub_host = $consul_config['server_stub_host'];
        $this->server_stub_port = $consul_config['server_stub_port'];
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
            'Name' => $this->name,
            'Tags' => $this->tags,
            'ServiceAddress' => $this->host,
            'ServicePort' => $this->port
        ];
    }

    public function register()
    {
        $this->connect();
        $this->create($this->name.'/'.$this->host);
        $this->set($this->name.'/'.$this->host, json_encode($this->generateRegisterData(), JSON_UNESCAPED_UNICODE));
        $this->health_check();
    }

    /**
     * Get catalog of the service
     *
     * @param int|string $service_name
     * @return array
     */
    public function services($service_name = ''): array
    {
        $childs = $this->connection->getChildren('/'.$service_name);
        if (!$service_name) {
            return $childs;
        }
        foreach ($childs as &$child) {
            $child = $this->get($this->name.'/'.$child);
            $child = json_decode($child, true);
        }
        
        return $childs;
    }

    /**
     * Create a handle to used communicate with zookeeper
     * @return void
     */
    public function connect()
    {
        $this->connection = new Zookeeper($this->server_stub_host.':'.$this->server_stub_port);
    }

    
    /**
     * Create a node synchronously
     *
     * @param string $path The name of the node. Expressed as a file name with slashes separating ancestors of the node.
     * @param string $value The data to be stored in the node.
     * @param array $acls The initial ACL of the node. The ACL must not be null or empty.
     * @param integer $flags this parameter can be set to 0 for normal create or an OR of the Create Flags
     * @return void
     */
    public function create(string $path, string $value = null, array $acls = [['perms' => Zookeeper::PERM_ALL, 'scheme' => 'world', 'id' => 'anyone']], int $flags = null)
    {
        if (!$this->exists($path)) {
            if (count($path_arr = explode('/', $path)) > 1) {
                $path = '';
                foreach ($path_arr as $p) {
                    $path = $path . '/' . $p;
                    $this->connection->create($path, $value, $acls, $flags);
                }
            } else {
                $this->connection->create('/'.$path, $value, $acls, $flags);
            }
        }
    }

    /**
     *  Checks the existence of a node in zookeeper synchronously
     *
     * @param string $path The name of the node. Expressed as a file name with slashes separating ancestors of the node.
     * @param callable $watcher_cb if nonzero, a watch will be set at the server to notify the client if the node changes. The watch will be set even if the node does not
     * @return array|bool Returns the value of stat for the path if the given node exists, otherwise false.
     */
    public function exists(string $path,callable $watcher_cb = null)
    {
        return $this->connection->exists('/'.$path, $watcher_cb);
    }

    /**
     * Gets the data associated with a node synchronously
     *
     * @param string $path The name of the node. Expressed as a file name with slashes separating ancestors of the node.
     * @param callable $watcher_cb If nonzero, a watch will be set at the server to notify the client if the node changes.
     * @param array $stat If not NULL, will hold the value of stat for the path on return.
     * @param integer $max_size Max size of the data. If 0 is used, this method will return the whole data.
     * @return string|bool Returns the data on success, and false on failure.
     */
    public function get(string $path,callable $watcher_cb = null,array &$stat = null,int $max_size = 0)
    {
        return $this->connection->get('/'.$path, $watcher_cb, $stat, $max_size);
    }

    /**
     * Sets the data associated with a node
     *
     * @param string $path The name of the node. Expressed as a file name with slashes separating ancestors of the node.
     * @param string $value The data to be stored in the node.
     * @param integer $version The expected version of the node.The function will fail if the actual version of the node does not match the expected version. If -1 is used the version check will not take place.
     * @param array $stat If not NULL, will hold the value of stat for the path on return.
     * @return bool Returns true on success, and false on failure.
     */
    public function set(string $path, string $value,int $version = -1, array &$stat = null)
    {
        return $this->connection->set('/'.$path, $value, $version, $stat);
    }

    /**
     * Delete a node in zookeeper synchronously
     *
     * @param string $path The name of the node. Expressed as a file name with slashes separating ancestors of the node.
     * @param integer $version The expected version of the node. The function will fail if the actual version of the node does not match the expected version. If -1 is used the version check will not take place.
     * @return bool Returns true on success, and false on failure.
     */
    public function delete(string $path, int $version = -1)
    {
        return $this->connection->delete('/'.$path, $version);
    }

    public function health_check()
    {
        // 大约2分钟检测一次健康检测
        swoole_timer_tick(12000, function () {
            $ch = curl_init();
            $header[] = 'Content-type:application/json';

            curl_setopt($ch, CURLOPT_URL, $this->health_check_url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $res = curl_exec($ch);
            curl_close($ch);

            if ($res === false) {
                $this->delete($this->name.'/'.$this->host);
            }
        });
    }
}
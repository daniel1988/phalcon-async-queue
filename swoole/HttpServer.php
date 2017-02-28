<?php

define('APP_PATH', dirname(__DIR__));

class HttpServer {

    var $http_srv = null;

    var $setting = [] ;

    var $application = null ;

    var $init_phalcon = false ;

    public function init_setting( $host, $port, $isdaemon )
    {
        $this->setting = [
            'host'             => $host,    //监听ip
            'port'             => $port,    //监听端口
            'env'              => 'dev',    //环境 dev|test|prod
            'async'            => 1,
            'open_tcp_nodelay' => 1,    //关闭Nagle算法,提高HTTP服务器响应速度
            'daemonize'        => $isdaemon ? 1 : 0,    //是否守护进程 1=>守护进程| 0 => 非守护进程
            'worker_num'       => 1,    //worker进程 cpu 1-4倍
            'task_worker_num'  => 4,    //task进程
            'task_max_request' => 10000,    //当task进程处理请求超过此值则关闭task进程
            'process_name'     => SWOOLE_TASK_NAME_PRE.'-'. $port, //swoole 进程名称
            'tmp_dir'          => SWOOLE_PATH."/tmp",
            'log_dir'          => SWOOLE_PATH."/tmp/log",
            'task_tmpdir'      => SWOOLE_PATH."/tmp/task", //task进程临时数据目录
            'log_file'         => SWOOLE_PATH."/tmp/log/http.log", //日志文件目录
            'pid_file'         => SWOOLE_PATH."/tmp/".SWOOLE_TASK_PID_PRE."-{$port}.pid",
        ];

        $cfg_file = SWOOLE_PATH . DS . 'config' . DS . "swoole.ini";

        $ini_setting = [];
        if ( file_exists($cfg_file) ) {
            $ini_setting = $this->getSetting() ;
            $ini_setting['port']            = $port;
            $ini_setting['host']            = $host;
            $ini_setting['process_name']    = $this->setting['process_name'];
            $ini_setting['pid_file']        = $this->setting['pid_file'];
        }
        $setting_str = '[http]' . PHP_EOL;
        foreach ($ini_setting as $k => $v) {
            $setting_str .= "{$k} = {$v}" . PHP_EOL;
        }
        file_put_contents($cfg_file, $setting_str);

        $this->setting = $ini_setting;
        return $this->setting;
    }

    public function getSetting() {
        $cfg_file = SWOOLE_PATH . DS . 'config' . DS . 'swoole.ini';
        if ( file_exists($cfg_file) ) {
            $ini = parse_ini_file($cfg_file, true);
            return $ini['http'];
        }
        return $this->setting;
    }

    public function init_phalcon()
    {
        if ( $this->init_phalcon ) {
            return true;
        }
        require APP_PATH. '/apps/bootstrap/services.php';
        require APP_PATH. '/apps/bootstrap/loader.php';
        //server注入容器
        $server = $this->http_srv;
        $di->setShared('server', function () use ($server) {
            return $server;
        });

        $this->application = new \Phalcon\Mvc\Application;
        $this->application->setDI($di);
        $this->init_phalcon = true;

        echo "init_phalcon success ...\n";
        return true;
    }

    public function init_swoole( $host='127.0.0.1', $port=9510, $isdaemon = false ) {
        $this->http_srv = new swoole_http_server($host, $port);

        $settings = $this->init_setting($host, $port, $isdaemon) ;
        $this->http_srv->set( $settings );
        $this->http_srv->on('start',  [$this, 'onStart']);
        $this->http_srv->on('workerStart',        [$this, 'onWorkerStart']);
        $this->http_srv->on('managerStart',  [$this, 'onManagerStart']);
        $this->http_srv->on('request', [$this, 'onRequest']);
        $this->http_srv->on('task', [$this, 'onTask']);
        $this->http_srv->on('finish', [$this, 'onfinish']);

        $this->init_phalcon();
        $this->http_srv->start();
        return $this->http_srv;
    }

    public function onStart( $server )
    {
        echo 'Date:' . date('Y-m-d H:i:s') . "\t swoole_http_server master worker start\n";
        $this->setProcessName($server->setting['process_name'] . '-master');

        $pid = "{$this->http_srv->master_pid}\n{$this->http_srv->manager_pid}";
        file_put_contents($this->setting['pid_file'], $pid);
    }

    public function onWorkerStart( $server, $worker_id )
    {
        if ($worker_id >= $this->setting['worker_num']) {
            $this->setProcessName($server->setting['process_name'] . '-task');
        } else {
            $this->setProcessName($server->setting['process_name'] . '-event');
        }
    }

    public function onManagerStart( $server )
    {
        echo 'Date:' . date('Y-m-d H:i:s') . "\t swoole_http_server manager worker start\n";
        $this->setProcessName($server->setting['process_name'] . '-manager');
    }


    public function onRequest( $request, $response )
    {
        //获取swoole服务的当前状态
        if (isset($request->get['cmd']) && $request->get['cmd'] == 'status') {
            $response->end(json_encode($this->http_srv->stats()));
            return true;
        }

        if ($request->server['request_uri'] == '/favicon.ico' || $request->server['path_info'] == '/favicon.ico') {
            return $response->end();
        }

        $setting = $this->getSetting();
        $result = '' ;
        echo $request->server['request_uri'],"\n";
        if ( $setting['async'] ) {
            $this->http_srv->task( $request ) ;
            $result = json_encode($request) . PHP_EOL;
        } else {
            $result = $this->_handle_request( $request ) ;
        }
        $response->end($result);
    }

    public function onTask( $server, $task_id, $from_id, $request)
    {
        $this->_handle_request( $request ) ;
        $this->log('task', print_r($request,true));
    }

    public function onFinish( $server , $task_id , $data )
    {
        $this->log('finish', print_r(func_get_args(), true));
    }

    function setProcessName($name)
    {
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($name);
        } else {
            if (function_exists('swoole_set_process_name')) {
                swoole_set_process_name($name);
            } else {
                trigger_error(__METHOD__ . " failed. require cli_set_process_title or swoole_set_process_name.");
            }
        }
    }

    private function _handle_request( $request )
    {
        //同步处理
        $_SERVER = $request->server;

        //构造url请求路径,phalcon获取到$_GET['_url']时会定向到对应的路径，否则请求路径为'/'
        $_GET['_url'] = $request->server['request_uri'];

        if ($request->server['request_method'] == 'GET' && isset($request->get)) {
            foreach ($request->get as $key => $value) {
                $_GET[$key] = $value;
                $_REQUEST[$key] = $value;
            }
        }
        if ($request->server['request_method'] == 'POST' && isset($request->post) ) {
            foreach ($request->post as $key => $value) {
                $_POST[$key] = $value;
                $_REQUEST[$key] = $value;
            }
        }
        //处理请求
        ob_start();
        try {

            echo $this->application->handle()->getContent();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        $result = ob_get_contents();
        echo $result ;
        ob_end_clean();
        return $result ;
    }

    public function log($file, $message)
    {
        $log_file = SWOOLE_PATH . '/tmp/task/' . $file . '-' . date('Y-m-d') . '.log';
        $message = sprintf("[%s]\n%s", date('Y-m-d H:i:s'), $message);
        file_put_contents( $log_file, $message, FILE_APPEND );
    }

}

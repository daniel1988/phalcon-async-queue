<?php

define('APP_PATH', dirname(__DIR__));

class HttpServer {

    var $http_srv = null;

    var $setting = [] ;

    var $application = null ;

    public function init_setting( $host, $port, $isdaemon )
    {
        $this->setting = [
            'host'             => $host,    //监听ip
            'port'             => $port,    //监听端口
            'env'              => 'dev',    //环境 dev|test|prod
            'open_tcp_nodelay' => 1,    //关闭Nagle算法,提高HTTP服务器响应速度
            'daemonize'        => $isdaemon ? 1 : 0,    //是否守护进程 1=>守护进程| 0 => 非守护进程
            'worker_num'       => 4,    //worker进程 cpu 1-4倍
            'task_worker_num'  => 4,    //task进程
            'task_max_request' => 10000,    //当task进程处理请求超过此值则关闭task进程
            'process_name'     => SWOOLE_TASK_NAME_PRE. $port, //swoole 进程名称
            'root'             => SWOOLE_PATH."/app",  //open_basedir 安全措施
            'tmp_dir'          => SWOOLE_PATH."/tmp",
            'log_dir'          => SWOOLE_PATH."/tmp/log",
            'task_tmpdir'      => SWOOLE_PATH."/tmp/task", //task进程临时数据目录
            'log_file'         => SWOOLE_PATH."/tmp/log/http.log", //日志文件目录
            'pid_file'         => SWOOLE_PATH."/tmp/".SWOOLE_TASK_PID_PRE."-{$port}.pid",
        ];

        $cfg_file = SWOOLE_PATH . DS . 'config' . DS . 'swoole.ini';

        $iniSetting = '[http]' . PHP_EOL;
        foreach ($this->setting as $k => $v) {
            $iniSetting .= "{$k} = {$v}" . PHP_EOL;
        }

        file_put_contents($cfg_file, $iniSetting);

        return $this->setting;
    }

    public function getSetting() {
        $cfg_file = SWOOLE_PATH . DS . 'config' . DS . 'swoole.ini';
        $ini = parse_ini_file($cfg_file, true);
        return $ini['http'];
    }

    public function init_swoole( $host='0.0.0.0', $port=9510, $isdaemon = false ) {
        $this->http_srv = new swoole_http_server($host, $port);

        $settings = $this->init_setting($host, $port, $isdaemon) ;
        $this->http_srv->set( $settings );


        $this->http_srv->on('start',  [$this, 'onStart']);
        $this->http_srv->on('workerStart',        [$this, 'onWorkerStart']);
        $this->http_srv->on('managerStart',  [$this, 'onManagerStart']);
        $this->http_srv->on('request', [$this, 'onRequest']);
        $this->http_srv->on('task', [$this, 'onTask']);
        $this->http_srv->on('finish', [$this, 'onfinish']);

        $this->http_srv->start();

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
        require APP_PATH. '/apps/bootstrap/services.php';
        require APP_PATH. '/apps/bootstrap/loader.php';
        //server注入容器
        $server = $this->http_srv;
        $di->setShared('server', function () use ($server) {
            return $server;
        });

        $this->application = new \Phalcon\Mvc\Application;
        $this->application->setDI($di);

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
        $this->http_srv->task( $request ) ;

        $out = json_encode($request) . PHP_EOL;
        //INFO 立即返回 非阻塞
        $response->end($out);

    }

    public function onTask( $server, $task_id, $from_id, $data)
    {
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
    }

    public function onFinish( $server , $task_id , $data )
    {
        var_dump( 'onFinish', func_get_args() ) ;
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

}

// $httpsrv = new HttpServer();

// $httpsrv->init_swoole('9510');
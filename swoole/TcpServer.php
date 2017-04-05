<?php

class TcpServer
{

    var $setting = [];
    var $opts       = [];

    public function run()
    {
        $this->initOpt();
    }

    public function initOpt()
    {
        $shortopts = "h:p:d:s:";
        $longopts = [
            'help',
        ];
        $this->opts = getopt($shortopts, $longopts);
        if ( empty( $this->opts ) || isset( $this->opts['help'] ) ) {
            echo <<<EOF
用法：$ php TcpServer.php -h 127.0.0.1 -p 9520
参数说明：
    -h 服务IP
    -p 端口
    -d 0|1 是否作为守护进程
    -s start|shutdown|reload
\n
EOF;
            exit;
        }

        $host = $this->opts['h'];
        $port = $this->opts['p'];
        $daemon = isset( $this->opts['d'] ) ? true : false ;
        $this->initSetting($host, $port, $daemon);

        if ( isset( $this->opts['s'] ) ) {
            $signal = $this->opts['s'];

            switch($signal) {
                case 'shutdown':
                    $this->shutdown();
                    exit;
                    break;
                case 'reload':
                    $this->shutdown();
                    break;
            }
        }
        $this->start();
    }

    public function shutdown() {
        $setting = $this->setting;
        $shell_cmd = "kill -9 $(ps -ef|grep {$setting['process_name']}|gawk '$0 !~/grep/ {print $2}' |tr -s '\n' ' ')";
        $res = exec( $shell_cmd );
        echo sprintf("Date:%s \t shutdown:%s", date('Y-m-d'), $res);
    }


    public function start()
    {
        $setting = $this->setting;

        $serv = new swoole_server($setting['host'], $setting['port'], SWOOLE_BASE, SWOOLE_SOCK_TCP);

        $serv->set( $setting );

        $serv->on('Start', [$this, 'onStart']);
        $serv->on('WorkerStart', [$this, 'onWorkerStart']);
        $serv->on('ManagerStart', [$this, 'onManagerStart']);
        $serv->on('Connect', [$this, 'onConnect']);
        $serv->on('Receive', [$this, 'onReceive']);
        $serv->start();
    }

    public function initSetting( $host, $port, $daemon)
    {

        $this->setting = [
            'host'          => $host,
            'port'          => $port,
            'worker_num'    => 4,
            'daemonize'     => $daemon ? true : false,
            'backlog'       => 128,
            'process_name'  => 'tcpsrv-' . $port
        ];
        var_dump( $this->setting ) ;
        return $this->setting;

    }

    public function onStart( $server )
    {
        echo 'Date:' . date('Y-m-d H:i:s') . "\t master started\n";
        $this->setProcessName($server->setting['process_name'] . '-master');
    }

    public function onWorkerStart( $server, $worker_id )
    {
        echo 'Date:' . date('Y-m-d H:i:s') . "\t onWorkerStart\n";
        if ($worker_id >= $this->setting['worker_num']) {
            $this->setProcessName($server->setting['process_name'] . '-task');
        } else {
            $this->setProcessName($server->setting['process_name'] . '-event');
        }
    }

    public function onManagerStart( $server )
    {
        echo 'Date:' . date('Y-m-d H:i:s') . "\t onManagerStart\n";
        $this->setProcessName($server->setting['process_name'] . '-manager');
    }

    public function onConnect( $serv, $fd )
    {
        echo "{$fd}--->Client:Connect.\n";
    }

    public function onReceive( $serv, $fd, $from_id, $data )
    {

        // $serv->tick(1000, function() use ($serv, $fd) {
        //     $serv->send($fd, date('Y-m-d H:i:s'));
        // });
        $serv->send($fd, 'Swoole: '.$data);
        $serv->close($fd);
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

$tcpsrv = new TcpServer();
$tcpsrv->run();
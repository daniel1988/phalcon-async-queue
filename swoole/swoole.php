#!/bin/env php
<?php
/**
 * 默认时区定义
 */
date_default_timezone_set('Asia/Shanghai');

/**
 * 设置错误报告模式
 */
error_reporting(E_ALL);

/**
 * 设置默认区域
 */
setlocale(LC_ALL, "zh_CN.utf-8");

/**
 * 检测 PDO_MYSQL
 */
if (!extension_loaded('pdo_mysql')) {
    exit('PDO_MYSQL extension is not installed' . PHP_EOL);
}
/**
 * 检查exec 函数是否启用
 */
if (!function_exists('exec')) {
    exit('exec function is disabled' . PHP_EOL);
}

/**
 * 定义项目根目录&swoole-task pid
 */
defined('DS') || define('DS', DIRECTORY_SEPARATOR);
define('ROOT_PATH', dirname(__DIR__));
define('SWOOLE_PATH', __DIR__);
define('SWOOLE_TASK_PID_PATH', SWOOLE_PATH . DS . 'tmp');
define('SWOOLE_TASK_NAME_PRE', 'http-swoole');
define('SWOOLE_TASK_PID_PRE', 'swoole-task');
// include ROOT_PATH . '/app/bootstrap/defined.php';
/**
 * 加载 swoole http server
 */
include dirname(__FILE__) . '/HttpServer.php';
/**
 * 查找指定端口的占用情况
 * @param int $port 端口号
 * @return array
 */
function portBind($port)
{
    $ret = [];
    $cmd = "/usr/bin/lsof -i :{$port}|awk '$1 != \"COMMAND\"  {print $1, $2, $9}'";
    exec($cmd, $out);
    if ($out) {
        foreach ($out as $v) {
            $a = explode(' ', $v);
            list($ip, $p) = explode(':', $a[2]);
            $ret[$a[1]] = [
                'cmd'  => $a[0],
                'ip'   => $ip,
                'port' => $p,
            ];
        }
    }

    return $ret;
}

/**
 * 启动swoole-task服务
 * @param $host
 * @param $port
 * @param $daemon
 * @param $name
 */

function servStart($server, $host, $port, $isdaemon)
{
    $start = function ($server, $host, $port, $isdaemon) {
        echo "正在启动 swoole-task 服务" . PHP_EOL;

        if ($isdaemon) {
            $process = new swoole_process(function () use ($server, $host, $port, $isdaemon) {
                $server->init_swoole($host, $port, $isdaemon);
            });
            $process->start();
            var_dump( $process ) ;
            //WARN swoole_process 自定义进程 误报错误，忽略即可
            $wait = 60;
            do {
                if ($wait <= 0) {
                    echo "启动 swoole-task 服务超时" . PHP_EOL;
                    break;
                }
                if (portBind($port)) {
                    echo "启动 swoole-task 服务成功" . PHP_EOL;
                    break;
                }
                $wait--;
            } while (true);

        } else {
            $server->init_swoole($host, $port, $isdaemon);
        }
    };

    $pidFile = SWOOLE_TASK_PID_PATH . DS . SWOOLE_TASK_PID_PRE . "-{$port}.pid";
    $ret = [
        'errno' => 0,
        'error' => '',
    ];
    //检查进程文件是否可写
    if (!is_writable(dirname($pidFile))) {
        $error = "swoole-task-pid文件需要目录的写入权限:" . dirname($pidFile);

        $ret['errno'] = 1;
        $ret['error'] = $error;

        return $ret;
    }
    //检查进程文件是否存在
    if (file_exists($pidFile)) {
        $pid = explode("\n", file_get_contents($pidFile));
        $cmd = "ps ax | awk '{ print $1 }' | grep -e \"^{$pid[0]}$\"";
        exec($cmd, $out);
        if (!empty($out)) {
            //进程存在
            $error = "swoole-task pid文件 " . $pidFile . " 存在，swoole-task 服务器已经启动，进程pid为:{$pid[0]}";

            $ret['errno'] = 2;
            $ret['error'] = $error;

            return $ret;
        } else {
            //WARN 进程不存在 删除进程文件
            $ret['msg'][] = "警告:swoole-task pid文件 " . $pidFile . " 存在，可能swoole-task服务上次异常退出(非守护模式ctrl+c终止造成是最大可能)" . PHP_EOL;
            unlink($pidFile);
        }
    }
    //端口绑定检查
    $bind = portBind($port);
    if ($bind) {
        foreach ($bind as $k => $v) {
            if ($v['ip'] == '*' || $v['ip'] == $host) {
                $error = "端口已经被占用 {$host}:$port, 占用端口进程ID {$k}" . PHP_EOL;

                $ret['errno'] = 3;
                $ret['error'] = $error;

                return $ret;
            }
        }
    }
    if ($ret['errno'] == 0) {

        $start($server, $host, $port, $isdaemon);
    }

    return $ret;
}

function servStop($host, $port)
{
    $stop = function ($pid, $pidFile, $host, $port) {
        echo "swoole-task 服务终止开始" . PHP_EOL;
        $cmd = "kill {$pid}";
        exec($cmd);
        $wait = 0;
        do {
            $out = [];
            $c = "ps ax | awk '{ print $1 }' | grep -e \"^{$pid}$\"";
            exec($c, $out);
            if (empty($out)) {
                break;
            }
            echo "正在关闭服务:{$wait} s" . PHP_EOL;
            $wait++;
            sleep(1);
        } while (true);
        //确保停止服务后swoole-task-pid文件被删除
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
        echo "执行命令 {$cmd} 成功，端口 {$host}:{$port} 进程结束" . PHP_EOL;
    };

    $pidFile = SWOOLE_TASK_PID_PATH . DS . SWOOLE_TASK_PID_PRE . "-{$port}.pid";
    $ret = [
        'errno' => 0,
        'error' => '',
    ];

    if (!file_exists($pidFile)) {
        $error = 'swoole-task-pid文件:' . $pidFile . '不存在';

        $ret['errno'] = 1;
        $ret['error'] = $error;

        return $ret;
    }
    $pid = explode("\n", file_get_contents($pidFile));
    $bind = portBind($port);
    if (empty($bind) || !isset($bind[$pid[0]])) {
        $error = "指定端口占用进程不存在 port:{$port}, pid:{$pid[0]}" . PHP_EOL;

        $ret['errno'] = 1;
        $ret['error'] = $error;

        return $ret;
    }
    if ($ret['error'] == 0) {
        $stop($pid[0], $pidFile, $host, $port);
    }

    return $ret;
}

function servStatus($host, $port)
{
    $status = function ($host, $port) {
        echo "swoole-task {$host}:{$port} 运行状态" . PHP_EOL;
        $cmd = "curl -s '{$host}:{$port}?cmd=status'";
        exec($cmd, $out);
        if (empty($out)) {
            echo "{$host}:{$port} swoole-task服务不存在或者已经停止" . PHP_EOL;
        }
        foreach ($out as $v) {
            $a = json_decode($v);
            foreach ($a as $k1 => $v1) {
                echo "$k1:\t$v1" . PHP_EOL;
            }
        }
    };

    $pidFile = SWOOLE_TASK_PID_PATH . DS . SWOOLE_TASK_PID_PRE . "-{$port}.pid";
    $ret = [
        'errno' => 0,
        'error' => '',
    ];
    $pid = explode("\n", file_get_contents($pidFile));
    $bind = portBind($port);
    if (empty($bind) || !isset($bind[$pid[0]])) {
        $error = "指定端口占用进程不存在 port:{$port}, pid:{$pid[0]}" . PHP_EOL;

        $ret['errno'] = 1;
        $ret['error'] = $error;

        return $ret;
    }

    if ($ret['errno'] == 0) {
        $status($host, $port);
    }

    return $ret;
}

function servList()
{
    $ret = [
        'errno' => 0,
        'error' => '',
    ];
    $cmd = "ps aux|grep " . SWOOLE_TASK_NAME_PRE . "|grep -v grep|awk '{print $1, $2, $6, $8, $9, $11}'";
    echo $cmd ,"\n";
    exec($cmd, $out);
    if (empty($out)) {
        $error = "没有发现正在运行的swoole-task服务" . PHP_EOL;

        $ret['errno'] = 1;
        $ret['error'] = $error;

        return $ret;
    }
    echo "本机运行的swoole-task服务进程" . PHP_EOL;
    echo "USER PID RSS(kb) STAT START COMMAND" . PHP_EOL;
    foreach ($out as $v) {
        echo $v . PHP_EOL;
    }

    return $ret;
}

//可执行命令
$cmds = [
    'start',
    'stop',
    'restart',
    'status',
    'list',
    'config',
];
$shortopts = "dDh:p:n:";
$longopts = [
    'help',
    'daemon',
    'nondaemon',
    'host:',
    'port:',
];
$opts = getopt($shortopts, $longopts);

if (isset($opts['help']) || $argc < 2) {
    echo <<<HELP
用法：php swoole.php 选项 ... 命令[start|stop|restart|status|list]
管理swoole-task服务,确保系统 lsof 命令有效
如果不指定监听host或者port，使用配置参数

参数说明
    --help  显示本帮助说明
    -d, --daemon    指定此参数,以守护进程模式运行,不指定则读取配置文件值
    -D, --nondaemon 指定此参数，以非守护进程模式运行,不指定则读取配置文件值
    -h, --host  指定监听ip,例如 php swoole.php -h 127.0.0.1
    -p, --port  指定监听端口port， 例如 php swoole.php -h 127.0.0.1 -p 9520
启动swoole-task 如果不指定 host和port，读取config目录里面的swoole.ini中的配置
关闭swoole-task 必须指定port,没有指定host，关闭的监听端口是  *:port,指定了host，关闭 host:port端口
重启swoole-task 必须指定端口
获取swoole-task 状态，必须指定port(不指定host默认127.0.0.1), tasking_num是正在处理的任务数量(0表示没有待处理任务)

HELP;
    exit;
}
//参数检查
foreach ($opts as $k => $v) {
    if (($k == 'h' || $k == 'host')) {
        if (empty($v)) {
            exit("参数 -h --host 必须指定值\n");
        }
    }
    if (($k == 'p' || $k == 'port')) {
        if (empty($v)) {
            exit("参数 -p --port 必须指定值\n");
        }
    }
    if (($k == 'n' || $k == 'name')) {
        if (empty($v)) {
            exit("参数 -n --name 必须指定值\n");
        }
    }
}

//命令检查
$cmd = $argv[$argc - 1];
if (!in_array($cmd, $cmds)) {
    exit("输入命令有误 : {$cmd}, 请查看帮助文档\n");
}

$server = new HttpServer();
$conf = $server->getSetting();
//监听ip，空读取配置文件
$host = $conf['host'];
if (!empty($opts['h'])) {
    $host = $opts['h'];
    if (!filter_var($host, FILTER_VALIDATE_IP)) {
        exit("输入host有误:{$host}");
    }
}
if (!empty($opts['host'])) {
    $host = $opts['host'];
    if (!filter_var($host, FILTER_VALIDATE_IP)) {
        exit("输入host有误:{$host}");
    }
}
//监听端口，0 读取配置文件
$port = $conf['port'];
if (!empty($opts['p'])) {
    $port = (int)$opts['p'];
    if ($port <= 0) {
        exit("输入port有误:{$port}");
    }
}
if (!empty($opts['port'])) {
    $port = (int)$opts['port'];
    if ($port <= 0) {
        exit("输入port有误:{$port}");
    }
}
//是否守护进程 -1 读取配置文件
$isdaemon = $conf['daemonize'];
if (isset($opts['D']) || isset($opts['nondaemon'])) {
    $isdaemon = 0;
}
if (isset($opts['d']) || isset($opts['daemon'])) {
    $isdaemon = 1;
} else {
    $isdaemon = 0;
}
if ($cmd == 'start') {
    $ret = servStart($server, $host, $port, $isdaemon);
    if ($ret['errno'] > 0) {
        exit($ret['error'] . PHP_EOL);
    }
}

if ($cmd == 'stop') {
    if (empty($port)) {
        exit("停止swoole-task服务必须指定port" . PHP_EOL);
    }
    $ret = servStop($host, $port);
    if ($ret['errno'] > 0) {
        exit($ret['error'] . PHP_EOL);
    }
}
if ($cmd == 'restart') {
    if (empty($port)) {
        exit("重启swoole-task服务必须指定port" . PHP_EOL);
    }
    echo "重启swoole-task服务" . PHP_EOL;
    $ret = servStop($host, $port);
    if ($ret['errno'] > 0) {
        exit($ret['error'] . PHP_EOL);
    }
    $ret = servStart($server, $host, $port, $isdaemon);
    if ($ret['errno'] > 0) {
        exit($ret['error'] . PHP_EOL);
    }
}
if ($cmd == 'status') {
    if (empty($host)) {
        $host = '127.0.0.1';
    }
    if (empty($port)) {
        exit("查看swoole-task服务必须指定port(host不指定默认使用127.0.0.1)" . PHP_EOL);
    }
    $ret = servStatus($host, $port);
    if ($ret['errno'] > 0) {
        exit($ret['error'] . PHP_EOL);
    }
}
if ($cmd == 'list') {
    servList();
}
if ($cmd == 'config') {
    echo "swoole-task 默认配置文件内容:" . SWOOLE_PATH . DS . 'config' . DS . 'swoole.ini' . PHP_EOL;
    foreach ($conf as $k => $v) {
        echo "{$k}:\t{$v}" . PHP_EOL;
    }
}

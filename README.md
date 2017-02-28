# php-async-queue

## 简介

phalcon + swoole 异步任务队列，可进行swoole-server管理，任务队列逻辑可由phalcon框架实现，如：
```
$ php swoole.php --help
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

```

## 运行与管理

```
$ php swoole.php -d -h 127.0.0.1 -p9510 start

正在启动 swoole-task 服务
启动 swoole-task 服务成功
[2017-02-27 10:10:02 @10457.0]  ERROR   zm_deactivate_swoole (ERROR 9003): worker process is terminated by exit()/die().

```
>　以swoole_process启动时遇到php中exit与die函数时会导致swoole_process退出，可不用理会


## 进程列表
```
$ php swoole.php list
ps aux|grep http-swoole|grep -v grep|awk '{print $1, $2, $6, $8, $9, $11}'
本机运行的swoole-task服务进程
USER PID RSS(kb) STAT START COMMAND
daniell+ 8445 12992 Ssl 11:06 http-swoole-9510-master
daniell+ 8446 12136 S 11:06 http-swoole-9510-manager
daniell+ 8453 15504 S 11:06 http-swoole-9510-task
daniell+ 8454 15504 S 11:06 http-swoole-9510-task
daniell+ 8455 15504 S 11:06 http-swoole-9510-task
daniell+ 8456 15504 S 11:06 http-swoole-9510-task
daniell+ 8457 14364 S 11:06 http-swoole-9510-event
```

## 运行状态

```
$ php swoole.php status
swoole-task 127.0.0.1:9510 运行状态
start_time: 1488251169
connection_num: 2
accept_count:   12
close_count:    10
tasking_num:    0
request_count:  23
worker_request_count:   23
```

## 重启与关闭
```
$ php swoole.php -d -p 9510 restart
重启swoole-task服务
swoole-task 服务终止开始
正在关闭服务:0 s
执行命令 kill 8445 成功，端口 :9510 进程结束
正在启动 swoole-task 服务
init_phalcon success ...
启动 swoole-task 服务成功
[2017-02-28 11:10:14 @8697.0]   ERROR   zm_deactivate_swoole (ERROR 9003): worker process is terminated by exit()/die()


$ php swoole.php -p 9510 stop
swoole-task 服务终止开始
正在关闭服务:0 s
执行命令 kill 8717 成功，端口 :9510 进程结束

```



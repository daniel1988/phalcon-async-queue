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





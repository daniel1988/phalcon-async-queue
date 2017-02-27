#!/usr/bin
kill -9 $(ps -ef|grep swoole-$1|gawk '$0 !~/grep/ {print $2}' |tr -s '\n' ' ')

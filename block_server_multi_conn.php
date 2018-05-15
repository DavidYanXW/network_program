<?php
/**
 *
 * socket server
 * 基于php socket函数族
 * IO模型：同步阻塞
 * 粘包处理：自定义包头，包头内容是包体长度
 * 连接数: 多进程多连接。
 * 每次accept一个socket，server单独fork一个子进程来处理socket
 *
 * 测试结果: 10个client，server端fork10个子进程。
 * 单CPU：load：13
 *
 * @author davidyanxw
 * @date 2018.04.27
 */

include "vendor/helper.php";

// 开启异步信号
if (function_exists('pcntl_async_signals')) {
    // for php 7.1
    pcntl_async_signals(true);
} else {
    // for php 4.3.0+ (up to 7.0)
    declare(ticks = 1);
}

// 脚本总超时
set_time_limit(0);
// 内存限制
ini_set('memory_limit', '64M');

//创建服务端的socket套接流,net协议为IPv4，protocol协议为TCP
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

/*绑定接收的套接流主机和端口,与客户端相对应*/
if (socket_bind($socket, '127.0.0.1', 8801) == false) {
    echo 'server bind fail:' . socket_strerror(socket_last_error());
    /*这里的127.0.0.1是在本地主机测试，你如果有多台电脑，可以写IP地址*/
}


//监听套接流
if (socket_listen($socket, 4) == false) {
    echo 'server listen fail:' . socket_strerror(socket_last_error());
}

$len = 100;
$len_header = 4;
$pid = posix_getpid();

// 忽略子进程信号
pcntl_signal(SIGCHLD, SIG_IGN);

//让服务器无限获取客户端传过来的信息
while (true) {
    $file_log = "/tmp/server.log.$pid.".date("Ymd");

    /**
     * 接收client的连接, 并生成通信的socket($accept_resource)
     * 后续的通信(读/写)都是基于该socket
     */
    $accept_resource = socket_accept($socket);
    if($accept_resource === false) {
        redirectIO("accept connection failed".PHP_EOL, false, $file_log);
        continue;
    }
    // 读写超时时间:0.8s
    socket_set_option($accept_resource, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 0, "usec" => 800000));
    socket_set_option($accept_resource, SOL_SOCKET, SO_SNDTIMEO, array("sec" => 0, "usec" => 800000));

    $pid = pcntl_fork();
    if($pid == -1) {
        redirectIO("pcntl fork fail!".PHP_EOL, false, $file_log);
        @socket_shutdown($accept_resource);
        socket_close($accept_resource);
    }
    elseif($pid) {
        // process parent
        // wait child process
        pcntl_wait($status, WNOHANG);
    }
    else {
        $pid_son = posix_getpid();  // server子进程pid
        $file_log = "/tmp/server.log.$pid_son.".date("Ymd");
        while(true) {
            // process child
            redirectIO("server: start read ".PHP_EOL, false, $file_log);
            /*读取客户端传过来的资源，并转化为字符串*/
            $string = biz_read($accept_resource, $len_header);
            if($string === false) {
                redirectIO(errorMsg(), false, $file_log);
                break;
            }
            else {
                $string = trim($string);
                $io_msg =  'server receive is :' . microtime(true) . '[' . $string . ']' . PHP_EOL;//PHP_EOL为php的换行预定义常量
                redirectIO($io_msg, false, $file_log);
            }

            redirectIO("server: start write ".PHP_EOL, false, $file_log);
            // 向服务端写入字符串信息
            $ori_client = "Hello client!content:abc" . PHP_EOL . "def" . PHP_EOL . "ghi" . PHP_EOL . "jkl" . randomkeys(5);
            $sent = biz_write($accept_resource, $ori_client);
            if ($sent === false) {
                if(in_array(socket_last_error(), [SOCKET_EPIPE, SOCKET_ECONNRESET])) {
                    redirectIO(errorMsg(), false, $file_log);
                    break;
                }
                $io_msg = 'server write fail|'.socket_last_error().'|'.(microtime(true)-$time_start).'|' . socket_strerror(socket_last_error()).PHP_EOL;
                redirectIO($io_msg, false, $file_log);
            }
            else {
                redirectIO("server write sucess,msg:[$ori_client]".PHP_EOL, false, $file_log);
            }
        }
        @socket_shutdown($accept_resource);
        socket_close($accept_resource);
        exit(0);    // 子进程通讯结束
    }

} ;
// 先shutdown，后close
@socket_shutdown($socket);
socket_close($socket);




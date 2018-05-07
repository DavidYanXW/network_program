<?php
/**
 *
 * socket server
 * 基于php socket函数族
 * IO模型：同步阻塞
 * 粘包处理：自定义包头，包头内容是包体长度
 * 连接数：1个socket连接
 *
 * 测试目标：持续通讯24+hour
 * 测试结果：
 *
 * @author davidyanxw
 * @date 2018.04.27
 */

include "vendor/helper.php";

// 脚本总超时
set_time_limit(0);
// 内存限制
ini_set('memory_limit', '64M');

//创建服务端的socket套接流,net协议为IPv4，protocol协议为TCP
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

// reuse address
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

/*绑定接收的套接流主机和端口,与客户端相对应*/
if (socket_bind($socket, '127.0.0.1', 8801) == false) {
    echo 'server bind fail:' . socket_strerror(socket_last_error());
}

//监听套接流
if (socket_listen($socket, 4) == false) {
    echo 'server listen fail:' . socket_strerror(socket_last_error());
}

$len = 100;
$len_header = 4;

/**
 * 接收client的连接, 并生成通信的socket($accept_resource)
 * 后续的通信(读/写)都是基于该socket
 */
$accept_resource = socket_accept($socket);
if($accept_resource === false) {
    echo "server accept connection failed".PHP_EOL;
    exit;
}
// 读写超时时间:0.8s
socket_set_option($accept_resource, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 0, "usec" => 800000));
socket_set_option($accept_resource, SOL_SOCKET, SO_SNDTIMEO, array("sec" => 0, "usec" => 800000));


//让服务器无限获取客户端传过来的信息
while (true) {
    $file_log = "/tmp/server.log.".date("Ymd");

    /*读取客户端传过来的资源，并转化为字符串*/
    $string = biz_read($accept_resource, $len_header);
    if($string === false) {
        echo errorMsg();
        break;
    }
    else {
        $string = trim($string);
        echo 'server receive success,msg:['.$string.'],time:' . microtime(true) . PHP_EOL;
    }

    // 向服务端写入字符串信息
    echo "server: start write ".PHP_EOL;
    $ori_client = "hello client!content:abc" . PHP_EOL . "def" . PHP_EOL . "ghi" . PHP_EOL . "jkl" . randomkeys(5);
    $sent = biz_write($accept_resource, $ori_client);
    if ($sent === false) {
        if(in_array(socket_last_error(), [SOCKET_EPIPE, SOCKET_ECONNRESET])) {
            echo errorMsg();
            break;
        }
        echo 'server: fail to write|'.socket_last_error().'|'.(microtime(true)-$time_start).'|' . socket_strerror(socket_last_error()).PHP_EOL;
    }
    else {
        echo 'server write success,msg:['.$ori_client.'],time:' . microtime(true).PHP_EOL;
    }
} ;
// 先shutdown，后close
@socket_shutdown($accept_resource);
socket_close($accept_resource);

@socket_shutdown($socket);
socket_close($socket);


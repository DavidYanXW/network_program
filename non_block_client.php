<?php
/**
 *
 * socket client
 * 基于php socket函数族
 * IO模型：同步非阻塞
 * 粘包处理：自定义包头，包头内容是包体长度
 *
 *
 * @author davidyanxw
 * @date 2018.04.27
 */

include "vendor/helper.php";

// 脚本总超时
set_time_limit(0);
// 内存限制
ini_set('memory_limit', '64M');

$time_start = microtime(true);

//创建一个socket套接流
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
/****************
 * 设置socket连接选项
 *************/
//接收套接流的最大超时时间(800ms)，后面是微秒单位超时时间，设置为零，表示不设置超时
//发送套接流的最大超时时间(800ms)
socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 0, "usec" => 800000));
socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array("sec" => 0, "usec" => 800000));

// non block
socket_set_nonblock($socket);

$len = 100;
$len_header = 4;
$pid = posix_getpid();

//连接服务端的套接流，这一步就是使客户端与服务器端的套接流建立联系
$connect_success = true;
while(@socket_connect($socket, '127.0.0.1', 8801) === false) {
    if(in_array(socket_last_error(), [SOCKET_EINPROGRESS, SOCKET_EALREADY, SOCKET_EWOULDBLOCK])) {
        // 处理中，状态未知
    }
    elseif(socket_last_error() == SOCKET_EISCONN) {
        // 已连接
        break;
    }
    elseif(socket_last_error() == SOCKET_ECONNREFUSED ) {
        $connect_success = false;
        break;
    }
    else {
        // 失败
        $connect_success = false;
        break;
    }
    usleep(1000);
}
if ($connect_success === false) {
    echo 'connect fail massege:['.socket_last_error().']' . socket_strerror(socket_last_error()).PHP_EOL;
} else {
    while(1){
        $file_log = "/tmp/client.log.$pid.".date("Ymd");

        //向服务端写入字符串信息
        redirectIO("client: start write ".PHP_EOL, false, $file_log);
        $ori_cli = 'Hello, socket!'.randomkeys(8);
        $sent = biz_write($socket, $ori_cli);
        if ($sent === false) {
            if(in_array(socket_last_error(), [SOCKET_EPIPE, SOCKET_ECONNRESET])) {
                redirectIO( errorMsg() , false, $file_log);
                break;
            }
            $io_msg = 'client write fail|'.socket_last_error().'|'.(microtime(true)-$time_start).'|' . socket_strerror(socket_last_error()).PHP_EOL;
            redirectIO($io_msg, false, $file_log);
        }
        else{
            $io_msg =  'client write success['.$ori_cli.']' . microtime(true).PHP_EOL;
            redirectIO($io_msg, false, $file_log);
        }

        //读取服务端返回来的套接流信息
        redirectIO( "client: start read ".PHP_EOL, false, $file_log) ;
        $string = biz_read($socket, $len_header);
        if($string === false) {
            redirectIO( errorMsg() , false, $file_log);
            break;
        }
        else {
            $io_msg = 'client receive success:' . microtime(true)."[".$string."]".PHP_EOL;
            redirectIO($io_msg, false, $file_log);
        }
    }

}
@socket_shutdown($socket);
socket_close($socket);//工作完毕，关闭套接流



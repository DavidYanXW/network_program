<?php
/**
 *
 * socket client
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

$time_start = microtime(true);

//创建一个socket套接流
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
/****************
* 设置socket连接选项
*************/
//接收套接流的最大超时时间(800ms)，后面是微秒单位超时时间，设置为零，表示不设置超时
socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 0, "usec" => 800000));
//发送套接流的最大超时时间(800ms)
socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array("sec" => 0, "usec" => 800000));


$len = 100;
$len_header = 4;

//连接服务端的套接流，这一步就是使客户端与服务器端的套接流建立联系
if (socket_connect($socket, '127.0.0.1', 8801) == false) {
    echo 'connect fail massege:' . socket_strerror(socket_last_error());
} else {
    while(1){
        $file_log = "/tmp/client.log.".date("Ymd");

        //向服务端写入字符串信息
        echo "client: start write ".PHP_EOL;
        $ori_cli = 'Hello server!'.randomkeys(8);
        $sent = biz_write($socket, $ori_cli);
        if ($sent === false) {
            if(in_array(socket_last_error(), [SOCKET_EPIPE, SOCKET_ECONNRESET])) {
                echo "client socket error:".socket_last_error().",error msg:".socket_strerror(socket_last_error()).PHP_EOL;
                break;
            }
            echo 'client write fail,socket:'.socket_last_error().',socket error msg:'.socket_strerror(socket_last_error()).',time:'.microtime(true) .PHP_EOL;
        }
        else{
            echo 'client write success,msg:['.$ori_cli.'],time:' . microtime(true).PHP_EOL;
        }

        //读取服务端返回来的套接流信息
        echo "start read ".PHP_EOL;
        $string = biz_read($socket, $len_header);
        if($string === false) {
            echo errorMsg();
            break;
        }
        else {
            echo 'client receive success,msg:['.$string.'],time:' . microtime(true).PHP_EOL;
        }
    }
}

@socket_shutdown($socket);
socket_close($socket);//工作完毕，关闭套接流






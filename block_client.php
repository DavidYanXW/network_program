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

// 脚本总超时
set_time_limit(0);
// 内存限制
ini_set('memory_limit', '512M');

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

        $ori_cli = 'Hello server!'.randomkeys(8);
        $header = pack("I*", strlen($ori_cli));
        $message_write = $header.$ori_cli;

        //向服务端写入字符串信息
        $sent = reliable_write($socket, $message_write );
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
        $string_header = reliable_read($socket, $len_header);
        if($string_header === false) {
            echo "client socket error:".socket_last_error().",error msg:".socket_strerror(socket_last_error()).PHP_EOL;
            break;
        }
        list(,$len) = unpack("I", $string_header);
        $callback = reliable_read($socket, $len);
        if($callback === false) {
            echo "client socket error:".socket_last_error().",error msg:".socket_strerror(socket_last_error()).PHP_EOL;
            continue;
        }
        else {
//            file_put_contents($file_log, "msg from server:[".$callback."]".PHP_EOL, FILE_APPEND);
            echo 'client receive success,msg:['.trim($callback).'],time:' . microtime(true).PHP_EOL;
        }
    }

}
@socket_shutdown($socket);
socket_close($socket);//工作完毕，关闭套接流



/**
 * 可靠写
 * @param $socket 句柄
 * @param $st 消息
 * @return bool
 */
function reliable_write($socket, $st) {
    $length = strlen($st);

    while (true) {
        $sent = socket_write($socket, $st, $length);

        if ($sent === false) {
            // network error
            return false;
        }

        // Check if the entire message has been sented
        if ($sent < $length) {
            // If not sent the entire message.
            // Get the part of the message that has not yet been sented as message
            $st = substr($st, $sent);
            // Get the length of the not sented part
            $length -= $sent;
        } else {
            // write sucess
            return true;
        }
    }
}

/**
 * 可靠读
 * socket关闭，特殊情形：中断，超时
 * @param $socket
 * @param $length
 * @return bool|string
 */
function reliable_read($socket, $length) {
    $str_read = "";

    while (true) {
        $len_read = socket_recv($socket, $have_read, $length, 0);
        // socket断开
        if($len_read === 0) {
            // 处理中断, 处理超时
            if(in_array(socket_last_error($socket) , [SOCKET_EINTR, SOCKET_EAGAIN])) {
                continue;
            }
            return false;
        }

        $str_read .= $have_read;
        if ($len_read < $length) {
            $length -= $len_read;
        } else {
            //
            return $str_read;
        }
    }
}

/**
 * 生成php随机串
 * @param $length
 * @return string
 */
function randomkeys($length){
    $output='';
    for ($a = 0; $a<$length; $a++) {
        $output .= chr(mt_rand(33, 126));
    }
    return $output;
}


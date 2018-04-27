<?php
/**
 *
 * socket client
 * 基于php socket函数族
 *
 * @author davidyanxw
 * @date 2018.04.27
 */
set_time_limit(3);
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

        $ori_cli = 'Hello, socket!'.randomkeys(8);
        $header = pack("I*", strlen($ori_cli));
        $message_write = $header.$ori_cli;

        //向服务端写入字符串信息
        $sent = reliable_write($socket, $message_write );

        if ($sent === false) {
            if(in_array(socket_last_error(), [SOCKET_EPIPE, SOCKET_ECONNRESET])) {
                echo "socket error:".socket_last_error().",error msg:".socket_strerror(socket_last_error()).PHP_EOL;
                break;
            }
            echo 'fail to write|'.socket_last_error().'|'.(microtime(true)-$time_start).'|' . socket_strerror(socket_last_error()).PHP_EOL;
        }
        else{
            echo 'client write success' . microtime(true).PHP_EOL;
            //读取服务端返回来的套接流信息
        }

        echo "start read ".PHP_EOL;
//        var_dump(demo($socket));
        $string_header = reliable_read($socket, $len_header);
        if($string_header === false) {
            echo "socket error:".socket_last_error().",error msg:".socket_strerror(socket_last_error()).PHP_EOL;
            if(in_array(socket_last_error(), [SOCKET_EPIPE, SOCKET_ECONNRESET])) {
                echo "socket error:".socket_last_error().",error msg:".socket_strerror(socket_last_error()).PHP_EOL;
                break;
            }
            if(in_array(socket_last_error(), [SOCKET_EAGAIN])) {
                echo "socket error:".socket_last_error().",error msg:".socket_strerror(socket_last_error()).PHP_EOL;
                break;
            }
            continue;
        }
        list(,$len) = unpack("I", $string_header);
        $callback = reliable_read($socket, $len);
        if($callback === false) {
            if(in_array(socket_last_error(), [SOCKET_EPIPE, SOCKET_ECONNRESET])) {
                echo "socket error:".socket_last_error().",error msg:".socket_strerror(socket_last_error()).PHP_EOL;
                break;
            }
            if(in_array(socket_last_error(), [SOCKET_EAGAIN])) {
                echo "socket error:".socket_last_error().",error msg:".socket_strerror(socket_last_error()).PHP_EOL;
                break;
            }
            continue;
        }
        else {
//            file_put_contents($file_log, "msg from server:[".$callback."]".PHP_EOL, FILE_APPEND);
            echo 'server return message is:' . microtime(true)."[".$callback."]".PHP_EOL;
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
            // @todo: network error
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
 * @param $socket
 * @param $length
 * @return bool|string
 */
function reliable_read($socket, $length) {
    $str_read = "";

    while (true) {
        $have_read = socket_read($socket, $length);
        $str_read .= $have_read;

        if ($have_read === false) {
            // @todo: network error
            return false;
        }

        if (strlen($have_read) < $length) {
            $length -= strlen($have_read);
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


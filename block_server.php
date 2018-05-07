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

// 脚本总超时
set_time_limit(0);
// 内存限制
ini_set('memory_limit', '512M');

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
    $string_header = reliable_read($accept_resource, $len_header);
    if ($string_header === false) {
        echo "server socket error:" . socket_last_error() . ",error msg:" . socket_strerror(socket_last_error()).",time:".microtime(true) . PHP_EOL;
        break;
    }

    list(, $len) = unpack("I", $string_header);
    $string = reliable_read($accept_resource, $len);
    if ($string === false) {
        echo "server socket error:" . socket_last_error() . ",error msg:" . socket_strerror(socket_last_error()).",time:".microtime(true) . PHP_EOL;
    } else {
//        file_put_contents($file_log, $string.PHP_EOL, FILE_APPEND);
        $string = trim($string);
        echo 'server receive success,msg:['.$string.'],time:' . microtime(true) . PHP_EOL;
    }

    $ori_client = "hello client!content:abc" . PHP_EOL . "def" . PHP_EOL . "ghi" . PHP_EOL . "jkl" . randomkeys(5);
    $header = pack("I*", strlen($ori_client));
    $return_client = $header . $ori_client;
    $ret_write = reliable_write($accept_resource, $return_client);
    if ($ret_write === false) {
        if (in_array(socket_last_error(), [SOCKET_EPIPE, SOCKET_ECONNRESET])) {
            echo "server socket error:" . socket_last_error() . ",error msg:" . socket_strerror(socket_last_error()) . PHP_EOL;
            break;
        }
        echo 'server write fail,socket:'.socket_last_error().',socket error msg:'.socket_strerror(socket_last_error()).',time:'.microtime(true) .PHP_EOL;
        continue;
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




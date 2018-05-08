<?php
/**
 * 通用帮助函数
 * Created by PhpStorm.
 * User: yanxiaowei
 * Date: 2018/5/7
 * Time: 21:11
 */


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
        $len_read = @socket_recv($socket, $have_read, $length, 0);
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
 * 业务逻辑读
 * @param $accept_resource
 * @param $len_header
 * @return bool|string
 */
function biz_read($accept_resource, $len_header) {
    $string_header = reliable_read($accept_resource, $len_header);
    if ($string_header === false) {
        // socket关闭
        return false;
    }

    list(, $len) = unpack("I", $string_header);
    if($len <= 0) {
        return false;
    }

    $string = reliable_read($accept_resource, $len);
    if ($string === false) {
        // socket关闭
        return false;
    } else {
        $string = trim($string);
        return $string;
    }
}

/**
 * 业务逻辑：写
 * @param $socket
 * @param $msg
 * @return bool
 */
function biz_write($socket, $msg) {
    $header = pack("I*", strlen($msg));
    $message_write = $header.$msg;

    //向服务端写入字符串信息
    $sent = reliable_write($socket, $message_write );

    if ($sent === false) {
        if(in_array(socket_last_error(), [SOCKET_EAGAIN])) {
            // when SOCKET_EAGAIN, retry later
            usleep(500);
            return biz_write($socket, $msg);
        }
        return false;
    }
    else{
        return true;
    }
}


/**
 * 错误信息
 * @return string
 */
function errorMsg() {
    return "socket error:".socket_last_error().",error msg:".socket_strerror(socket_last_error()).PHP_EOL;
}

/**
 * 方便调试，输出到文件
 * 重定向输出
 * @param $msg
 */
function redirectIO($msg, $echo=true, $log=false) {
    if($echo) {
        echo $msg;
    }
    if($log) {
        file_put_contents($log, $msg, FILE_APPEND);
    }
}

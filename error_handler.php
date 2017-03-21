<?php

$scriptStartTime=microtime();
//error_reporting(0);
ini_set('display_errors',false);
set_error_handler('errorHandler');
set_exception_handler('exceptionHandler');
register_shutdown_function('shutdownHandler');

/**
 * 脚本结束时的处理函数
 * @Author   WindGreen<yqfwind@163.com>
 * @DateTime 2017-03-20T13:29:22+0800
 * @return   [type]                     [description]
 */
function shutdownHandler()
{
    //处理未处理的错误
    $error = error_get_last();
    if(is_array($error)){
        $message=sprintf('SHUTDOWN_HANDLE %s[%d] %s:%d %s',
                get_errno_name($error['type']),
                isset($error['type']) ? $error['type'] : '',
                isset($error['file']) ? $error['file'] : '',
                isset($error['line']) ? $error['line'] : '',
                isset($error['message']) ? $error['message'] : '');
        exception_and_error_log($message,get_errno_type($error['type']));
    }
    //计算脚本执行时间
    if(isset($GLOBALS['scriptStartTime'])){
        $scriptStartTime=explode(' ',$GLOBALS['scriptStartTime']);
        $scriptEndTime=explode(' ',microtime());
        $time=intval($scriptEndTime[1])-intval($scriptStartTime[1])+floatval($scriptEndTime[0])-floatval($scriptStartTime[0]);
        $msg=sprintf('[Finished in %fs] %s%s%s',
            $time,
            /*empty($_SERVER['HTTP_HOST']) ? '' :*/ $_SERVER['HTTP_HOST'],
            $_SERVER['PHP_SELF'],
            empty($_SERVER['QUERY_STRING']) ? '' : '?'.$_SERVER['QUERY_STRING']);
        exception_and_error_log($msg,'DEBUG');
    }    
}

/**
 * 未处理的异常处理
 * @Author   WindGreen<yqfwind@163.com>
 * @DateTime 2017-03-20T13:30:01+0800
 * @param    [type]                     $exception [description]
 * @return   [type]                                [description]
 */
function exceptionHandler($exception)
{
    if($exception){
        $message=sprintf('EXCEPTION_HANDLE %s:%d %s',
                $exception->getFile(),
                $exception->getLine(),
                $exception->getMessage()
            );
        exception_and_error_log($message,'ERROR');
    }
}

/**
 * 错误处理，包括notice,warning和error级别的
 * 参考 http://php.net/manual/zh/function.set-error-handler.php
 * 这个函数可以被trigger_error触发
 * @param    [type]                     $errno      [description]
 * @param    [type]                     $errstr     [description]
 * @param    [type]                     $errfile    [description]
 * @param    [type]                     $errline    [description]
 * @param    [type]                     $errcontext [description]
 * @return   [type]                                 [description]
 */
function errorHandler($errno,$errstr,$errfile,$errline,$errcontext=null)
{
    //ref http://php.net/manual/zh/function.set-error-handler.php
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting, so let it fall
        // through to the standard PHP error handler
        return false;
    }
    $format='%s [%s] %s:%s %s';
    switch ($errno) {
        case E_USER_ERROR:
            $message=sprintf($format,'E_USER_ERROR',$errno,$errfile,$errline,$errstr);
            exception_and_error_log($message,get_errno_type($errno));
            exit(1);
            break;
/*
        case E_USER_WARNING:
            $message=sprintf($format,'USER_WARNING',$errno,$errfile,$errline,$errstr);
            exception_and_error_log($message,'WARNING');
            break;

        case E_USER_NOTICE:
            $message=sprintf($format,'USER_NOTICE',$errno,$errfile,$errline,$errstr);
            exception_and_error_log($message,'NOTICE');
            break;
*/
        default:
            //return false;
            $message=sprintf($format,get_errno_name($errno),$errno,$errfile,$errline,$errstr);
            exception_and_error_log($message,get_errno_type($errno));
            break;
    }

    /* Don't execute PHP internal error handler */
    return true;
}

/**
 * 自定义错误日志处理
 * @Author   WindGreen<yqfwind@163.com>
 * @DateTime 2017-03-20T13:32:12+0800
 * @param    [type]                     $message [description]
 * @param    string                     $level   [description]
 * @return   [type]                              [description]
 */
function exception_and_error_log($message,$level='INFO')
{
    echo $level,' ',$message,'<br>';
}

/**
 * 通过错误码获取名称
 * @Author   WindGreen<yqfwind@163.com>
 * @DateTime 2017-03-20T13:33:41+0800
 * @param    [type]                     $errno [description]
 * @return   [type]                            [description]
 */
function get_errno_name($errno)
{
    $error=[
        E_ERROR             =>'E_ERROR',            //1
        E_WARNING           =>'E_WARNING',          //2
        E_PARSE             =>'E_PARSE',            //4
        E_NOTICE            =>'E_NOTICE',           //8
        E_CORE_ERROR        =>'E_CORE_ERROR',       //16
        E_CORE_WARNING      =>'E_CORE_WARNING',     //32
        E_COMPILE_ERROR     =>'E_COMPILE_ERROR',    //64
        E_COMPILE_WARNING   =>'E_COMPILE_WARNING',  //128
        E_USER_ERROR        =>'E_USER_ERROR',       //256
        E_USER_WARNING      =>'E_USER_WARNING',     //512
        E_USER_NOTICE       =>'E_USER_NOTICE',      //1024
        E_STRICT            =>'E_STRICT',           //2048
        E_RECOVERABLE_ERROR =>'E_RECOVERABLE_ERROR',//4096
        E_DEPRECATED        =>'E_DEPRECATED',       //8192
        E_USER_DEPRECATED   =>'E_USER_DEPRECATED',  //16384
        //E_ALL               =>'E_ALL'             //32767
    ];
    if(isset($error[$errno]))
        return $error[$errno];
    else return '';
}

/**
 * 通过错误码获取自定义错误类型
 * @Author   WindGreen<yqfwind@163.com>
 * @DateTime 2017-03-20T13:33:54+0800
 * @param    [type]                     $errno [description]
 * @return   [type]                            [description]
 */
function get_errno_type($errno)
{
    $error=get_errno_name($errno);
    $type=substr($error, strrpos($error,'_')+1);
    if(in_array($type,['ERROR','WARNING','NOTICE']))
        return $type;
    else return 'INFO';
}

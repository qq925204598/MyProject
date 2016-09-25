<?php
/**
 * Created by PhpStorm.
 * User: henry
 * Date: 15-6-25
 * Time: 下午10:19
 */
date_default_timezone_set('PRC');

$serv = swoole_server_create('127.0.0.1', 3302, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);//端口3304
swoole_server_set($serv, array(
    'worker_num' => 2,      //worker线程的数量
    'task_worker_num' => 1, //MySQL连接的数量
    'max_request' => 2000,  //2000次请求后，结束运行
    'daemonize' => 1,       //守护进程
    'log_file' => '/webdata/seaslog/swoole/swoole.log',
));

function my_onReceive($serv, $fd, $from_id, $data){
    //执行查询
    $result = $serv->taskwait($data);
    if ($result !== false) {
        swoole_server_send($serv, $fd, $result);
        return;
    } else {
        swoole_server_send($serv, $fd, "Error. Task timeout\n");
    }
}


function my_onTask($serv, $task_id, $from_id, $sql){
    static $link = NULL;
    if ($link == NULL) {
        $link = mysqli_connect('rds00q07toigm31touz1.mysql.rds.aliyuncs.com', 'ticket', 'ticket123', 'ticket_db');
        //localhost=>UNIX Socket , IP地址=>TCP/IP
    }
    $result = $link->query($sql);

    if ($link->errno == 2006 || $link->errno == 2013){
        if (!@$link->ping()){
            //挂
            $link->close();
            $link = mysqli_connect('rds00q07toigm31touz1.mysql.rds.aliyuncs.com', 'ticket', 'ticket123', 'ticket_db');
            $result = $link->query($sql);
        }
    }

    if ($result === false) {
        swoole_server_finish($serv, 'b:0;');//语句运行失败，这是serialize后的false，下同理
        return;
    }
    if ($result === true){
        swoole_server_finish($serv, 'b:1;');//写入操作成功
        return;
    }
    $data = $result->fetch_all(MYSQLI_ASSOC);
    swoole_server_finish($serv, serialize($data));
}
function my_onFinish($serv, $data){
    //这次实验就没有写东西了
    //但是必须有这个函数定义
    //其实可以写日志什么的吧
}
swoole_server_handler($serv, 'onReceive', 'my_onReceive');
swoole_server_handler($serv, 'onTask', 'my_onTask');
swoole_server_handler($serv, 'onFinish', 'my_onFinish');
//上面是设置回调函数
swoole_server_start($serv);
//swoole_event_wait();//实验环境是PHP5.3，所以需要这个函数进行事件轮询；5.4+就不需要了
?>

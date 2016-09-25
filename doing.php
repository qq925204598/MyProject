<?php
/**
 * Created by PhpStorm.
 * User: henry
 * Date: 16-3-24
 * Time: 下午11:02
 */

require_once '../config/config.inc.php';

$data = new swoole_client(SWOOLE_SOCK_TCP,SWOOLE_SOCK_SYNC);
$data->connect(SWOOLE_HOST,SWOOLE_PORT);

while (true){
    $data->send("SELECT `ID` FROM `user_information` WHERE `ID`='1'");
    $data->recv();
    sleep(60);
}
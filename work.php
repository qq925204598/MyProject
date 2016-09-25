<?php
/**
 * Created by PhpStorm.
 * User: henry
 * Date: 16-4-4
 * Time: 下午3:59
 */
//REDIS地址
define('REDIS_HOST','127.0.0.1');
//REDIS端口
define('REDIS_PORT',6379);

define('SWOOLE_HOST','127.0.0.1');
define('SWOOLE_PORT','3302');

class cronTab {

    private $redisLive;
    private $sqlLive;

    const WAIT_UPDATE_PAY_STATUS = 'wait_update_p_s';

    /*
     * 构造函数
     */
    public function __construct(){
        //建立redis链接
        $this->redisLive = new Redis();
        $this->redisLive->connect(REDIS_HOST,REDIS_PORT);

        SeasLog::setLogger('shiyilu.cc');
    }

    /*
     * 获取list长度
     */
    private function getLength(){
        return $this->redisLive->lSize(self::WAIT_UPDATE_PAY_STATUS);
    }

    /*
     * 执行函数
     */
    public function doWork(){
        $listLength = intval($this->getLength());
        if ($listLength == 0){
            //列表无元素
            return false;
        }

        //创建swoole链接
        $this->sqlLive = new swoole_client(SWOOLE_SOCK_TCP,SWOOLE_SOCK_SYNC);
        $this->sqlLive->connect(SWOOLE_HOST,SWOOLE_PORT);

        //遍历元素
        for ($i=0;$i<$listLength;$i++){
            $this->updatePayStatus($this->redisLive->rPop(self::WAIT_UPDATE_PAY_STATUS));
        }

        return true;
    }

    /*
     * 对于更新失败的语句,再次处理更新操作
     */
    private function updatePayStatus($orderNum){
        $this->sqlLive->send("UPDATE `user_information` SET `pay`='1' WHERE `ordernumber` = '{$orderNum}'");
        $res = unserialize($this->sqlLive->recv());
        if (!$res){
            if(!$this->redisLive->lPush(self::WAIT_UPDATE_PAY_STATUS,$orderNum)){
                SeasLog::critical('Have an order error that the operate update failed! The order number is'.$orderNum);
            }
        }
    }
}

//执行操作
$workObj = new cronTab();
$workObj->doWork();
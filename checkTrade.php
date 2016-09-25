<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2016/5/9
 * Time: 21:24
 */
require_once '../config/config.inc.php';
class checkTrade{
    private $redisLive;
    private $sqlLive;

    const WAIT_CHECK_PAY_STATUS = 'check_update_p_s';

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
        return $this->redisLive->lSize(self::WAIT_CHECK_PAY_STATUS);
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
            $this->catPayStatus($this->redisLive->rPop(self::WAIT_CHECK_PAY_STATUS));
        }

        return true;
    }
    /*
     * 查询数据库用户的付费信息是否正常，如果不正常做调整
     */
    private function catPayStatus($out_trade_no){
        $this->sqlLive->send("SELECT `pay` FROM `user_information` WHERE `ordernumber` = '{$out_trade_no}'");
        $res = unserialize($this->sqlLive->recv());
        if ($res[0]['pay'] == 0){
            $this->sqlLive->send("UPDATE `user_information` SET `pay`='1' WHERE `ordernumber` = '{$out_trade_no}'");
            $result = unserialize($this->sqlLive->recv());
            if (!$result){
                $this->redisLive->lPush('wait_update_p_s',$out_trade_no);
                }
            }
        }
}
//执行操作
$checkObj = new checkTrade();
while (true){
    $checkObj->doWork();
    sleep(2);
}


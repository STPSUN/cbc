<?php

namespace web\api\controller;

use think\Lang;
use web\api\service\NodeService;

class Crontab extends \web\common\controller\Controller {


    protected function _initialize() {
        
    }

    /**
     * 定时器访问
     * 超过30分钟未付款则取消订单
     */
    public function cancleOrder(){
        $tradingM = new \addons\member\model\Trading();
        $map['type'] = 1;
        $map['status'] = 0;
        $map['update_time'] = ['lt',date('Y-m-d H:i:s',(time()-30*60))];
        $list = $tradingM->where($map)->select();
        if(!$list) return $this->failJSON('no trading list');
        foreach ($list as $key => $value) {
            $data = $value;
            $data['to_user_id'] = 0;
            $data['type'] = 0;
            $data['update_time']=NOW_DATETIME;
            $res = $tradingM->save($data);
        }
        if($res) $this->successJSON('update success');
        else $this->failJSON('update failed');
    }

    /**
     * 节点释放
     */
    public function nodeRelease()
    {
        $nodeS = new NodeService();
        $nodeS->nodeRelease();
    }

    /**
     * 更新余额中节点日释放值
     */
    public function updateBalanceReleaseNum()
    {
        $nodeS = new NodeService();
        $nodeS->updateBalanceReleaseNum();
    }

    /**
     * 输出错误JSON信息。
     * @param type $message     
     */
    protected function failJSON($message) {
        $message = lang($message);
        $jsonData = array('success' => false, 'message' => $message);
        $json = json_encode($jsonData, true);
        echo $json;
        exit;
    }

    /**
     * 输出成功JSON信息
     * @param type $data
     */
    protected function successJSON($data = NULL, $msg = "success") {
        if (is_array($data) || is_object($data)) {
            $data = $this->_setDataLang($data);
        }
        $jsonData = array('success' => true, 'data' => $data, 'message' => $msg);
        $json = json_encode($jsonData, 1);
        echo $json;
        exit;
    }

   
}

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
     * 定时释放理财奖金
     */
    public function auto_receive(){
        $finaM = new \addons\member\model\Financial();
        $map['end_at'] = ['lt',date('Y-m-d H:i:s')];
        $list = $finaM->where($map)->select();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        if(empty($list)) return $this->failJSON('no financial list');
        foreach ($list as $key => $value) {
            $info = $value;
            $user_id = $info['user_id'];
            $financial_id = $info['id'];
            $balanceM->startTrans();
            $type = 12;
            $coin_id = 4;
            $amount = $info['interset']+$info['amount'];
            $userAsset = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
            if(!$userAsset){
                $balanceM->rollback();
                return $this->failJSON(lang('COMMON_ADD_AMOUNT_WRONG'));
            }
            $in_record_id = $recordM->addRecord($user_id, $amount, $userAsset['before_amount'], $userAsset['amount'], $coin_id, $type,1, $user_id,'用户理财');
            if(empty($in_record_id)){
                $balanceM->rollback();
                return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
            }

            if($info['belong']==0){
                $AwardService = new \web\api\service\AwardService();
                $fee_num = $info['amount']/7*3;
                $res = $AwardService->tradingReward($fee_num,$user_id);
                //计算奖金
                if(!$res){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_REWARD_FAIL'));
                }
            }
            $data = [
                    'status'        =>1,
                    'update_at'     =>date('Y-m-d H:i:s'),
            ];
            $res = $finaM->where(['id'=>$financial_id])->update($data);
            if(!$res){
                $balanceM->rollback();
                return $this->failJSON(lang('INVESTMENT_ADD_FAIL'));
            }
            $balanceM->commit();
            return $this->successJSON();
        }
            
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

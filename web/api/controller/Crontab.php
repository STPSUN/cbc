<?php

namespace web\api\controller;

use think\Lang;
use think\Log;
use web\api\model\AwardIssue;
use web\api\model\MemberNodeIncome;
use web\api\service\AwardService;
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
     * 定时恢复挂卖金额
     * 恢复每日总量
     * 更新用户额度
     */
    public function auto_quota(){
        $TransferM = new \addons\member\model\Transfer();
        $sysM = new \web\common\model\sys\SysParameterModel();
        $total = $sysM->getValByName('total_transaction');
        $res =  $sysM->setValByName('less_total',$total);
        $page = 0;
        $list = $TransferM->limit($page,5000)->select();
        while ($list) {
            $time = date('Y-m-d H:i:s');
            $page++;
            foreach ($list as $k => $v) {
                if($v['today_quota']!=0){
                    $list[$k]['quota'] = $v['today_quota']+$v['quota'];
                    $list[$k]['today_quota'] = 0;
                    $list[$k]['today_at'] = $time;
                    $list[$k]['quota_at'] = $time;
                    $list[$k]['update_at'] = $time;
                }
            }
            $TransferM->saveAll($list);
            $list = $TransferM->limit($page*5000,5000)->select();
        }
        echo 'success';
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
        set_time_limit(0);
        $incomeM = new MemberNodeIncome();
        $is_release = $incomeM->whereTime('create_time','today')->find();
        if(!empty($is_release))
            return;

        $nodeS = new NodeService();

        $nodeS->nodeRelease();

        $nodeS->updateBalanceReleaseNum();
    }

    /**
     * 超级节点释放，奖励发放
     */
    public function superNodeAward()
    {
        set_time_limit(0);
        $awardIssue = new AwardIssue();
        $awardS = new AwardService();

        $data = $awardIssue->where('status',1)->limit(1)->select();

        $awardIssue->startTrans();
        try
        {
            foreach ($data as $v)
            {
                $awardS->tradingReward($v['amount'],$v['user_id']);
                $awardIssue->save([
                    'status' => 2,
                    'update_time' => NOW_DATETIME
                ],[
                    'id' => $v['id'],
                ]);

                Log::record('超级节点奖励释放成功：' . $v['user_id']);
            }

            $awardIssue->commit();
        }catch (\Exception $e)
        {
            $awardIssue->rollback();
            Log::record('超级节点奖励释放失败');
        }
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

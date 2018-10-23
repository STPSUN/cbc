<?php
/**
 * Created by PhpStorm.
 * User: zhuangminghan 
 * Date: 2018/10/16
 * Time: 16:48
 * 投资
 */

namespace web\api\controller;


class Investment extends ApiBase
{
    /**
     * 理财  
     * @param financial_id int
     * @param amount float 资产
     */
    public function Investment(){
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON("请登录");
        $financialM = new \web\common\model\sys\FinancialModel();
        $fina = new \addons\member\model\Financial();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        $financial_id = $this->_post('financial_id');
        $info = $financialM->getFinancial($financial_id);
        if(!$info) return $this->failJSON("找不到理财方式");
        $amount = $this->_post('amount');
        if($amount<$info['amount_limit']) return $this->failJSON("起始投资金额少于".$info['amount_limit']);
        $type = 1;
        $userAsset = $balanceM->getBalanceByType($user_id,$type);
        if($amount>$userAsset['amount'])  return $this->failJSON("你的资金少于".$amount);
        $balanceM->startTrans();
        $userAsset = $balanceM->updateBalance($user_id,$type,$amount);
        if(!$userAsset){
            $balanceM->rollback();
            return $this->failJSON("减少资金错误");
        }
        $in_record_id = $recordM->addRecord($user_id, $amount, $userAsset['before_amount'], $userAsset['amount'], $type, 4,0, $user_id,'用户理财');
        if(empty($in_record_id)){
            $balanceM->rollback();
            return $this->failJSON('更新余额失败');
        }
        $data = [
                'user_id'       =>$user_id,
                'amount'        =>$amount,
                'month_fee'     =>$info['amount_interest'],
                'financing_time'=>$info['time_length'],
                'interset'      =>$amount*$info['amount_interest']/100,
                'start_at'      =>date('Y-m-d H:i:s'),
                'end_at'        =>date('Y-m-d H:i:s',strtotime('+'.$info['time_length'].' days')),
                'update_at'     =>date('Y-m-d H:i:s'),
                'create_at'     =>date('Y-m-d H:i:s'),
        ];
        $res = $fina->add($data);
        if(!$res){
            $balanceM->rollback();
            return $this->failJSON('添加投资失败');
        }
        $balanceM->commit();
        return $this->successJSON();
    }

    /**
     * 获取理财记录
     */
    public function getFinancialList(){
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON("请登录");
        $finaM = new \addons\member\model\Financial();
        $order = 'id desc';
        $filter = 'user_id = '.$user_id;
        $list = $finaM->getDataList($this->getPageIndex(), $this->getPageSize(),$filter,'',$order);
        return $this->successJSON($list);
    }

    /**
     * 领取理财金额
     */
    public function receiveFinancial(){
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON("请登录");
        $finaM = new \addons\member\model\Financial();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        $financial_id = $this->_post('financial_id');
        $info = $finaM->getDetail($financial_id);
        if(!$info) return $this->failJSON("找不到理财订单");
        if($info['user_id']!=$user_id) return $this->failJSON("不是你的理财订单");
        if(time()<strtotime($info['end_at'])) return $this->failJSON("理财时间还没结束");
        $balanceM->startTrans();
        $type = 1;
        $amount = $info['amount'];
        $userAsset = $balanceM->updateBalance($user_id,$type,$amount,1);
        if(!$userAsset){
            $balanceM->rollback();
            return $this->failJSON("增加资金错误");
        }
        $in_record_id = $recordM->addRecord($user_id, $amount, $userAsset['before_amount'], $userAsset['amount'], $type, 4,1, $user_id,'用户理财');
        if(empty($in_record_id)){
            $balanceM->rollback();
            return $this->failJSON('更新余额失败');
        }

        $type = 4;
        $amount = $info['interset'];
        $userAsset = $balanceM->updateBalance($user_id,$type,$amount,1);
        if(!$userAsset){
            $balanceM->rollback();
            return $this->failJSON("增加资金错误");
        }
        $in_record_id = $recordM->addRecord($user_id, $amount, $userAsset['before_amount'], $userAsset['amount'], $type, 4,1, $user_id,'用户理财');
        if(empty($in_record_id)){
            $balanceM->rollback();
            return $this->failJSON('更新余额失败');
        }
        $data = [
                'status'        =>1,
                'update_at'     =>date('Y-m-d H:i:s'),
        ];
        $res = $finaM->where(['id'=>$financial_id])->update($data);
        if(!$res){
            $balanceM->rollback();
            return $this->failJSON('添加投资失败');
        }
        $balanceM->commit();
        return $this->successJSON();
    }
}
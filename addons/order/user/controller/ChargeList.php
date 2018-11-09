<?php

namespace addons\order\user\controller;

/**
 * 消费积分充值
 */
class ChargeList extends \web\user\controller\AddonUserBase {

    public function index() {
        $status = $this->_get('status');
        if ($status == '') {
            $status = 0;
        }
        $this->assign('status', $status);
        return $this->fetch();
    }

    public function loadList() {
        $keyword = $this->_get('keyword');
        $status = $this->_get('status');
        $filter = 'status=' . $status;
        if ($keyword != null) {
            $filter .= ' and user_id like \'%' . $keyword . '%\'';
        }
        $r = new \addons\order\model\chargeModel();
        $total = $r->getTotal($filter);
        $rows = $r->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }

    //审核
    public function doCheck() {
        $orderId = $this->_get('id'); //提现订单id
        $state = $this->_get('status');
        $r = new \addons\order\model\chargeModel();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        $orderData = $r->where(['id' => $orderId])->find();
        $balanceM->startTrans();
        try {
            if ($state == 1) {//提现通过，扣除用户冻结提现金额
                $userM = new \addons\member\model\MemberAccountModel();
                $MemberNodeM = new \web\api\model\MemberNode();
                $user_id = $userM->getUserByPhone($orderData['user_id']);
                //获取用户奖金信息
                $conid_id = 7;
                $amount = $orderData['amount'];
                $userAsset = $balanceM->updateBalance($user_id,$conid_id,$amount,1);
                if(!$userAsset){
                    $balanceM->rollback();
                    return $this->failJSON("增加资金错误");
                }
                $in_record_id = $recordM->addRecord($user_id, $amount, $userAsset['before_amount'], $userAsset['amount'], $conid_id, 16,1, $user_id,'系统审核通过，增加消费积分');
                if(empty($in_record_id)){
                    $balanceM->rollback();
                    return $this->failJSON('更新余额失败');
                }
                $res = $r->where(['id' => $orderId])->update(['status' => $state, 'update_at' => NOW_DATETIME]);
                if(!$res){
                    $balanceM->rollback();
                    return $this->failData('不通过失败');
                }

                $balanceM->commit();
                return $this->successData();
            } elseif ($state == 2) {
                $res = $r->where(['id' => $orderId])->update(['status' => $state, 'update_at' => NOW_DATETIME]);
                if(!$res){
                    $balanceM->rollback();
                    return $this->failData('不通过失败');
                }
                $balanceM->commit();
                return $this->successData();
            }
        } catch (\Exception $ex) {
            $balanceM->rollback();
            return $this->failData($ex->getMessage());
        }
    }


}

<?php

namespace addons\order\user\controller;

/**
 * 审核
 */
class NodeAudit extends \web\user\controller\AddonUserBase {

    public function index() {
        $status = $this->_get('status');
        if ($status == '') {
            $status = 1;
        }
        $this->assign('status', $status);
        return $this->fetch();
    }

    public function loadList() {
        $keyword = $this->_get('keyword');
        $status = $this->_get('status');
        $filter = 'status=' . $status;
        if ($keyword != null) {
            $filter .= ' and username like \'%' . $keyword . '%\'';
        }
        $r = new \web\api\model\MemberNodeApply();
        $total = $r->getTotal($filter);
        $rows = $r->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }

    //审核
    public function doCheck() {
        $orderId = $this->_get('id'); //提现订单id
        $state = $this->_get('status');
        $r = new \web\api\model\MemberNodeApply();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        $orderData = $r->where(['id' => $orderId])->find();
        $balanceM->startTrans();
        try {
            if ($state == 2) {//提现通过，扣除用户冻结提现金额
                $userM = new \addons\member\model\MemberAccountModel();
                $MemberNodeM = new \web\api\model\MemberNode();
                $user_id = $userM->getUserByPhone($orderData['username']);
                $node = new \web\api\model\Node();
                $cbc = $node->where(['type'=>7])->find();
                //获取用户奖金信息
                $type = 4;
                $balanceData = $balanceM->getBalanceByType($user_id, $type);
                $amount = $cbc['cbc_num'];
                if($cbc['cbc_num']>$balanceData['amount']){
                    $balanceM->rollback();
                    return $this->failData('用户金额少于'.$cbc['cbc_num']);
                }
                $userAsset = $balanceM->updateBalance($user_id,$type,$amount);
                if(!$userAsset){
                    $balanceM->rollback();
                    return $this->failJSON("减少资金错误");
                }
                $in_record_id = $recordM->addRecord($user_id, $amount, $userAsset['before_amount'], $userAsset['amount'], $type, 5,0, $user_id,'系统审核通过，扣除余额');
                if(empty($in_record_id)){
                    $balanceM->rollback();
                    return $this->failJSON('更新余额失败');
                }
                $res = $r->where(['id' => $orderId])->update(['status' => $state, 'update_time' => NOW_DATETIME]);
                if(!$res){
                    $balanceM->rollback();
                    return $this->failData('不通过失败');
                }
                $data = [
                    'node_id'=>$cbc['id'],
                    'node_num'=>$cbc['node_num'],
                    'user_id'=>$user_id,
                    'give_user_id'=>0,
                    'create_time'=>NOW_DATETIME,
                    'type'=>$cbc['type'],
                    'status'=>1,
                ];
                $res = $MemberNodeM->add($data);
                if(!$res){
                    $balanceM->rollback();
                    return $this->failData('不通过失败');
                }

                $balanceM->commit();
                return $this->successData();
            } elseif ($state == 3) {
                $res = $r->where(['id' => $orderId])->update(['status' => $state, 'update_time' => NOW_DATETIME]);
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

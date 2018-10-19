<?php

namespace addons\order\user\controller;

/**
 * 审核
 */
class NodeAudit extends \web\user\controller\AddonUserBase {

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
            $filter .= ' and username like \'%' . $keyword . '%\'';
        }
        $r = new \addons\order\model\WithoutModel();
        $total = $r->getTotal($filter);
        $rows = $r->getList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }

    //审核
    public function doCheck() {
        $orderId = $this->_get('id'); //提现订单id
        $state = $this->_get('status');
        $withoutM = new \addons\order\model\WithoutModel();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        $orderData = $withoutM->where(['id' => $orderId])->find();
        $balanceM->startTrans();
        try {
            //获取用户奖金信息
            $balanceData = $balanceM->getBalanceByType($orderData['user_id'], 3);
            if ($state == 1) {//提现通过，扣除用户冻结提现金额
                //更新用户余额
                $balanceM->where(['id' => $balanceData['id']])->update(['withdraw_frozen_amount' => $balanceData['withdraw_frozen_amount'] - $orderData['amount']]);
                //修改订单状态
                $withoutM->where(['id' => $orderId])->update(['status' => $state, 'update_time' => NOW_DATETIME]);
                $balanceM->commit();
                return $this->successData();
            } elseif ($state == -1) {
                //减少用户提现冻结金额
                $balanceM->where(['id' => $balanceData['id']])->update(['withdraw_frozen_amount' => $balanceData['withdraw_frozen_amount'] - $orderData['amount']]);
                //返回提现金额
                $balanceM->updateBalance($orderData['user_id'], 3, $orderData['amount'], true);
                $balanceData = $balanceM->getBalanceByType($orderData['user_id'], 3);
                //写入交易记录
                $recordM->addRecord($orderData['user_id'], $orderData['amount'], $balanceData['before_amount'], $balanceData['amount'], 3, 2, 1, 0, '返还提现金额');
                if ($orderData['rate'] > 0) {
                    $balanceM->updateBalance($orderData['user_id'], 3, $orderData['rate'], true);
                    $balanceData = $balanceM->getBalanceByType($orderData['user_id'], 3);
                    $recordM->addRecord($orderData['user_id'], $orderData['rate'], $balanceData['before_amount'], $balanceData['amount'], 3, 2, 1, 0, '返还提现手续费');
                }
                //修改订单状态
                $withoutM->where(['id' => $orderId])->update(['status' => $state, 'update_time' => NOW_DATETIME]);
                $balanceM->commit();
                return $this->successData();
            }
        } catch (\Exception $ex) {
            $balanceM->rollback();
            return $this->failData($ex->getMessage());
        }
    }


}

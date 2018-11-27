<?php

namespace addons\member\user\controller;

/**
 * 理财
 */
class Finacial extends \web\user\controller\AddonUserBase {

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
        $r = new \addons\member\model\Financial();
        $total = $r->getTotal($filter);
        $rows = $r->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }

    //审核
    public function doCheck() {
        return false;
        $orderId = $this->_get('id'); //提现订单id
        $state = $this->_get('status');
        $r = new \addons\member\model\Financial();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        $orderData = $r->where(['id' => $orderId])->find();
        try {
            if ($state == 1) {//提现通过，扣除用户冻结提现金额
                $res = $r->where(['id' => $orderId])->update(['status' => $state, 'update_at' => NOW_DATETIME]);
                if(!$res){
                    $balanceM->rollback();
                    return $this->failData('不通过失败');
                }

                return $this->successData();
            } elseif ($state == 2) {
                $res = $r->where(['id' => $orderId])->update(['status' => $state, 'update_at' => NOW_DATETIME]);
                if(!$res){
                    return $this->failData('不通过失败');
                }
                return $this->successData();
            }
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }
}
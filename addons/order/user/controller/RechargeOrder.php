<?php

namespace addons\order\user\controller;

/**
 * 用户等级
 */
class RechargeOrder extends \web\user\controller\AddonUserBase {

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
        $r = new \addons\order\model\RechargeModel();
        $total = $r->getTotal($filter);
        $rows = $r->getList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }

    //查看图片
    public function show_pic() {
        $id = $this->_get('id');
        $m = new \addons\order\model\RechargeModel();
        $data = $m->getPicById($id);
        $pic = $data['pic'];
        $this->assign('pic', $pic);
        return $this->fetch();
    }

    //审核
    public function doCheck() {
        $orderId = $this->_get('id');
        $state = $this->_get('status');
        $rechargeM = new \addons\order\model\RechargeModel();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        $orderData = $rechargeM->where(['id' => $orderId])->find();
        $balanceM->startTrans();
        try {
            if ($state == 1) {
                //更新用户余额
                $balanceM->updateBalance($orderData['user_id'], 1, $orderData['amount'], true);
                //获取用户更新完余额
                $balanceData = $balanceM->getBalanceByType($orderData['user_id'], 1);
                $recordM->addRecord($orderData['user_id'], $orderData['amount'], $balanceData['before_amount'], $balanceData['amount'], 1, 15, 1, 0, '充值');
                //修改订单状态
                $rechargeM->where(['id' => $orderId])->update(['status' => 1, 'update_time' => NOW_DATETIME]);
                $balanceM->commit();
                return $this->successData();
            } else {
                //修改订单状态
                $rechargeM->where(['id' => $orderId])->update(['status' => -1, 'update_time' => NOW_DATETIME]);
                $balanceM->commit();
                return $this->successData();
            }
        } catch (\Exception $ex) {
            $balanceM->rollback();
            return $this->failData($ex->getMessage());
        }
    }

//    public function edit() {
//        if (IS_POST) {
//            $m = new \addons\member\model\LevelConfig();
//            $data = $_POST;
//            $id = $data['id'];
//            try {
//                if (empty($id)) {
//                    $m->add($data);
//                } else {
//                    $m->save($data);
//                }
//                return $this->successData();
//            } catch (\Exception $ex) {
//                return $this->failData($ex->getMessage());
//            }
//        } else {
//            $this->assign('id', $this->_get('id'));
//            $this->setLoadDataAction('loadData');
//            return $this->fetch();
//        }
//    }
//
//    public function loadData() {
//        $id = $this->_get('id');
//        $m = new \addons\member\model\LevelConfig();
//        $data = $m->getDetail($id);
//        return $data;
//    }
//
//    public function loadList() {
//        $keyword = $this->_get('keyword');
//        $filter = '1=1';
//        $m = new \addons\member\model\LevelConfig();
//        if ($keyword != null) {
//            $filter .= ' and level_name like \'%' . $keyword . '%\'';
//        }
//        $total = $m->getTotal($filter);
//        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
//        return $this->toDataGrid($total, $rows);
//    }
//
//    public function del() {
//        $id = $this->_post('id');
//        $m = new \addons\member\model\LevelConfig();
//        $where['id'] = $id;
//        $where['is_default'] = 0;
//        $res = $m->where($where)->delete();
//        if ($res > 0) {
//            return $this->successData();
//        } else {
//            return $this->failData('删除失败!');
//        }
//    }
}

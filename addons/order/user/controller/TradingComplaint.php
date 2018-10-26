<?php

namespace addons\order\user\controller;

/**
 @author zhuangminghan 
 * 订单管理
 */
class TradingComplaint extends \web\user\controller\AddonUserBase {

    public function index() {
        $type = $this->_get('type');
        if ($type == '') {
            $type = 0;
        }
        $this->assign('type', $type);
        return $this->fetch();
    }

    public function loadList() {
        $keyword = $this->_get('keyword');
        $filter = ' 1=1';
        if ($keyword != null) {
            $filter .= ' and sphone like "%' . $keyword . '%" or order_id like "%' . $keyword . '%"';
        }
        $r = new \addons\member\model\TradingComplaint();
        $total = $r->getTrandTotal($filter);
        $rows = $r->getList($filter,$this->getPageIndex(), $this->getPageSize());
        return $this->toDataGrid($total, $rows);
    }


    /**
    * 操作投诉
    */
    public function cancleOrder(){
        $r = new \addons\member\model\TradingComplaint();
        $id = $this->_get('id');
        $res = $r->where(['id'=>$id])->update(['type'=>1]);
        if($res){
            return $this->successData('操作成功');
        }else{
            return $this->failData('操作失败');
        }

    }
}

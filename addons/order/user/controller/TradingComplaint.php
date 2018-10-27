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

    public function loadData(){
        $id = $this->_get('id');
        $m = new \addons\member\model\TradingComplaint();
        $data = $m->getDetail($id);
        return $data;
    }

    public function edit() {
        if (IS_POST) {
            $data = $_POST;
            $id = $data['id'];
            $data['update_at'] = NOW_DATETIME;
            $data['type'] = 1;
            $m = new \addons\member\model\TradingComplaint();
            try {
                $ret = $m->save($data);
                return $this->successData();
            } catch (\Exception $e) {
                return $this->failData($e->getMessage()); 
            }
        } else {
            $this->assign('id', $this->_get('id'));
            $m = new \addons\member\model\TradingComplaint();
            $role = $m->getDataList();
            $this->assign('role',$role);
            $this->setLoadDataAction('loadData');
            return $this->fetch();
        }
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

<?php

namespace addons\order\user\controller;

/**
 @author zhuangminghan 
 * 订单管理
 */
class Trading extends \web\user\controller\AddonUserBase {

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
        $r = new \addons\member\model\Trading();
        $total = $r->getTotal($filter);
        $rows = $r->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }



}

<?php

namespace addons\order\user\controller;

/**
 @author zhuangminghan 
 * 订单管理
 */
class Trading extends \web\user\controller\AddonUserBase {

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
        $type = $this->_get('type');
        $filter = 'type=' . $type;
        if ($keyword != null) {
            $filter .= ' and username like \'%' . $keyword . '%\'';
        }
        $r = new \addons\member\model\Trading();
        $total = $r->getTotal($filter);
        $rows = $r->getList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }



}

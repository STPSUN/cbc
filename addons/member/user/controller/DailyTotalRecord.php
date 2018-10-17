<?php

namespace addons\member\user\controller;

/**
 * Description of Record
 * 拨比
 * @author shilinqing
 */
class DailyTotalRecord extends \web\user\controller\AddonUserBase{
    
    public function index(){
        return $this->fetch();
    }
    
    public function loadList(){
        $m = new \addons\member\model\DailyTotalRecord();
        $filter = '1=1';
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
//        $count_total = $m->getCountTotal($filter);
        return $this->toDataGrid($total, $rows);
    }
    
}

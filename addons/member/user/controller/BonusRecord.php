<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace addons\member\user\controller;

/**
 * Description of BonusRecord
 *
 * @author shilinqing
 */
class BonusRecord extends \web\user\controller\AddonUserBase{
    
    public function index(){
        return $this->fetch();
    }
    
    public function loadList(){
        $keyword = $this->_get('keyword');
        $type = $this->_get('type');
        $startDate = $this->_get('startDate');
        $endDate = $this->_get("endDate");
        $filter = '1=1';
        if($type != ''){
            $filter .= ' and type='.$type;
        }
        if ($keyword != null) {
            $filter .= ' and username like \'%' . $keyword . '%\'';
        }
        if ($startDate != null && $endDate != null)
            $filter .= ' and (update_time >= \'' . $startDate . ' 00:00:00\' and update_time <= \'' . $endDate . ' 23:59:59\')';
        elseif ($startDate != null)
            $filter .= ' and (update_time >= \'' . $startDate . ' 00:00:00\')';
        elseif ($endDate != null)
            $filter .= ' and (update_time <= \'' . $endDate . ' 23:59:59\')';
        $m = new \addons\member\model\BonusRecord();
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
        $count_total = $m->getCountTotal($filter);
        return $this->toTotalDataGrid($total, $rows,$count_total);
    }
    
   
    
}

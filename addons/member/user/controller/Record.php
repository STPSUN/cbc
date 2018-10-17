<?php

namespace addons\member\user\controller;

/**
 * Description of Record
 * 交易记录
 * @author shilinqing
 */
class Record extends \web\user\controller\AddonUserBase{
    
    public function index(){
        $m = new \addons\config\model\BalanceConf();
        $list = $m->getDataList(-1,-1,'','id,name','id asc');
        $this->assign('confs',$list);
        return $this->fetch();
    }
    
    public function loadList(){
        $keyword = $this->_get('keyword');
        $asset_type = $this->_get('asset_type');
        $change_type = $this->_get('change_type');
        $type = $this->_get('type');
        $startDate = $this->_get('startDate');
        $endDate = $this->_get("endDate");
        $filter = '1=1';
        if($type != ''){
            $filter .= ' and type='.$type;
        }
        if($asset_type != ''){
            $filter .= ' and asset_type='.$asset_type;
        }
         if($change_type != ''){
            $filter .= ' and change_type='.$change_type;
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
        $m = new \addons\member\model\TradingRecord();
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
        $count_total = $m->getCountTotal($filter);
        return $this->toTotalDataGrid($total, $rows,$count_total);
    }
    
}

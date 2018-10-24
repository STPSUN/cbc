<?php

namespace addons\config\user\controller;

class Quotation extends \web\user\controller\AddonUserBase{
    
    public function index(){
        return $this->fetch();
    }
    
    public function loadList(){
        $filter = '';
        $m = new \addons\config\model\Quotation();
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter, '', $this->getOrderBy('id desc'));
        return $this->toDataGrid($total, $rows);
    }
    
    public function loadData(){
        $id = $this->_get('id');
        $m = new \addons\config\model\Quotation();
        $data = $m->getDetail($id);
        return $data;
    }
    
    public function edit(){
        if (IS_POST) {
            $id = $this->_post('id');
            $data['price_top'] = $this->_post('price_top');
            $data['price_now'] = $this->_post('price_now');
            $data['price_low'] = $this->_post('price_low');
            $data['update_at'] = NOW_DATETIME;
            $m = new \addons\config\model\Quotation();
            try {
                if(empty($id)){
                    $data['create_at'] = NOW_DATETIME;
                    $map['create_at'] = ['between',[date('Y-m-d'),date('Y-m-d',strtotime('+1 days'))]];
                    $res = $m->where($map)->find();
                    if(!$res) $ret = $m->add($data);
                    else $this->failData('今日已添加');
                }else{
                    $data['id'] = $id;
                    $ret = $m->save($data);
                }
                return $this->successData();
                
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
        } else {
            $id = $this->_get('id');
            $this->assign('id', $id);
            $this->setLoadDataAction('loadData');
            return $this->fetch();
        }
    }
  
}

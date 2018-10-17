<?php

namespace addons\baodan\user\controller;

class LeaderConfig extends \web\user\controller\AddonUserBase{
    
    public function index(){
        return $this->fetch();
        
    }
    
    public function loadList(){
        $keyword = $this->_get('keyword');
        $filter = '1=1';
        $m = new \addons\baodan\model\LeaderConfig();
        if ($keyword != null) {
            $filter .= ' and title like \'%' . $keyword . '%\'';
        }
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }
    
    public function loadData(){
        $id = $this->_get('id');
        $m = new \addons\baodan\model\LeaderConfig();
        $data = $m->getDetail($id);
        return $data;
    }
    
    public function edit(){
        if(IS_POST){
            $m = new \addons\baodan\model\LeaderConfig();
            $data = $_POST;
            $id = $data['id'];
            try{
                $data['update_time'] = NOW_DATETIME;
                if(empty($id)){
                   $m->add($data); 
                }else{
                   $m->save($data);
                }
                return $this->successData();
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
                
            }
        }else{
            $this->assign('id', $this->_get('id'));
            $this->setLoadDataAction('loadData');
            return $this->fetch();
        }
    }
    
    public function del(){
        $id = $this->_post('id');
        $m = new \addons\baodan\model\LeaderConfig();
        $res = $m->deleteData($id);
        if($res > 0){
            return $this->successData();
        }else{
            return $this->failData('删除失败!');
        }
    }
}

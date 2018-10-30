<?php

namespace addons\member\user\controller;

use web\api\service\NodeService;

class Node extends \web\user\controller\AddonUserBase{
    
    public function index(){
        return $this->fetch();
    }
    
    public function loadList(){
        $keyword = $this->_get('keyword');
        $filter = '';
        if ($keyword != null) {
            $filter .= ' and phone like \'%' . $keyword . '%\'';
        }
        $m = new \web\api\model\MemberNode();
        $total = $m->getTotal($filter);
        $rows = $m->getList($this->getPageIndex(), $this->getPageSize(), $filter);
        foreach ($rows as $key => $value) {
            $rows[$key]['pass_time'] = date('Y-m-d H:i:s',$value['pass_time']);
        }
        return $this->toDataGrid($total, $rows);
    }
    
    public function edit(){
        $m = new \web\api\model\MemberNode();
        if(IS_POST){
            $data = $_POST;
            $data['pass_time'] = strtotime($data['pass_time']);
            if($data['id']){
                $m->save($data);
                return $this->successData();
            }else{
                return $this->failData('用户id为空');
            }
        }else{
            
            $this->assign('id', $this->_get('id'));
            $this->setLoadDataAction('loadData');
            return $this->fetch();
        }
    }
    
    public function loadData() {
        $id = $this->_get('id');
        $m = new \web\api\model\MemberNode();
        $data = $m->getDetail($id);
        $data['pass_time'] = date('Y-m-d H:i:s',$data['pass_time']);
        return $data;
    }
    
    public function select_user(){
        return $this->fetch();
    }
    
    public function loadSelectUser(){
        $keyword = $this->_get('keyword');
        $filter = 'logic_delete=0';
        if ($keyword != null) {
            $filter .= ' and username like \'%' . $keyword . '%\'';
        }
        $m = new \web\api\model\MemberNode();
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }

    
    
    /**
     * 加载认证数据
     * @return type
     */
    public function loadCard(){
        $id = $this->_get('id');
        $m = new \addons\member\model\MemberAccountModel();
        $data = $m->getAuthData($id);
        return $data;
    }
    
    
    public function view_childs(){
        $id = $this->_get('id');
        $m = new \addons\member\model\MemberAccountModel();
        $data = $m->getDetail($id,'id,aid,username,left_total,right_total');
        if($data){
            $data['children'] = $m->getChildsByAID($id);
        }
        $this->assign('data', json_encode($data, 256));
        return $this->fetch();
    }
    
    public function load_childs(){
        $aid = $this->_get('aid');
        $m = new \addons\member\model\MemberAccountModel();
        return $m->getChildsByAID($aid);
    }
    
    public function view_balance(){
        $id = $this->_get('id');
        $m = new \addons\member\model\Balance();
        $filter = 'user_id='.$id;
        $list = $m->getList(-1,-1,$filter);
//        dump($list);exit;
        $this->assign('data',$list);
        return $this->fetch();
    }
    
    
    
    public function change_frozen(){
        $id = $this->_post('id');
        $status = $this->_post('status');
        if($status != 0){
            $status = 1;
        }
        $m = new \addons\member\model\MemberAccountModel();
        try{
            $ret = $m->changeFrozenStatus($id, $status);
            if($ret > 0){
                return $this->successData();
            }else{
                $message = '操作失败';
                return $this->failData($message);
            }
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }
    
    /**
     * 逻辑删除
     * @return type
     */
    public function del(){
        $id = $this->_post('id');
        $m = new \web\api\model\MemberNode();
        try{
            $ret = $m->deleteData($id);
            if($ret > 0){
                return $this->successData();
            }else{
                $message = '删除失败';
                return $this->failData($message);
            }
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }
    

}



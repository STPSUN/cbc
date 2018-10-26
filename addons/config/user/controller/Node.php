<?php

namespace addons\config\user\controller;

/**
 * Description of Account
 *
 * @author zhuangminghan
 */
class Node extends \web\user\controller\AddonUserBase{
    
    public function index(){
        return $this->fetch();
        
    }
    
    public function loadList() {
        $keyword = $this->_get('keyword');
        $m = new \web\api\model\Node();
        $filter = '';
        if($keyword){
            // $filter = 'time_length = '.$keyword;
        }
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }
    
    public function edit() {
        if (IS_POST) {
            $data = $_POST;
            $id = $data['id'];
            $data['update_time'] = NOW_DATETIME;
            $m = new \web\api\model\Node();
            try {
                if (empty($id)) {
                    $ret = $m->add($data);
                } else {
                    $ret = $m->save($data);
                }
                return $this->successData();
            } catch (\Exception $e) {
                return $this->failData($e->getMessage());
            }
        } else {
            $this->assign('id', $this->_get('id'));
            $m = new \addons\config\model\Role();
            $role = $m->getDataList();
            $this->assign('role',$role);
            $this->setLoadDataAction('loadData');
            return $this->fetch();
        }
    }
    

    public function change(){
        $id = $this->_get('id');
        $m = new \web\api\model\Node();
        try {
            $res = $m->where(['id'=>$id])->find();
            $res['status'] = $res['status']?0:1;
            $res['update_at'] = NOW_DATETIME;
            $ret = $m->save($res);
            return $this->successData();
        } catch (\Exception $e) {
            return $this->failData($e->getMessage());
        }
        
    }
    public function loadData(){
        $id = $this->_get('id');
        $m = new \web\api\model\Node();
        $data = $m->getDetail($id);
        return $data;
    }
    
    /**
     * 删除
     */
    public function del() {
        $id = intval($this->_get('id'));
        if (!empty($id)) {
            $m = new \web\api\model\Node();
            try {
                $res = $m->deleteData($id);
                if ($res > 0) {
                    return $this->successData();
                } else {
                    return $this->failData('删除失败');
                }
            } catch (\Exception $e) {
                return $this->failData($e->getMessage());
            }
        } else {
            return $this->failData('删除失败，参数有误');
        }
    }
    
}

<?php

namespace addons\member\user\controller;

class Member extends \web\user\controller\AddonUserBase{
    
    public function index(){
//        $is_auth = $this->_get('is_auth');
//        if($is_auth == ''){
//            $is_auth = 2; //待认证
//        }
//        $this->assign('is_auth',$is_auth);
        return $this->fetch();
    }
    
    public function loadList(){
//        $is_auth = $this->_get('is_auth');
        $keyword = $this->_get('keyword');
        $filter = 'logic_delete=0';
        if ($keyword != null) {
            $filter .= ' and username like \'%' . $keyword . '%\'';
        }
        $m = new \addons\member\model\MemberAccountModel();
        $total = $m->getTotal($filter);
        $rows = $m->getList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }
    
    public function edit(){
        $m = new \addons\member\model\MemberAccountModel();
        if(IS_POST){
            $data = $_POST;
            $password = $this->_post("now_password");
            if(!empty($password)){
                if (!preg_match("/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,20}$/", $password)) {
                    return $this->failData('请输入5~20位字母数字密码');
                }
                $data['password'] = md5($password);
            }
            if($data['id']){
                $m->save($data);
                return $this->successData();
            }else{
                return $this->failData('用户id为空');
            }
        }else{
            
            $list = $m->field('id,username')->where('logic_delete=0')->order('id asc')->select();
            $this->assign('user_list', json_encode($list, 256));
            $this->assign('id', $this->_get('id'));
            $this->setLoadDataAction('loadData');
            $mealM = new \addons\baodan\model\MealConfig();
            $meals = $mealM->getDataList(-1,-1,'','','id asc');
            $this->assign('meals',$meals);
            return $this->fetch();
        }
    }
    
    public function loadData() {
        $id = $this->_get('id');
        $m = new \addons\member\model\MemberAccountModel();
        $data = $m->getDetail($id);
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
        $m = new \addons\member\model\MemberAccountModel();
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }


    /**
     * 认证
     */
    public function auth(){
       if(IS_POST){
           $is_auth = $this->_post('is_auth');
           $user_id = $this->_post('id');
           if($is_auth && $user_id){
                $m = new \addons\member\model\MemberAccountModel();
                $data['id'] = $user_id;
                $data['is_auth'] = $is_auth;
                $ret = $m->save($data);
                if($ret > 0){
                    return $this->successData();
                }
           }else{
               return $this->failData('缺少参数');
           }
       }else{
           $this->assign('id',$this->_get('id'));
           $this->setLoadDataAction('loadCard');
           return $this->fetch();
       }
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
    
    /**
     * 拨币
     * @return type
     */
    public function add_coin_stock(){
        if(IS_POST){
            $user_id = $this->_post('id');
            $amount = $this->_post('amount');
            $type = $this->_post('type');
            $remark = $this->_post('remark');
            $memberM = new \addons\member\model\MemberAccountModel();
            $m = new \addons\member\model\Balance();
            $m->startTrans();
            $balance = $m->getBalanceByType($user_id,$type);
            try{
                if(!empty($balance)){
                    $id = $balance['id'];
                    $before_amount = $balance['amount'];
                    $balance['type'] = $type;
                    $balance['amount'] = $before_amount + $amount;
                    $balance['update_time'] = NOW_DATETIME;
                    $m->save($balance);

                }else{
                    $balance['user_id'] = $user_id;
                    $balance['type'] = $type;
                    $balance['amount'] = $amount;
                    $balance['update_time'] = NOW_DATETIME;
                    $id = $m->add($balance);
                }
                if($id > 0){
                    $rm = new \addons\member\model\TradingRecord();
                    $after_amount = $balance['amount'];
                    $asset_type = $type;
                    if($amount > 0){
                        $change_type = 1; //增加
                    }else{
                        $change_type= 0;//减少
                        $amount = abs($amount);
                    }
                    $type = 3;//后台拨币
//                    $remark = '系统后台拨币';
                    $r_id = $rm->addRecord($user_id, $amount, $before_amount, $after_amount,$asset_type, $type, $change_type,0, $remark);
                    if($r_id > 0){
                        $m->commit();
                        return $this->successData();
                    }
                }else{
                    $m->rollback();
                    return $this->failData('拨币失败');
                }
            } catch (\Exception $ex) {
                $m->rollback();
                return $this->failData($ex->getMessage());
            }
            
        }else{
            $m = new \addons\config\model\BalanceConf();
            $list = $m->getDataList(-1,-1,'','id,name','id asc');
            $this->assign('types',$list);
            $this->assign('id',$this->_get('id'));
            return $this->fetch();
        }
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
        $m = new \addons\member\model\MemberAccountModel();
        try{
            $where1['aid'] = $id;
            $user = $m->where($where1)->find();
            if(!empty($user)){
                return $this->failData('存在下级节点用户:'.$user['username']);
            }
//            删除账号后退款到推荐人用户 ， 删除的账号下面如果有节点 就不能删除
            $m->startTrans();
            //删除资产
            $b = new \addons\member\model\Balance();
            $where['user_id'] = $id;
            $del = $b->where($where)->delete();
            //删除报单订单
            $o = new \addons\baodan\model\MealOrder();
            $o_del = $o->where($where)->delete();
            //删除结算记录
            $tbr = new \addons\member\model\TotalBonusRecord();
            $tbr->where($where)->delete();
            $br = new \addons\member\model\BonusRecord();
            $br->where($where)->delete();
            $ret = $m->deleteData($id);
            if($ret > 0){
                $m->commit();
                return $this->successData();
            }else{
                $m->rollback();
                $message = '删除失败';
                return $this->failData($message);
            }
        } catch (\Exception $ex) {
            $m->rollback();
            return $this->failData($ex->getMessage());
        }
    }
    

}



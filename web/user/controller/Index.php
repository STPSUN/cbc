<?php

namespace web\user\controller;


/**
 * 后台首页
 */
class Index extends Base {

    public function index() {
       return $this->fetch();
    }
    
    public function edit_pwd() {
        if (IS_POST) {
            $m = new \web\common\model\user\AccountModel();
            $id = $this->login_user_id;
            $password1 = trim($_POST['password1']);
            $password = trim($_POST['password']);
            if($password != $password1){
                return $this->failData('两次输入的密码不符');
            }
            $accountData['id'] = $id;
            $accountData['password'] = md5($password);
            $ret = $m->save($accountData);
            
            return $this->successData();
            
        } else {
            $this->assign('username', $this->login_user_name);
            $this->assign('id', '');
            $this->assign('permission', array(1, 0, 1));
            return $this->fetch();
        }
    }
}
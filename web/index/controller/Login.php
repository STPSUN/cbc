<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace web\index\controller;

/**
 * Description of Login
 *
 * @author shilinqing
 */
class Login extends \web\common\controller\BaseController {
    
    public function index(){
        if(IS_POST){
            try{
                $code = $this->_post('code');
                if (!captcha_check($code)) {
                    return $this->failData('验证码输入错误！');
                }
                $phone = $this->_post('phone');
                $password = $this->_post('password');
                if (empty($phone)) {
                    return $this->failData('用户名或者手机号不能为空');
                }
                if (empty($password)) {
                    return $this->failData('密码不能为空');
                }
                
                $m = new \addons\member\model\MemberAccountModel();
                $res = $m->getLoginData($password,$phone,'id,username',true);
                if ($res) {
                    $memberData['user_id'] = $res['id'];
                    $memberData['username'] = $res['username'];
                    session('memberData', $memberData);
                    $url = url('index/index');
                    $ip = getRealIpAddr();
                    $_data['id'] = $res['id'];
                    $_data['login_ip'] = $ip;
                    $m->save($_data);
                    return $this->successData($url);
                } else {
                    return $this->failData('帐号或密码有误');
                }
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
        }else{
            return $this->fetch('index');
        }
    }
    
    public function register(){
        if(IS_POST){
            $m = new \addons\member\model\MemberAccountModel();
            $inviter_username = $this->_post('inviter');
            $pid = $m->getUserIDByUsername($inviter_username);
            if(empty($pid)){
                return $this->failData('所填写销售经理用户不存在');
            }
            $data['pid'] = $pid;
            $data['username'] = $this->_post('username');
            $data['phone'] = $this->_post('phone');
            $password = $this->_post('password');
            $pay_password = $this->_post('pay_password');
            $data['real_name'] = $this->_post('real_name');
            if (!preg_match("/^[0-9]{6}$/", $pay_password)) {
                return $this->failData('请输入6位数字交易密码');
            }
            if (strlen($password) < 6) {
                return $this->failData('密码长度不能小于6');
            }
            $data['password'] = md5($password);
            $data['pay_password'] = md5($pay_password);
            
            if (preg_match('/[\x7f-\xff]/', $data['username'])) {
                return $this->failData('用户名不支持中文');
            }
            $count = $m->hasRegsterUsername($data['username']);
            if ($count > 0) {
                return $this->failData('此用户名已被注册');
            }
            //节点用户名 判断是否存在
            $data['position'] = $this->_post('position');
            $aid_username = $this->_post('aid_username');
            $aid = $m->getUserIDByUsername($aid_username);
            if(empty($aid)){
                return $this->failData('发展用户不存在');
            }
            //如果选的是右区,则判断该用户左区是否为空,为空提示选择左区
            if($data['position'] == 1){
                $left_child = $m->getLeftChild($aid);
                if(empty($left_child)){
                   return $this->failData('所选发展左区下级为空,请选择左区'); 
                }
            }
            //获取节点用户id
            $child_data = $m->getChildIdByPosition($aid,$data['position']);
            $data['aid'] = $child_data['aid'];
            $data['position'] = $child_data['position'];
            try{
                //添加用户
                $data['meal_id'] = 0;
                $data['self_total'] = 0; //自身业绩
                $data['register_time'] = NOW_DATETIME;
                $user_id = $m->add($data);
                if($user_id > 0){
//                    添加余额数据
                    $balanceM = new \addons\member\model\Balance();
                    $bcm = new \addons\config\model\BalanceConf();
                    $type_list = $bcm->getDataList(-1,-1,'','id','id asc');
                    foreach($type_list as $k => $type){
                        $type = $type['id'];
                        $_balance['user_id'] = $user_id;
                        $_balance['type'] = $type;
                        $_balance['update_time'] = NOW_DATETIME;
                        $balanceM->add($_balance);
                    }
                    $url = url('login/index');
                    return $this->successData($url);
                }
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
        }else{
            $username = $this->_get('name');
            $position = $this->_get('position');
            if($position!=1){
                $position=0;
            }
            if(empty($username)){
                echo '参数有误';
                exit;
            }
            $this->assign('username',$username);
            $this->assign('position',$position);
            
            return $this->fetch();
            
        }
    }
    
    
}

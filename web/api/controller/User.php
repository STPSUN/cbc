<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/16
 * Time: 16:48
 */

namespace web\api\controller;


use addons\member\model\Balance;
use addons\member\model\MemberAccountModel;
use think\Request;
use think\Validate;

class User extends ApiBase
{
    public function login()
    {
        if (IS_POST) {
            try {
                $phone = $this->_post('phone');
                $password = $this->_post('password');
                if (empty($password)) {
                    return $this->failJSON('密码不能为空');
                }
                if (empty($phone)) {
                    return $this->failJSON('手机号不能为空');
                }
                $m = new \addons\member\model\MemberAccountModel();
                $res = $m->getLoginData($password, $phone, 'phone,id,username', 'id,phone,username');
                if ($res) {
                    $memberData['username'] = $res['phone'];
//                    $memberData['address'] = $res['address'];
                    $memberData['user_id'] = $res['id'];
                    session('memberData', $memberData);

                    $token = md5($res['id'] . $this->apikey);
                    $this->setGlobalCache($res['id'], $token); //user_id存储到入redis
                    $data['phone'] = $res['phone'];
                    $data['username'] = $res['username'];
//                    $data['address'] = $res['address'];
                    $data['token'] = $token;
                    return $this->successJSON($data);
                } else {
                    return $this->failJSON('帐号或密码有误');
                }
            } catch (\Exception $ex) {
                return $this->failJSON($ex->getMessage());
            }
        } else {
            return $this->failJSON('请求出错');
        }
    }

    /**
     * 用户注册
     */
    public function register() {
        if (IS_POST) {
            $param = Request::instance()->post();
            $validate = new Validate([
                'phone' => 'require|number',
                'verify_code'   => 'require',
                'password'  => 'require',
                'pay_password'  => 'require'
            ]);
            if(!$validate->check($param))
                return $this->failJSON($validate->getError());

            $data['phone'] = $this->_post('phone');
            $data['verify_code'] = $this->_post('verify_code');
            $password = $this->_post('password');
            $pay_password = $this->_post('pay_password');
//            $data['username'] = $this->_post('username');
//            if ($password != $password1) {
//                return $this->failJSON('两次输入的密码不一致');
//            }
            if (strlen($password) < 8) {
                return $this->failJSON('密码长度不能小于8');
            }
            $data['password'] = md5($password);
            $data['pay_password'] = md5($pay_password);
            $data['address'] = md5(md5(time() . 'ABC') . '!@$');
            $m = new \addons\member\model\MemberAccountModel();
//            $count = $m->hasRegsterUsername($data['username']);
//            if ($count > 0) {
//                return $this->failJSON('此用户名已被注册,请直接登录或尝试找回密码');
//            }
            $count = $m->hasRegsterPhone($data['phone']);
            if ($count > 0) {
                return $this->failJSON('此手机号已被注册,请直接登录或尝试找回密码');
            }
            $m->startTrans();
            try {
                $verifyM = new \addons\member\model\VericodeModel();
                $_verify = $verifyM->VerifyCode($data['verify_code'], $data['phone']);
                if(empty($_verify))
                {
                    $m->rollback();
                    return $this->failJSON('验证码失效,请重新注册');
                }
                $inviter_id = $this->_post('inviter_id');
                if (!empty($inviter_id)) {
                    //获取邀请者id
                    $invite_user = $m->getDetail($inviter_id);
                    if (!empty($invite_user)) {
                        $data['pid'] = $inviter_id; //邀请者id
                    } else {
                        return $this->failJSON('邀请人不存在');
                    }
                }
                $data['register_time'] = NOW_DATETIME;
                $user_id = $m->add($data); //用户id
                $balanceM = new Balance();
                for($i = 1; $i <= 5; $i++)
                {
                    $balance = array(
                        'user_id'   => $user_id,
                        'type'  => $i,
                        'update_time'   => NOW_DATETIME,
                    );

                    $balanceM->save($balance);
                }
                $m->commit();
                return $this->successJSON('注册成功');
//                $res = $this->getEthAddr($data['phone']);
//                if ($res) {
//                    $data['address'] = $this->_address; //eth地址
//                    $data['eth_pass'] = $this->ethPass;
//                    $user_id = $m->add($data); //用户id
//                    $m->commit();
//                    return $this->successJSON('注册成功');
//                }
            } catch (\Exception $ex) {
                return $this->failJSON($ex->getMessage());
            }
        }else {
            return $this->failJSON('请求出错');
        }
    }

    /**
     * 短信验证
     */
    public function getVerifyCode(){
        $phone = $this->_post('phone');
//        $time = $this->_post('time');
        $type = $this->_post('type');
        $time = 120;
        if(empty($type))
            $type = 1;//注册验证码
        $m = new \addons\member\model\VericodeModel();
        $unpass_code = $m->hasUnpassCode($phone,$type);
        if(!empty($unpass_code)){
            return $this->failJSON('验证码未过期,请输入之前收到的验证码');
        }
        try{
            //发送验证码
            $res = \addons\member\utils\Sms::send($phone);
            $code = $res['code'];
//            $res['success'] = true;
//            $res['message'] = '短信发送成功';
//            $res['code'] = '1111';
            if(!empty($res['code'])){
                //保存验证码
                $pass_time = date('Y-m-d H:i:s',strtotime("+".$time." seconds"));
                $data['phone'] = $phone;
                $data['code'] = $res['code'];
                $data['type'] = $type;
                $data['pass_time'] = $pass_time; //过期时间
                $m->add($data);
                unset($res['code']);
            }
            return $this->successJSON($code);
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo()
    {
        $userM = new MemberAccountModel();
        $balanceM = new Balance();

        $user = $userM->getDetail($this->user_id);

        if(empty($user))
        {
            return $this->failJSON('该用户不存在');
        }

        $balance = array();
        $balance_data = $balanceM->where('user_id',$this->user_id)->select();
        foreach ($balance_data as $v)
        {
            switch ($v['type'])
            {
                case 1:
                {
                    $balance['total_amount'] = $v['amount'];
                    $balance['use_amount'] = bcmul($v['amount'],0.7,2);
                    break;
                }
                case 3:
                    $balance['lock_amount'] = $v['amount'];    break;
                case 4:
                    $balance['key_amount'] = $v['amount'];     break;
                case 5:
                    $balance['today_amount'] = $v['amount'];   break;
            }
        }

        $data['balance'] = $balance;
        $data['credit_level'] = $user['credit_level'];
        $data['node_level'] = $user['node_level'];
        $data['user_level'] = $user['user_level'];
        $data['phone']  = $user['phone'];
        $data['is_auth'] = $user['is_auth'];
        $data['head_img'] = $user['head_img'];
        $data['address'] = $user['address'];

        return $this->successJSON($data);
    }

    /**
     * 修改登录密码
     * @return type
     */
    public function modifyLoginPass(){
        if(IS_POST){
            $auth_code = $this->_post('auth_code');
            $password = $this->_post('password');
            $password1 = $this->_post('password1');
            $phone  = $this->_post('phone');
            if($password != $password1){
                return $this->failJSON('两次输入的密码不一致');
            }

            if (strlen($password) < 8) {
                return $this->failJSON('密码长度不能小于8');
            }

            $verifyM = new \addons\member\model\VericodeModel();
            $_verify = $verifyM->VerifyCode($auth_code, $phone,2);
            if(empty($_verify))
            {
                return $this->failJSON('验证码失效');
            }

            $password = md5($password);
            $m = new \addons\member\model\MemberAccountModel();
            $res = $m->updatePassByUserID($this->user_id, $password);
            if($res > 0){
                return $this->successJSON();
            }else{
                return $this->failJSON('修改密码失败');
            }
        }
    }
}



















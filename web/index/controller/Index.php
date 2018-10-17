<?php

namespace web\index\controller;

/**
 * 前端首页控制器
 */
class Index extends Base {

    public function index(){
        //资产
        $m = new \addons\member\model\Balance();
        $list = $m->getUserBalanceList($this->user_id);
        $this->assign('balances',$list);
        //公告
        $noticeM = new \addons\config\model\Notice();
        $notices = $noticeM->getDataList(1,10);
        $this->assign('notices', $notices);
        //轮播
        $sliderM = new \addons\config\model\Slider();
        $sliders = $sliderM->getDataList(1,5);
        $this->assign('sliders', $sliders);

        return $this->fetch();
    }
    
    /**
     * 登出
     */
    public function out(){
        $memberData = session('memberData');
        if (empty($memberData))
            return $this->successData();
        session('memberData', null);
        $url = getUrl('/index/login');
        $this->redirect($url);
        exit;
    }
    
    /**
     * 修改密码
     * @return type
     */
    public function change_pass(){
        if(IS_POST){
            $old_password = $this->_post('old_password');
            $password = $this->_post('password');
            $password1 = $this->_post('password1');
            if($password != $password1){
                return $this->failData('两次输入的密码不一致');
            }
            if (!preg_match("/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,20}$/", $password)) {
                return $this->failData('请输入5~20位字母数字密码');
            }
            $old_password = md5($old_password);
            
            $m = new \addons\member\model\MemberAccountModel();
            $user = $m->getDetail($this->user_id, 'password');
            if($old_password != $user['password']){
                return $this->failData('原密码错误,请重新输入');
            }
            $password = md5($password);
            $res = $m->updatePassByUserID($this->user_id, $password);
            if($res > 0){
                return $this->successData();
            }else{
                return $this->failData('修改密码失败');
            }
        }else{
            return $this->fetch();
        }
    }
    
    /**
     * 修改支付密码
     * @return type
     */
    public function change_pay_pass(){
        if(IS_POST){
            $old_password = $this->_post('old_password');
            $password = $this->_post('password');
            $password1 = $this->_post('password1');
            if($password != $password1){
                return $this->failData('两次输入的密码不一致');
            }
            if (!preg_match("/^[0-9]{6}$/", $password)) {
                return $this->failData('请输入6位数字交易密码');
            }
            $old_password = md5($old_password);
            $m = new \addons\member\model\MemberAccountModel();
            $user = $m->getDetail($this->user_id, 'pay_password');
            if($old_password != $user['pay_password']){
                return $this->failData('原密码错误,请重新输入');
            }
            $password = md5($password);
            $res = $m->updatePassByUserID($this->user_id, $password, 3);
            if($res > 0){
                return $this->successData();
            }else{
                return $this->failData('修改密码失败');
            }
        }else{
            return $this->fetch();
        }
    }
    
    /**
     * 修改资料
     * @return type
     */
    public function change_info(){
        if(IS_POST){
            $data['real_name'] = $this->_post('real_name');
            $data['bank_name'] = $this->_post('bank_name');
            $data['bank_other']  = $this->_post('bank_other');
            $data['bank_code']  = $this->_post('bank_code');
            $data['address']  = $this->_post('address');
            if(empty($data['bank_name']) ||empty($data['bank_other'])||empty($data['bank_code'])||empty($data['address']) ){
                return $this->failData('数据不能为空');
            }
            $data['id'] = $this->user_id;
            $m = new \addons\member\model\MemberAccountModel();
            $res = $m->save($data);
            if($res > 0){
                return $this->successData();
            }else{
                return $this->failData('修改资料失败');
            }
        }else{
            $this->setLoadDataAction('loadBankAddress');
            return $this->fetch();
        }
    }
    
    public function loadBankAddress(){
        $m = new \addons\member\model\MemberAccountModel();
        $data = $m->getUserBankAndAddress($this->user_id);
        return $data;
    }
    
    /**
     * 忘记密码
     */
    public function forgetPass(){
        if(IS_POST){
            $type = $this->_post('type');// 密码类型 ,2=登陆,3=交易
            $password = $this->_post('password');
            $password1 = $this->_post('password1');
            if($password != $password1){
                return $this->failData('两次输入的密码不一致');
            }
            if(!empty($password))
                $password = md5($password);
            $m = new \addons\member\model\MemberAccountModel();
            try{
                $id = $m->updatePassByUserID($this->user_id,$password); //用户id
                if($id < 0){
                    return $this->failData('重置失败,请重试');
                }
                return $this->successData();

            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
            
        }else{
            return $this->fetch();
        }
        
    }
    
    /**
     * 修改手机号
     */
    public function change_phone(){
        $type = 10;
        $m = new \addons\member\model\MemberAccountModel();
        $phone = $m->where('id='.$this->user_id)->value('phone');
        if(IS_POST){
            $code = $this->_post('code');
            $new_phone = $this->_post('new_phone');
            if(empty($code) || empty($phone)){
                return $this->failData('请完善表单');
            }
            if (!preg_match("/13[0-9]{1}\d{8}|15[0-9]\d{8}|188\d{8}/", $new_phone)) {
                //11为手机号, 匹配13[0-9]后8位 \d数字| 15[0-9]后8位数字 | 188 后8位数字 
                return $this->failData('手机号码格式错误');
            }
            $verifyM = new \addons\member\model\VericodeModel();
            $_verify = $verifyM->VerifyCode($code, $phone, $type);
            if (!empty($_verify)) {
                $id = $m->where('id='.$this->user_id)->update(['phone' => $new_phone]);
                if ($id <= 0) {
                    return $this->failData('重置失败');
                }
                return $this->successData("修改成功");
            } else {
                return $this->failData('验证码失效,请重新发送');
            }
        }else{
            $this->assign('phone',$phone);
            $this->assign('time',120);
            return $this->fetch();
        }
    }
    
    /**
     * 获取短信
     */
    public function getSms(){
        $type = 10; //修改手机号
        $time = 120;
        $u = new \addons\member\model\MemberAccountModel();
        $phone = $u->where('id='.$this->user_id)->value('phone'); //获取当前账号的手机号
        
        $m = new \addons\member\model\VericodeModel();
        $unpass_code = $m->hasUnpassCode($phone, $type);
        if (!empty($unpass_code)) {
            return $this->failData('验证码未过期,请输入之前收到的验证码');
        }
        try {
            //发送验证码 todo
            $res = \addons\member\utils\Sms::send($phone);
//            $res['success'] = true;
//            $res['message'] = '测试短信发送成功:1111';
//            $res['code'] = '1111';
            if (!$res['success']) {
                return $this->failData($res['message']);
            }
            //保存验证码
            $pass_time = date('Y-m-d H:i:s', strtotime("+" . $time . " seconds"));
            $data['phone'] = $phone;
            $data['code'] = $res['code'];
            $data['type'] = $type;
            $data['pass_time'] = $pass_time; //过期时间
            $result = $m->add($data);
            if (empty($result)) {
                return $this->failData('验证码生成失败'); //写入数据库失败
            }
            unset($res['code']);

            return $this->successData($res['message']);
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
        
    }
    
}

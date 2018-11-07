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
use addons\member\model\PayConfig;
use addons\member\model\TradingRecord;
use think\Request;
use think\Validate;
use web\api\model\Leave;
use web\api\model\MemberNode;
use web\api\service\AwardService;
use web\api\service\MemberService;
use web\api\service\NodeService;

class User extends ApiBase
{
    public function login()
    {
        if (IS_POST) {
            try {
                $phone = $this->_post('phone');
                $password = $this->_post('password');
                if (empty($password)) {
                    return $this->failJSON(lang('USER_PASSWORD'));
                }
                if (empty($phone)) {
                    return $this->failJSON(lang('USER_PHONE'));
                }
                $m = new \addons\member\model\MemberAccountModel();
                $res = $m->getLoginDataById($password, $phone, 'phone,id,username,head_img,token,address', 'id,phone,username');
                $balanceM = new Balance();
                if ($res) {
                    $info = $balanceM->where(['user_id'=>$res['id']])->select();
                    if($info){
                        foreach ($info as $key => $value) {
                            if($value['type']!=1){
                                $this->balaceAdd($res['id'],1,$balanceM);
                            }elseif($value['type']!=2){
                                $this->balaceAdd($res['id'],2,$balanceM);
                            }elseif($value['type']!=5){
                                $this->balaceAdd($res['id'],5,$balanceM);
                            }elseif($value['type']!=3){
                                $this->balaceAdd($res['id'],3,$balanceM);
                            }elseif($value['type']!=4){
                                $this->balaceAdd($res['id'],4,$balanceM);
                            }
                        }
                    }else{
                        for ($i=1; $i <6 ; $i++) { 
                            $balance = array(
                                'user_id'   => $res['id'],
                                'type'  => $i,
                                'update_time'   => NOW_DATETIME,
                            );
                            $balanceM->save($balance);
                        }
                    }
                    $memberData['username'] = $res['phone'];
                    $memberData['user_id'] = $res['id'];
                    session('memberData', $memberData);

                    $token = md5($res['id'] . $this->apikey . time());
                    $map['token'] = $token;
                    $rand = $this->getRand(5);
                    if(empty($res['address']))
                    {
                        $map['address'] = md5(md5(time() . $rand) . '!@$');
                    }else
                    {
                        $count = $m->where('address',$res['address'])->count();
                        if($count > 1)
                        {
                            $map['address'] = md5(md5(time() . $rand) . '!@$');
                        }
                    }

                    $m->save($map,[
                        'id'    => $res['id'],
                    ]);
                    $e = $this->setGlobalCache($res['id'], $token); //user_id存储到入redis
                    $data['phone'] = $res['phone'];
                    $data['user_id'] = $res['id'];
                    $data['username'] = $res['username'];
                    $data['head_img'] = $res['head_img'];
                    $data['token'] = $token;
                    return $this->successJSON($data);
                } else {
                    return $this->failJSON(lang('USER_ACCOUNT_WRONG'));
                }
            } catch (\Exception $ex) {
                return $this->failJSON($ex->getMessage());
            }
        } else {
            return $this->failJSON(lang('USER_REQUEST'));
        }
    }

    private function balaceAdd($user_id,$type,$balanceM){        
        $balance = array(
            'user_id'   => $user_id,
            'type'  => $type,
            'update_time'   => NOW_DATETIME,
        );
        $balanceM->save($balance);
        
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

            $data['region_code'] = $this->_post('region_code');
            $data['phone'] = $this->_post('phone');
            $data['verify_code'] = $this->_post('verify_code');
            $password = $this->_post('password');
            $pay_password = $this->_post('pay_password');
//            $data['username'] = $this->_post('username');
//            if ($password != $password1) {
//                return $this->failJSON('两次输入的密码不一致');
//            }
            if (strlen($password) < 8) {
                return $this->failJSON(lang('USER_PASSWORD_LESS'));
            }
            if (strlen($pay_password) != 6) {
                return $this->failJSON(lang('USER_PAY_PASSWORD_LESS'));
            }
            $data['id'] = $this->_post('phone');
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
                return $this->failJSON(lang('USER_PHONE_ALREADY'));
            }
            $m->startTrans();
            try {
                $verifyM = new \addons\member\model\VericodeModel();
                $phone = $data['region_code'].$data['phone'];
                $_verify = $verifyM->VerifyCode($data['verify_code'], $phone);
                if(empty($_verify))
                {
                    $m->rollback();
                    return $this->failJSON(lang('USER_VERI_CODE'));
                }
                $inviter_id = $this->_post('inviter_id');
                if (!empty($inviter_id)) {
                    //获取邀请者id
                    $invite_user = $m->getDetail($inviter_id);
                    // $invite_user = $m->getUserByPhone($inviter_id);
                    if (!empty($invite_user)) {
                        $data['pid'] = $inviter_id; //邀请者id
                    } else {
                        return $this->failJSON(lang('USER_INVITER'));
                    }
                }
                $data['register_time'] = NOW_DATETIME;
                $user_id = $m->add($data); //用户id
                $balanceM = new Balance();
                for($i = 1; $i <= 5; $i++)
                {
                    $balance = array(
                        'user_id'   => $data['phone'],
                        'type'  => $i,
                        'update_time'   => NOW_DATETIME,
                    );

                    $balanceM->save($balance);
                }
                $m->commit();
                return $this->successJSON(lang('USER_REGISTER_SUC'));
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
            return $this->failJSON(lang('USER_REQUEST'));
        }
    }

    /**
     * 短信验证
     */
    public function getVerifyCode(){
        $phone = $this->_post('phone');
//        $time = $this->_post('time');
        $type = $this->_post('type');
        $region_code = $this->_post('region_code');
        $time = 120;
        if(empty($type))
            $type = 1;//注册验证码
        $m = new \addons\member\model\VericodeModel();
        $unpass_code = $m->hasUnpassCode($phone,$type);
        if(!empty($unpass_code)){
            return $this->failJSON(lang('USER_VERI_NOT'));
        }
        $memberM = new MemberAccountModel();
        $member = $memberM->getDetail($this->user_id);
        if(empty($region_code))
            $region_code = $member['region_code'];
        try{
            //发送验证码
            if($region_code)
                $phone = $region_code . $phone;
            $res = \addons\member\utils\Sms::send($phone);
            if($res['success']){
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
                return $this->successJSON();
            }else{
                return $this->failJSON($res['message']);
            }  
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
        $nodeS = new NodeService();

        $user = $userM->getDetail($this->user_id);

        if(empty($user))
        {
            return $this->failJSON(lang('USER_EXISTS'));
        }

        $balance = array();
        $balance_data = $balanceM->where('user_id',$this->user_id)->select();
        foreach ($balance_data as $v)
        {
            switch ($v['type'])
            {
                case 1:
                    $balance['total_amount'] = sprintf("%.2f",$v['amount']);    break;
                case 2:
                    $balance['use_amount'] = sprintf("%.2f",$v['amount']); break;
                case 3:
                    $balance['lock_amount'] = sprintf("%.2f",$v['amount']);    break;
                case 4:
                    $balance['key_amount'] = sprintf("%.2f",$v['amount']);     break;
                case 5:
                {
                    $amount = $nodeS->getDayNodeCount($this->user_id);
                    $balance['today_amount'] = sprintf("%.2f",$amount);   break;
                }

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
            $region_code = $this->_post('region_code');
            $region_phone = $region_code.$phone;

            if($password != $password1){
                return $this->failJSON(lang('USER_TWO_PASS'));
            }

            if (strlen($password) < 8) {
                return $this->failJSON(lang('USER_PASSWORD_LESS'));
            }
            $verifyM = new \addons\member\model\VericodeModel();
            $_verify = $verifyM->VerifyCode($auth_code, $region_phone,2);
            if(empty($_verify))
            {
                return $this->failJSON(lang('USER_VERI_WRONG'));
            }

            $password = md5($password);
            $m = new \addons\member\model\MemberAccountModel();
//            $user_id = $m->getUserByPhone($phone);
            $m->updatePassByUserID($this->user_id, $password,2);
            return $this->successJSON();
        }
    }

    /**
     * 修改交易密码
     * @return type
     */
    public function modifyPayPass(){
        if(IS_POST){
            $auth_code = $this->_post('auth_code');
            $password = $this->_post('pay_password');
            $password1 = $this->_post('pay_password1');
            $phone  = $this->_post('phone');
            $region_code = $this->_post('region_code');
            $phone = $region_code.$phone;
            if($password != $password1){
                return $this->failJSON(lang('USER_TWO_PASS'));
            }

            if (strlen($password) !=6) {
                return $this->failJSON(lang('USER_PAY_PASSWORD_LESS'));
            }

            $verifyM = new \addons\member\model\VericodeModel();
            $_verify = $verifyM->VerifyCode($auth_code, $phone,4);
            if(empty($_verify))
            {
                return $this->failJSON(lang('USER_VERI_WRONG'));
            }

            $password = md5($password);
            $m = new \addons\member\model\MemberAccountModel();
            $res = $m->updatePassByUserID($this->user_id, $password,4);
            if($res > 0){
                return $this->successJSON();
            }else{
                return $this->failJSON(lang('USER_UPDATE_PAY_FAIL'));
            }
        }
    }

    /**
     * 修改手机号
     */
    public function modifyPhone(){
        $type = 5;
        $m = new \addons\member\model\MemberAccountModel();
        $phone = $this->_post('old_phone');
        if(IS_POST){
            $code = $this->_post('auth_code');
            $new_phone = $this->_post('new_phone');
            $new_phone1 = $this->_post('new_phone1');

            if($new_phone != $new_phone1)
                return $this->failJSON(lang('USER_TWO_PHONE'));

            if (!preg_match("/13[0-9]{1}\d{8}|15[0-9]\d{8}|188\d{8}/", $new_phone)) {
                //11为手机号, 匹配13[0-9]后8位 \d数字| 15[0-9]后8位数字 | 188 后8位数字
                return $this->failJSON(lang('USER_WRONG_PHONE'));
            }
            $verifyM = new \addons\member\model\VericodeModel();
            $_verify = $verifyM->VerifyCode($code, $phone, $type);
            if (!empty($_verify)) {
                $id = $m->where('id='.$this->user_id)->update(['phone' => $new_phone]);
                if ($id <= 0) {
                    return $this->failJSON(lang('USER_UPDATE_FIAL'));
                }
                return $this->successJSON(lang('USER_UPDATE_SUC'));
            } else {
                return $this->failJSON(lang('USER_VERI_WRONG'));
            }
        }
    }

    /**
     * 绑定银行卡
     */
    public function bindingBank()
    {
        $param = Request::instance()->post();

        $payM = new PayConfig();
        $data = $payM->where(['user_id' => $this->user_id, 'type' => 3])->find();
        $data['user_id'] = $this->user_id;
        $data['type'] = 3;
        if($param['account'])
            $data['account'] = $param['account'];
        if($param['name'])
            $data['name'] = $param['name'];
        if($param['bank_address'])
            $data['bank_address'] = $param['bank_address'];
        if($param['bank_name'])
            $data['bank_name'] = $param['bank_name'];
        $data['update_time'] = NOW_DATETIME;

        $payM->save($data);
        return $this->successJSON();
    }

    /**
     * 完善资料
     */
    public function setUserInfo($real_name = null,$id_face = null,$id_back = null,$wechat = null,$alipay = null)
    {
        $userM = new MemberAccountModel();
        $payM  = new PayConfig();
        $user_data = array();

        if($real_name)
            $user_data['real_name'] = $real_name;
        try
        {
            if($wechat)
            {
                $pay_wechat = $payM->where(['user_id' => $this->user_id, 'type' => 1])->find();
                $pay_wechat['user_id'] = $this->user_id;
                $pay_wechat['type'] = 1;
                $pay_wechat['account'] = $wechat;
                $pay_wechat['update_time'] = NOW_DATETIME;
                $payM->save($pay_wechat);
            }

            if($alipay)
            {
                $pay_alipay = $payM->where(['user_id' => $this->user_id, 'type' => 2])->find();
                $pay_alipay['user_id'] = $this->user_id;
                $pay_alipay['type'] = 2;
                $pay_alipay['account'] = $alipay;
                $pay_alipay['update_time'] = NOW_DATETIME;
                $payM->save($pay_alipay);
            }

            $userM->save($user_data,['id' => $this->user_id]);

            return $this->successJSON();
        }catch (\Exception $e)
        {
            return $this->failJSON($e->getMessage());
        }
    }

    /**
     * 获取用户资料
     */
    public function getUserData()
    {
        $userM = new MemberAccountModel();
        $user = $userM->getDetail($this->user_id);

        $data = array(
            'head_img'  => $user['head_img'],
            'id_face'   => $user['id_face'],
            'id_back'   => $user['id_back'],
            'real_name' => $user['real_name'],
            'is_auth'   => $user['is_auth'],
            'update_name'   => $user['update_name'],
            'update_wechat' => $user['update_wechat'],
            'update_alipay' => $user['update_alipay'],
            'update_bank'   => $user['update_bank'],
            'wechat'    => '',
            'alipay'    => '',
            'name'      => '',
            'bank_account'  => '',
            'bank_name' => '',
        );

        $payM = new PayConfig();
        $pay = $payM->where('user_id',$this->user_id)->select();
        if($pay)
        {
            foreach ($pay as $v) {
                switch ($v['type'])
                {
                    case 1:
                    {
                        $data['wechat'] = $v['account'];
                        break;
                    }
                    case 2:
                    {
                        $data['alipay'] = $v['account'];
                        break;
                    }
                    case 3:
                    {
                        $data['name'] = $v['name'];
                        $data['bank_account'] = $v['account'];
                        $data['bank_address'] = $v['bank_address'];
                        $data['bank_name'] = $v['bank_name'];
                        break;
                    }
                }
            }
        }

        return $this->successJSON($data);
    }

    /**
     * 留言
     */
    public function leave()
    {
        $param = Request::instance()->post();
        $validate = new Validate([
            'issue' => 'require'
        ]);

        if(!$validate->check($param))
            return $this->failJSON($validate->getError());

        $data = array(
            'user_id'   => $this->user_id,
            'issue'     => $param['issue'],
            'create_time'   => NOW_DATETIME,
            'update_time'   => NOW_DATETIME,
        );

        $leaveM = new Leave();
        $leaveM->save($data);
        return $this->successJSON();
    }

    /**
     * 获取留言记录
     */
    public function getLeaveRecord()
    {
        $param = Request::instance()->post();

        $conf = array(
            'page'  => empty($param['page']) ? 1 : $param['page'],
            'list_rows' => empty($param['list_rows']) ? 5 : $param['list_rows']
        );

        $leaveM = new Leave();
        $filter = " user_id = $this->user_id";
        $fields = "issue,reply,create_time";
        $data = $leaveM->getDataList2($conf['page'],$conf['list_rows'],$filter,$fields,'create_time desc');

        return $this->successJSON($data);
    }

    /**
     * 节点社区
     */
    public function nodeCommunity()
    {
        set_time_limit(0);
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON(lang('COMMON_LOGIN'));

        $redis = \think\Cache::connect(\think\Config::get('global_cache'));
        $data = $redis->get('node'.$user_id);
        if(!$data){
            $memberS = new \web\api\service\MemberService();

            $users = $memberS->getTreeTeam($user_id,[],0);
            $userid = $memberS->getTreeId($user_id,[]);
            $memberNodeM = new MemberNode();
            $node_arr = array();
            foreach ($users as $v)
            {
                $user_node = $memberNodeM->where('user_id',$v['user_id'])->column('type');
                if(empty($user_node))
                    continue;
                $temp = array(
                    'node' => $user_node,
                    'user' => $v
                );
                $node_arr[] = $temp;
            }
            $user_node_arr = $memberNodeM->group('type')->column('type');
            for ($i=1; $i <9 ; $i++) { 
                $node = 'node'.$i;
                $$node = [];
                $arr = 'arr'.$i;
                $$arr = [];
            }
            foreach ($node_arr as $v)
            {
                foreach ($v['node'] as $n)
                {
                    switch ($n)
                    {
                        case 1:
                            $arr1[] = $v['user'];  break;
                        case 2:
                            $arr2[] = $v['user'];  break;
                        case 3:
                            $arr3[] = $v['user'];  break;
                        case 4:
                            $arr4[] = $v['user'];  break;
                        case 5:
                            $arr5[] = $v['user'];  break;
                        case 6:
                            $arr6[] = $v['user'];  break;
                        case 7:
                            $arr7[] = $v['user'];  break;
                        case 8:
                            $arr8[] = $v['user'];  break;
                    }
                }
            }
            foreach ($user_node_arr as $key => $value) {
                $map['user_id'] = ['in',$userid];
                $map['type'] = $value;
                $node = 'node'.$value;
                $count = $memberNodeM->where($map)->count();
                $$node['count'] = $count;
            }

            for ($i=1; $i <9 ; $i++) { 
                $node = 'node'.$i;
                $arr = 'arr'.$i;
                $$node['list'] = $$arr;
            }
            $data = array($node8,$node7,$node6,$node5,$node4,$node3,$node2,$node1);
            $redis->set('node'.$user_id, json_encode($data),86400);
        }

        return $this->successJSON($data);
    }

    /**
     * 奖励社区
     */
    public function awardCommunity()
    {

        set_time_limit(0);
        $user_id = $this->user_id;
        if(!$user_id) return $this->failJSON(lang('COMMON_LOGIN'));
        $redis = \think\Cache::connect(\think\Config::get('global_cache'));
        $data = $redis->get('award'.$user_id);
        if(!$data){

            $userM = new MemberAccountModel();
            $user = $userM->getDetail($user_id);
            $pOne = null;
            $pTwo = null;
            $pThree = null;
            $one_ids = '';
            $two_ids = '';
            $three_ids = '';

            if($user)
            {
                $pOne = $userM->where(['pid' => $user['id'], 'is_auth' => 1])->column('id');
            }

            $user2 = array();
            if($pOne)
            {
                foreach ($pOne as $v)
                {
                    $temp = $userM->where(['pid' => $v, 'is_auth' => 1])->column('id');
                    $pTwo[] = $temp;
                    $one_ids .= $v . ',';
                }

                foreach ($pTwo as $p)
                    foreach ($p as $i)
                        array_push($user2,$i);
            }

            $user3 = array();
            if($user2)
            {
                foreach ($user2 as $v)
                {
                    $temp = $userM->where(['pid' => $v, 'is_auth' => 1])->column('id');
                    $pThree[] = $temp;
                    $two_ids .= $v . ',';
                }

                foreach ($pThree as $p)
                    foreach ($p as $i)
                        array_push($user3,$i);
            }

            if($user3)
            {
                foreach ($user3 as $v)
                {
                    $three_ids .= $v . ',';
                }
            }

            $memberS = new MemberService();
            $team_users = $memberS->getTeamId($user_id);
            $team_user_ids = '';
            foreach ($team_users as $v)
            {
                $team_user_ids .= $v['user_id'] . ',';
            }

            $recordM = new TradingRecord();

            $total_amount = $recordM->where(['user_id' => array('in',$team_user_ids), 'type' => array('in','3,5')])->sum('amount');
            $one_amount = $recordM->where(['user_id' => array('in',$one_ids), 'type' => 3])->sum('amount');
            $two_amount = $recordM->where(['user_id' => array('in',$two_ids), 'type' => 3])->sum('amount');
            $three_amount = $recordM->where(['user_id' => array('in',$three_ids), 'type' => 3])->sum('amount');
            $total = array(
                'total' => array(
                    'total_num' => count($team_users),
                    'total_amount'  => $total_amount,
                ),
                'one'   => array(
                    'people_num'    => count($pOne),
                    'amount'        => $one_amount,
                ),
                'two'   => array(
                    'people_num'    => count($user2),
                    'amount'        => $two_amount,
                ),
                'three' => array(
                    'people_num'    => count($user3),
                    'amount'        => $three_amount,
                )
            );

            $one = $userM->field('real_name,phone')->where('id','in',$one_ids)->select();
            $two = $userM->field('real_name,phone')->where('id','in',$two_ids)->select();
            $three = $userM->field('real_name,phone')->where('id','in',$three_ids)->select();
            $data = array(
                'total' => $total,
                '1'   => $one,
                '2'   => $two,
                '3'   => $three,
            );
            $redis->set('award'.$user_id, json_encode($data),86400);
        }
    
        return $this->successJSON($data);
    }

    /**
     * 用户信息修改验证
     */
    public function modifyAuth()
    {
        $param = Request::instance()->post();

        $userM = new MemberAccountModel();
        $user = $userM->getDetail($this->user_id);

        if($param['type'] == 2)
        {
            $verifyM = new \addons\member\model\VericodeModel();
            $_verify = $verifyM->VerifyCode($param['auth_code'], $user['region_code'].$user['phone'],7);
            if(empty($_verify))
            {
                return $this->failJSON('验证码失效，请重新获取');
            }

            return $this->successJSON();
        }else
        {
            $pay_password = md5($param['pay_password']);
            if($pay_password != $user['pay_password'])
                return $this->failJSON('密码错误');

            return $this->successJSON();
        }
    }

    private function getRand($num=6)
    {
        $number = '';
        for($i = 0; $i < $num; $i++)
        {
            $number .= rand(1,9);
        }

        return $number;
    }

}



















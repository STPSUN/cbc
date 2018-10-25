<?php
/**
 * Created by sublime.
 * User: zhuangminghan 
 * Date: 2018/10/22
 * 交易
 */

namespace web\api\controller;


class Transfer extends ApiBase
{

    /**
     * 时间段禁止
     */
    public function forbiddenTime($sysM){
        $time_start = strtotime(date('Y-m-d ').$sysM->getValByName('time_start'));
        $time_end = strtotime(date('Y-m-d ').$sysM->getValByName('time_end'));
        if(!(time()>=$time_start&&time()<=$time_end)) return $this->failJSON('当前时间禁止交易');
    }

    /**
     * 判断支付密码是否正确
     */
    public function checkPwd($user_id,$pay_password){
        $pay_password = md5($pay_password);
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->getDetail($user_id);
        if($user['pay_password'] != $pay_password){
            return $this->failJSON('支付密码错误');
        }
        return $user;
    }
    /**
     * 获取今日行情
     */
    public function getToday(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');
        $m = new \addons\config\model\Quotation();
        $data = $m->field('price_now,price_top top,price_low low,create_at')->order('id desc')->find();
        return $this->successJSON($data);
    }
    /**
     * 创建订单编号
     */
    public function createOrderNumber(){
        return 'CBC'.date('ymdHis').rand(0,9).rand(0,9).rand(0,9);
    }

    /**
     * 挂卖
     * @param user_id int
     * @param number float 数量
     */
    public function sellOut(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');
        $pay_password = $this->_post('pay_password');
        $user = $this->checkPwd($user_id,$pay_password);
        if($user['is_auth']!=1)  return $this->failJSON('没有实名认证无法挂卖');
        $m = new \addons\config\model\Quotation();
        $data = $m->field('price_now,price_top top,price_low low,create_at')->order('id desc')->find();
        $top = $data['top'];
        $low = $data['low'];
        $sysM = new \web\common\model\sys\SysParameterModel();
        $this->forbiddenTime($sysM);
        $number = $this->_post('number');
        if($number<=0) return $this->failJSON('请输入正确的挂卖数量');
        $price = $this->_post('price');
        $code = $this->_post('code');
        $verifyM = new \addons\member\model\VericodeModel();
        $_verify = $verifyM->VerifyCode($code, $user['phone'],6);
        if(empty($_verify)) return $this->failJSON('验证码失效,请重新发送');
        if($price>$top)  return $this->failJSON('价格大于今日最高价');
        if($price<$low)  return $this->failJSON('价格小于今日最低价');
        $amount = bcmul($number, $price,4);
        if($amount<=0) return $this->failJSON('请输入正确的挂卖金额');
        $payM = new \addons\member\model\PayConfig();
        $paylist = $payM->getUserPay($user_id);
        if(!$paylist)  return $this->failJSON('没有设置支付方式，请设置');
        $rate = $sysM->getValByName('is_deal_tax')?$sysM->getValByName('deal_tax'):0;
        $fee_num = bcmul($number,($rate/100),2);
        $total = $number+$fee_num;
        $balanceM = new \addons\member\model\Balance();
        $coin_id = 2;//CBC
        $userAmount = $balanceM->getBalanceByType($user_id,$coin_id);
        if($number>$userAmount['amount']) return $this->failJSON('你的CBC余额少于'.$number);
        try{
            $balanceM->startTrans();
            $userAmount = $balanceM->updateBalance($user_id,$coin_id,$number);
            if(!$userAmount){
                $balanceM->rollback();
                return $this->failJSON('减少CBC余额失败');
            }

            $type = 6;
            $change_type = 0; //减少
            $remark = '用户挂卖，减少可用';
            $recordM = new \addons\member\model\TradingRecord();
            $r_id = $recordM->addRecord($user_id, $number, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
            if(!$r_id){
                $balanceM->rollback();
                return $this->failJSON('增加记录失败');
            }

            $coin_id = 1;//CBC总额
            $userAmount = $balanceM->updateBalance($user_id,$coin_id,$total);
            if(!$userAmount){
                $balanceM->rollback();
                return $this->failJSON('增加CBC锁仓失败');
            }
            $type = 6;
            $change_type = 0; //减少
            $remark = '用户挂卖，减少总额';
            $recordM = new \addons\member\model\TradingRecord();
            $r_id = $recordM->addRecord($user_id, $total, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
            if(!$r_id){
                $balanceM->rollback();
                return $this->failJSON('增加记录失败');
            }

            $coin_id = 3;//CBC
            $userAmount = $balanceM->updateBalance($user_id,$coin_id,$total,1);
            if(!$userAmount){
                $balanceM->rollback();
                return $this->failJSON('增加CBC锁仓失败');
            }
            $type = 6;
            $change_type = 1; //增加
            $remark = '用户挂卖，增加锁仓';
            $recordM = new \addons\member\model\TradingRecord();
            $r_id = $recordM->addRecord($user_id, $total, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
            if(!$r_id){
                $balanceM->rollback();
                return $this->failJSON('增加记录失败');
            }

            $data = [
                'user_id' =>$user_id,
                'order_id'=>$this->createOrderNumber(),
                'to_user_id'=>0,
                'type'=>0,
                'number'=>$number,
                'price'=>$price,
                'amount'=>$amount,
                'fee_num'=>$fee_num,
                'status'=>0,
                'update_time'=>NOW_DATETIME,
                'create_at'=>date('Y-m-d H:i:s')
            ];
            $tradingM = new \addons\member\model\Trading();
            $res = $tradingM->add($data);
            if($res){
                $balanceM->commit();
                return $this->successJSON('挂卖成功');
            }else{
                $balanceM->rollback();
                return $this->failJSON('挂卖失败');
            }
        }catch(\Exception $e){
            return $this->successJSON($e->getMessage());
        }
            
    }

    /**
     * 买入
     * @param trad_id int
     */
    public function purchaseOrder(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');
        $tradingM = new \addons\member\model\Trading();
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON('请选择正确的订单');
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON('订单不存在');
        if($trading['status']!=0) return $this->failJSON('不能买入买单');
        if($user_id==$trading['user_id']) return $this->failJSON('不能买入自己挂卖的订单');
        if($trading['type']==1) return $this->failJSON('订单已买入');
        $pay_password = $this->_post('pay_password');
        $this->checkPwd($user_id,$pay_password);
        $data = [
                'to_user_id' =>$user_id,
                'type'=>1,
                'update_time'=>NOW_DATETIME,
        ];
        $tradingM = new \addons\member\model\Trading();
        $res = $tradingM->where(['id'=>$trad_id])->update($data);
        if($res){
            return $this->successJSON('买入成功');
        }else{
            return $this->failJSON('买入失败');
        }
    }

    /**
     * 提交付款凭证
     * @param trad_id int
     * @param file base64
     */
    public function referOrder(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');
        $tradingM = new \addons\member\model\Trading();
        try{
            $trad_id = $this->_post('trad_id');
            if($trad_id<=0) return $this->failJSON('请选择正确的订单');
            $trading = $tradingM->findTrad($trad_id);
            if(!$trading) return $this->failJSON('订单不存在');
            if($user_id!=$trading['to_user_id']) return $this->failJSON('该订单不是您的订单');
            if($trading['type']!=1) return $this->failJSON('订单状态错误');
            $pay_password = $this->_post('pay_password');
            $this->checkPwd($user_id,$pay_password);

            $qrcode = $this->_post('file');
            $savePath = 'transaction/proof/'.$user_id.'/';
            $data = $this->base_img_upload($qrcode, $user_id, $savePath);
            if(!$data['success']) return $this->failJSON('上传付款凭证失败');
            $trading['type'] = 2;
            $trading['voucher'] = $data['path'];
            $trading['update_time'] = NOW_DATETIME;
            $res = $tradingM->save($trading);
            if(!$res) return $this->failJSON('订单保存失败');
            return $this->successJSON('上传打款凭证成功');
        }catch(\Exception $e){
            return $this->successJSON($e->getMessage());
        }  
    }


    /**
     * 确认收款
     * @param trad_id int 
     * @return pay_password string 
     */
    public function ConfirmOrder(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');
        $tradingM = new \addons\member\model\Trading();
        $userM = new \addons\member\model\MemberAccountModel();
        $pay_password = $this->_post('pay_password');
        $user = $this->checkPwd($user_id,$pay_password);
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON('请选择正确的订单');
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON('订单不存在');
        if($user_id!=$trading['user_id']) return $this->failJSON('该订单不是您的订单');
        if($trading['type']!=2) return $this->failJSON('订单状态错误');
        try{
            $balanceM = new \addons\member\model\Balance();
            $balanceM->startTrans();
            $trading['type'] = 3;
            $trading['update_time'] = NOW_DATETIME;
            $res = $tradingM->save($trading);
            if(!$res){
                $balanceM->rollback();
                return $this->failJSON('订单保存失败');
            }
            $coin_id = 4;//CBC余额
            $userAmount = $balanceM->updateBalance($trading['to_user_id'],$coin_id,$trading['number'],1);
            if(!$userAmount){
                $balanceM->rollback();
                return $this->failJSON('增加CBC失败');
            }

            $type = 7;
            $change_type = 1; //增加
            $remark = '确认收款-用户增加激活码';
            $recordM = new \addons\member\model\TradingRecord();
            $r_id = $recordM->addRecord($trading['to_user_id'], $trading['number'], $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type,$user_id ,$remark);
            if(!$r_id){
                $balanceM->rollback();
                return $this->failJSON('增加记录失败');
            }

            //删除锁仓金额
            $coin_id = 3;//CBC
            $amount = bcmul(($trading['fee_num']+$trading['number']), 1,2);
            $userAmount = $balanceM->getBalanceByType($user_id,$coin_id);
            if($amount>$userAmount['amount']){
                $amount = $userAmount['amount'];
            }

            $userAmount = $balanceM->updateBalance($user_id,$coin_id,$amount);
            if(!$userAmount){
                $balanceM->rollback();
                return $this->failJSON('减少CBC锁仓失败');
            }
            $type = 7;
            $change_type = 0; //减少
            $remark = '确认收款-用户减少CBC锁仓';
            $recordM = new \addons\member\model\TradingRecord();
            $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, $user_id,$remark);
            if(!$r_id){
                $balanceM->rollback();
                return $this->failJSON('增加记录失败');
            }
            $AwardService = new \web\api\service\AwardService();
            $res = $AwardService->tradingReward($trading['fee_num'],$trading['user_id']);
            //计算奖金
            if(!$res){
                $balanceM->rollback();
                return $this->failJSON('奖金发放失败');
            }
            $balanceM->commit();
            return $this->successJSON('确认收款成功');
        }catch(\Exception $e){
            return $this->successJSON($e->getMessage());
        }
    }

    /**
     * 订单列表
     */
    public function orderList(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');
        $tradingM = new \addons\member\model\Trading();
        $row = $this->_post('row')?$this->_post('row'):20;
        $page = $this->_post('page')?$this->_post('page')*$row:0;
        if($this->_post('status')){
            $map['status'] = 1;
        }else{
            $type = $this->_post('type');
            if($type==2){
                $map['type'] = ['in',[1,2]];
            }elseif($type==3){
                $map['type'] = 3;
            }else{
                $map['type'] = 0;
            }
            $map['user_id|to_user_id'] = $user_id;
        }
        $list = $tradingM->getOrderList($map,$page,$row);
        foreach ($list as $key => $value) {
            if($value['user_id']==$user_id){
                $list[$key]['pay_type'] = 1;
            }else{
                $list[$key]['pay_type'] = 0;
            }
            $count = $tradingM->where(['user_id'=>$value['user_id']])->count();
            $list[$key]['count'] = $count;
        }
        $this->successJSON($list);
    }

    /**
     * 交易大厅
     * 每行15个超过3页为空
     */
    public function TradingHall(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');

        $sort = $this->_post('sort_type');
        if($sort==1){
            $map['amount'] = ['lt',100];
        }elseif($sort==2){
            $map['amount'] = ['between',[100,1000]];
        }elseif($sort==3){
            $map['amount'] = ['gt',1000];
        }

        $pay_type = $this->_post('pay_type');
        if($pay_type==1){
            $map['p.type'] = 1;
        }elseif($pay_type==2){
            $map['p.type'] = 2;
        }elseif($pay_type==3){
            $map['p.type'] = 3;
        }

        $order = 'id desc';
        $level_type = $this->_post('level_type');
        if($level_type==1){
            $order = 'credit_level desc';
        }elseif($level_type==2){
            $order = 'credit_level asc';
        }
        $map['type'] = 0;
        $type = $this->_post('type')?$this->_post('type'):15;
        $tradingM = new \addons\member\model\Trading();
        $row = $this->_post('row')?$this->_post('row'):15;
        $page = $this->_post('page')?$this->_post('page')*$row:0;
        if($this->_post('page')>=3){
            return $this->successJSON();
        }
        $list = $tradingM->getOrderList($map,$page,$row,$order);
        foreach ($list as $key => $value) {
            if($value['user_id']==$user_id){
                $list[$key]['pay_type'] = 1;
            }else{
                $list[$key]['pay_type'] = 0;
            }
            $count = $tradingM->where(['user_id'=>$value['user_id']])->count();
            $list[$key]['count'] = $count;
        }
        $this->successJSON($list);
    }

    /**
     * 获取外网行情
     */
    public function getCoinInfo(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');
        $payM = new \addons\member\model\PayConfig();
        $redis = \think\Cache::connect(\think\Config::get('global_cache'));
        $arr = $redis->get('hotapi_price');
        if(!$arr){
            $sysM = new \web\common\model\sys\SysParameterModel();
            $price = $sysM->getValByName('usdt_price');
            $type = $this->_post('type');
            $HotApi = new \web\common\utils\HotApi();
            $list = ['btcusdt','ethusdt','xrpusdt','neousdt','eosusdt'];
            $arr = [];
            foreach ($list as $key => $value) {
                $info = $HotApi->get_detail_merged($value);
                if($info['success']&&$info['data']['status']=='ok'){
                    $tmp['price'] = bcmul($price, $info['data']['tick']['close'],2);
                    $tmp['difference'] = bcmul($price, ($info['data']['tick']['close']-$info['data']['tick']['open']),2);
                    if($info['data']['tick']['close']-$info['data']['tick']['open']>0){
                        $tmp['type'] = 1;
                    }else{
                        $tmp['type'] = 0;
                    }
                    if($value=='btcusdt'){
                        $tmp['name'] = 'BTC';
                    }elseif($value=='ethusdt'){
                        $tmp['name'] = 'ETH';
                    }elseif($value=='xrpusdt'){
                        $tmp['name'] = 'XRP';
                    }elseif($value=='neousdt'){
                        $tmp['name'] = 'NEO';
                    }elseif($value=='eosusdt'){
                        $tmp['name'] = 'EOS';
                    }
                    $arr[] = $tmp;
                }
            }
            $tmp['type'] = 1;
            $tmp['price'] = $price;
            $tmp['difference'] = 0;
            $tmp['name'] = 'USDT';
            $arr[] = $tmp;
            $redis->set('hotapi_price', json_encode($arr), 60);
        }
        $this->successJSON($arr);
    }
    /**
     * 订单详情
     * @param trad_id int 
     * @return pay_password string 
     */
    public function orderDetail(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');
        $tradingM = new \addons\member\model\Trading();
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON('请选择正确的订单');
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading)  return $this->failJSON('该订单不存在');
        if(!($user_id==$trading['user_id']||$user_id==$trading['to_user_id'])) return $this->failJSON('该订单不是您的订单');
        $userM = new \addons\member\model\MemberAccountModel();
        if($user_id==$trading['user_id']){
            $trading['pay_type'] = 1;
            $uid = $trading['to_user_id'];
        }else{
            $trading['pay_type'] = 0;
            $uid = $trading['user_id'];
        }
        $user = $userM->getDetail($uid);
        $list = $this->getUserPayList($uid);
        $trading['phone'] = $user['phone'];
        $tmp = [];
        foreach ($list as $key => $value) {
            if($value['type']==1){
                $tmp['wechat_number'] = $value['account']; 
                $tmp['wechat_name'] = $value['name']; 
                $tmp['wechat_qrcode'] = $value['qrcode']; 
            }elseif($value['type']==2){
                $tmp['alipay_number'] = $value['account']; 
                $tmp['alipay_name'] = $value['name']; 
                $tmp['alipay_qrcode'] = $value['qrcode']; 
            }elseif($value['type']==3){
                $tmp['bank_name'] = $value['bank_name']; 
                $tmp['bank_address'] = $value['bank_address']; 
                $tmp['bank_number'] = $value['account']; 
                $tmp['real_name'] = $value['name']; 
            }
        }
        $trading['pay'] = $tmp;
        $this->successJSON($trading);
    }

    /**
     * 买家获取订单数据
     */
    public function getSellInfo(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');
        $tradingM = new \addons\member\model\Trading();
        $userM = new \addons\member\model\MemberAccountModel();
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON('请选择正确的订单');
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON('订单不存在');
        $user = $userM->getDetail($trading['user_id']);
        $count = $tradingM->getCount(['user_id'=>$trading['user_id']]);
        $trading['phone'] = $user['phone'];
        $trading['username'] = $user['username'];
        $trading['head_img'] = $user['head_img'];
        $trading['is_auth'] = $user['is_auth'];
        $trading['order_count'] = $count;
        $this->successJSON($trading);
    }

    
    /**
     * 用户取消订单
     * @param trad_id int 
     * @return pay_password string 
     */
    public function cancleTrading(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');
        $tradingM = new \addons\member\model\Trading();
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->where(['id'=>$user_id])->find();
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON('请选择正确的订单');
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON('订单不存在');
        if($user_id==$trading['user_id']&&0==$trading['type']&&0==$trading['status']){
            try{
                $balanceM = new \addons\member\model\Balance();
                $balanceM->startTrans();
                $coin_id = 2;
                $amount = $trading['number'];
                $userAmount = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failJSON('增加CBC余额失败');
                }

                $type = 8;
                $change_type = 1; //增加
                $remark = '用户取消订单，增加可用';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failJSON('增加记录失败');
                }

                $coin_id = 1;//CBC
                $total = bcmul(($trading['number']+$trading['fee_num']), 1,2);
                $userAmount = $balanceM->updateBalance($user_id,$coin_id,$total,1);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failJSON('增加CBC总额失败');
                }
                $type = 8;
                $change_type = 1; //减少
                $remark = '用户取消订单，增加总额';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $total, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failJSON('增加记录失败');
                }

                $coin_id = 3;//CBC
                $total = bcmul(($trading['number']+$trading['fee_num']), 1,2);
                $userAmount = $balanceM->updateBalance($user_id,$coin_id,$total);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failJSON('减少CBC锁仓失败');
                }
                $type = 8;
                $change_type = 0; //减少
                $remark = '用户取消订单，减少锁仓';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $total, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failJSON('增加记录失败');
                }
                $trading['status'] = 1;
                $trading['update_time'] = NOW_DATETIME;
                $res = $tradingM->save($trading);
                if($res){
                    $balanceM->commit();
                    $this->successJSON('取消成功');
                }else{
                    $balanceM->rollback();
                    $this->failJSON('取消失败');
                } 
            }catch(\Exception $e){
                $this->failJSON($e->getMessage());
            }
        }else{
            // if(!($user_id==$trading['user_id']||$user_id==$trading['to_user_id'])) return $this->failJSON('该订单不是您的订单');
            // if($trading['type']==3||$trading['type']==2) return $this->failJSON('订单状态错误');
            // $pay_password = $this->_post('pay_password');
            // $pay_password = md5($pay_password);
            // $userM = new \addons\member\model\MemberAccountModel();
            // $user = $userM->getDetail($user_id);
            // if($user['pay_password'] != $pay_password) return $this->failJSON('支付密码错误');
            // $trading['type'] = 0;
            // $trading['to_user_id'] = 0;
            // $trading['update_time'] = NOW_DATETIME;
            // $res = $tradingM->save($trading);
            // if($res) $this->successJSON('取消成功');
            return $this->failJSON('订单错误');
        }
    }

    
    /**
     * 设置收款方式
     * @return type int
     * @return account string
     * @return name string
     * @return bank_address string
     * @return file base64
     */
    public function setPayConfig(){
        if(!IS_POST) return $this->failJSON('illegal request');
        $user_id = $this->user_id;
        $type = $this->_post('type');
        $account = $this->_post('account');
        $name = $this->_post('name');
        $bank_address = $this->_post('bank_address');
        $bank_name = $this->_post('bank_name');
        if(empty($user_id) || empty($type) || empty($account)){
            return $this->failJSON('missing arguments');
        }

        if($type == 3){
            if(empty($name) || empty($bank_address)){
                return $this->failJSON('姓名与开户行地址不能为空');
            }
            $data['bank_address'] = $bank_address;
            $data['bank_name'] = $bank_name;
            $data['name'] = $name;
        }
        try{
            $base64 = $this->_post('file');
            if($base64){
                $savePath = 'transaction/proof/'.$user_id.'/';
                $ret = $this->base_img_upload($base64, $user_id, $savePath);
                if(!$ret['success']){
                    return $this->failJSON($ret['message']);
                }
                $data['qrcode'] = $ret['path'];
            }
            $m = new \addons\member\model\PayConfig();
            $info = $m->where(['user_id'=>$user_id,'type'=>$type])->find();
            if($info){
                $where['user_id'] = $user_id;
                $where['id'] = $info['id'];
                $data['type'] = $type;
                $data['account'] = $account;
                $data['name'] = $name;
                $data['update_time'] = NOW_DATETIME;
                $ret = $m->where($where)->update($data);
            }else{
                $data['user_id'] = $user_id;
                $data['account'] = $account;
                $data['name'] = $name;
                $data['type'] = $type;
                $data['update_time'] = NOW_DATETIME;
                $ret = $m->add($data);
            }
            if($ret > 0){
                return $this->successJSON();
            }else{
                return $this->failJSON('添加或更新数据失败');
            }
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }


    /**
     * 获取收款方式
     * @return type
     */
    public function getUserPayAll(){
        $m = new \addons\member\model\PayConfig();
        $user_id = $this->user_id;
        if(empty($user_id)){
            return $this->failJSON('missing arguments');
        }
        try{
            $data = $m->getUserPay($user_id);
            return $this->successJSON($data);
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }

    /**
     * 发送通知卖家短信
     */
    public function sendSellMessage(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');
        $tradingM = new \addons\member\model\Trading();
        $userM = new \addons\member\model\MemberAccountModel();
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON('请选择正确的订单');
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON('订单不存在');
        if($trading['type']!=2) return $this->failJSON('订单状态错误');
        if($user_id!=$trading['to_user_id']) return $this->failJSON('该订单不是您的订单');
        $user = $userM->getDetail($trading['user_id']);
        $msg = '【CBC】尊敬的'.$user['real_name'].'先生/女士，您的订单在CBC系统出售成功，买家已经打款，请您在收到款之后去平台确认发货。';
        $res = \addons\member\utils\Sms::sendOrder($user['phone'],$msg);
        if($res['success']){
            return $this->successJSON();
        }else{
            return $this->failJSON($res['message']);
        }  
    }


    /**
     * 用户投诉
     */
    public function UserComplaint(){
        $user_id = $this->user_id;
        $user_id = 56;
        if($user_id <= 0) return $this->failData('请登录');
        $tradingM = new \addons\member\model\Trading();
        $userM = new \addons\member\model\MemberAccountModel();
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON('请选择正确的订单');
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON('订单不存在');
        if(!($user_id==$trading['to_user_id']||$user_id==$trading['user_id'])) return $this->failJSON('该订单不是您的订单');
        $content = $this->_post('content');
        if(!$content) return $this->failJSON('投诉内容不能为空');
        $data = [
            'user_id'=>$user_id,
            'trad_id'=>$trad_id,
            'content'=>$content,
        ];
        $TradingComplaint = new \addons\member\model\TradingComplaint();
        $res = $TradingComplaint->addComplaint($data);
        if($res) $this->successJSON('投诉成功');
        else $this->failJSON('投诉失败');

    }

    /**
     * 获取支付方式
     */
    private function getUserPayList($uid){
        $m = new \addons\member\model\PayConfig();
        return $m->getUserPay($uid);
    }

    /**
     * 定时器访问
     * 超过30分钟未付款则取消订单
     */
    public function cancleOrder(){
        $tradingM = new \addons\member\model\Trading();
        $map['type'] = 1;
        $map['status'] = 0;
        $map['update_time'] = ['gt',date('Y-m-d H:i:s',(time()+30*60))];
        $list = $tradingM->where($map)->select();
        if(!$list) return $this->failJSON('暂无订单');
        foreach ($list as $key => $value) {
            $list[$key]['to_user_id'] = 0;
            $list[$key]['type'] = 0;
            $list[$key]['update_time']=NOW_DATETIME;
        }
        $res = $tradingM->save($list);
        if($res) $this->successJSON('update success');
        else $this->failJSON('update failed');
    }

}
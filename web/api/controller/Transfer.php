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
        if(!(time()>=$time_start&&time()<=$time_end)) return $this->failJSON(lang('TRANSFER_TIME_BAN'));
    }

    /**
     * 判断支付密码是否正确
     */
    public function checkPwd($user_id,$pay_password){
        $pay_password = md5($pay_password);
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->getDetail($user_id);
        if($user['pay_password'] != $pay_password){
            return $this->failJSON(lang('TRANSFER_PAYPWD_WRONG'));
        }
        return $user;
    }
    /**
     * 获取今日行情
     */
    public function getToday(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
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
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
        $pay_password = $this->_post('pay_password');
        $user = $this->checkPwd($user_id,$pay_password);
        if($user['is_auth']!=1)  return $this->failJSON(lang('TRANSFER_NOT_AUTH'));
        // if($user['node_level']<1)  return $this->failJSON(lang('TRANSFER_NOT_NODE'));
        $m = new \addons\config\model\Quotation();
        $data = $m->field('price_now,price_top top,price_low low,create_at')->order('id desc')->find();
        $top = $data['top'];
        $low = $data['low'];
        $sysM = new \web\common\model\sys\SysParameterModel();
        $this->forbiddenTime($sysM);
        $tradingM = new \addons\member\model\Trading();
        $map['user_id'] = $user_id;
        $map['status'] = 0;
        $map['type'] = ['neq',3];
        $r = $tradingM->where($map)->find();
        if($r) return $this->failJSON(lang('TRANSFER_ALREADY'));
        //交易成功限制单数
        // $ma['update_time'] = ['between',[date('Y-m-d'),(date('Y-m-d').' 23:59:59')]];
        // $ma['status'] = 0;
        // $ma['user_id'] = $user_id;
        // $r = $tradingM->where($ma)->find();
        // if($r) return $this->failJSON(lang('TRANSFER_TODAY'));
        $number = $this->_post('number');
        if($number<50) return $this->failJSON(lang('TRANSFER_RIGHT_NUMBER'));
        if($number%50!=0) return $this->failJSON(lang('TRANSFER_RIGHT_NUMBER_50'));
        if($number>1000) return $this->failJSON(lang('TRANSFER_RIGHT_NUMBER_1000'));
        $price = $this->_post('price');
        $code = $this->_post('code');

        $verifyM = new \addons\member\model\VericodeModel();
        $_verify = $verifyM->VerifyCode($code, $user['region_code'].$user['phone'],6);
        if(empty($_verify)) return $this->failJSON(lang('TRANSFER_VERIGYCODE_WRONG'));

        if($price>$top)  return $this->failJSON(lang('TRANSFER_PLUS_TODAY'));
        if($price<$low)  return $this->failJSON(lang('TRANSFER_LESS_TODAY'));
        $amount = bcmul($number, $price,4);
        if($amount<=0) return $this->failJSON(lang('TRANSFER_RIGHT_AMOUNT'));
        $less_total = $sysM->getValByName('less_total');
        if($number>$less_total) return $this->failJSON(lang('TRANSFER_LESS_TOTAL'));
        $payM = new \addons\member\model\PayConfig();
        $paylist = $payM->getUserPay($user_id);
        if(!$paylist)  return $this->failJSON(lang('TRANSFER_NOT_SET_PAY'));
        $fee_num = bcmul(($number/7*3),1,2);
        $total = $number+$fee_num;
        $TransferM = new \addons\member\model\Transfer();
        $info = $TransferM->findData($user_id);
        if(!$info) return $this->failJSON(lang('TRANSFER_NOT_BUY'));
        if($info['power']!=1){
            if($number>$info['quota']) return $this->failJSON(lang('TRANSFER_NOT_QUOTA'));
            $time = date('Y-m-d');
            $ma['create_at'] = ['between',[$time.' 00:00:00',$time.' 23:59:59']];
            $ma['status'] = 0;
            $ma['user_id'] = $user_id;
            $r = $tradingM->where($ma)->count();
            if($r) return $this->failJSON(lang('TRANSFER_NOT_SELL'));
            // if($info['can_sell']==0) return $this->failJSON(lang('TRANSFER_NOT_SELL'));
        }

        $balanceM = new \addons\member\model\Balance();
        $sell_mode = $this->_post('sell_mode')?1:0;
        if($sell_mode==0){
            $coin_id = 2;//CBC
            $userAmount = $balanceM->getBalanceByType($user_id,$coin_id);
            if($number>$userAmount['amount']) return $this->failJSON(lang('TRANSFER_CBC_LESS').$number);
            try{
                $balanceM->startTrans();
                $userAmount = $balanceM->updateBalance($user_id,$coin_id,$number);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_CBC2_LESS'));
                }

                $type = 6;
                $change_type = 0; //减少
                $remark = '用户挂卖，减少可用';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $number, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
                }

                $coin_id = 1;//CBC总额
                $userAmount = $balanceM->updateBalance($user_id,$coin_id,$total);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_CBC1_LESS'));
                }
                $type = 6;
                $change_type = 0; //减少
                $remark = '用户挂卖，减少总额';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $total, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
                }

                $coin_id = 3;//CBC
                $userAmount = $balanceM->updateBalance($user_id,$coin_id,$total,1);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_CBC3_ADD'));
                }
                $type = 6;
                $change_type = 1; //增加
                $remark = '用户挂卖，增加锁仓';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $total, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
                }
                $less_total = $less_total-$number;
                $res = $sysM->setValByName('less_total',$less_total);
                if(!$res){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_TOTAL_FAIL'));
                }

                $res = $TransferM->updateQuota($user_id,$number);
                if(!$res){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_QUOTA_FAIL'));
                }
                $order_id = $this->createOrderNumber();
                $TradingLog = new \addons\member\model\TradingLog();
                $info = [
                    'order_id'=>$order_id,
                    'user_id'=>$user_id,
                    'remark'=>'用户挂卖',
                    'create_at'=>NOW_DATETIME,
                    'type'=>0,
                ];
                $TradingLog->add($info);

                $data = [
                    'user_id' =>$user_id,
                    'order_id'=>$order_id,
                    'to_user_id'=>0,
                    'type'=>0,
                    'number'=>$number,
                    'price'=>$price,
                    'amount'=>$amount,
                    'fee_num'=>$fee_num,
                    'trans_mode'=>0,
                    'status'=>0,
                    'update_time'=>NOW_DATETIME,
                    'create_at'=>date('Y-m-d H:i:s')
                ];
                $res = $tradingM->add($data);

                if($res){
                    $balanceM->commit();
                    return $this->successJSON(lang('TRANSFER_SELL_SUC'));
                }else{
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_SELL_FAIL'));
                }
            }catch(\Exception $e){
                return $this->successJSON($e->getMessage());
            }
        }else{
            $coin_id = 4;//激活码
            $userAmount = $balanceM->getBalanceByType($user_id,$coin_id);
            if($number>$userAmount['amount']) return $this->failJSON(lang('TRANSFER_CODE_LESS').$number);
            try{
                $balanceM->startTrans();
                $userAmount = $balanceM->updateBalance($user_id,$coin_id,$number);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_CBC4_LESS'));
                }

                $type = 6;
                $change_type = 0; //减少
                $remark = '用户挂卖，减少激活码';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $number, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
                }

                $less_total = $less_total-$number;
                $res = $sysM->setValByName('less_total',$less_total);
                if(!$res){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_TOTAL_FAIL'));
                }
                $res = $TransferM->updateQuota($user_id,$number);
                if(!$res){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_QUOTA_FAIL'));
                }
                $order_id = $this->createOrderNumber();
                $TradingLog = new \addons\member\model\TradingLog();
                $info = [
                    'order_id'=>$order_id,
                    'user_id'=>$user_id,
                    'remark'=>'用户挂卖',
                    'create_at'=>NOW_DATETIME,
                    'type'=>0,
                ];
                $TradingLog->add($info);

                $data = [
                    'user_id' =>$user_id,
                    'order_id'=>$order_id,
                    'to_user_id'=>0,
                    'type'=>0,
                    'trans_mode'=>1,
                    'number'=>$number,
                    'price'=>$price,
                    'amount'=>$amount,
                    'fee_num'=>$fee_num,
                    'status'=>0,
                    'update_time'=>NOW_DATETIME,
                    'create_at'=>date('Y-m-d H:i:s')
                ];



                $res = $tradingM->add($data);
                if($res){
                    $balanceM->commit();
                    return $this->successJSON(lang('TRANSFER_SELL_SUC'));
                }else{
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_SELL_FAIL'));
                }
            }catch(\Exception $e){
                return $this->failJSON($e->getMessage());
            }
        }
    }

    /**
     * 检查订单是否购买
     * @param trad_id int
     */
    public function checkBuyOrder(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
        $tradingM = new \addons\member\model\Trading();
        $TransferM = new \addons\member\model\Transfer();
        $info = $TransferM->findData($user_id);
        if($info['power']!=1){
            $time = date('Y-m-d');
            $map['update_time'] = ['between',[$time.' 00:00:00',$time.' 23:59:59']];
            $map['status'] = 0;
            $map['to_user_id'] = $user_id;
            $res = $tradingM->where($map)->count();
            if($res>100) return $this->failJSON(lang('TRANSFER_BUT_ALREADY_100'));
        }

        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON(lang('TRANSFER_RIGHT_ORDER'));
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON(lang('TRANSFER_ORDER_EXISTS'));
        if($user_id==$trading['user_id']) return $this->failJSON(lang('TRANSFER_BUY_OWN'));
        if($trading['type']!=0) return $this->failJSON(lang('TRANSFER_ALREADY_BUY'));
        if($trading['to_user_id']) return $this->failJSON(lang('TRANSFER_ALREADY_BUY'));
        if($trading['status']) return $this->failJSON(lang('TRANSFER_ALREADY_BUY'));
        return $this->successJSON();
    }

    /**
     * 买入
     * @param trad_id int
     */
    public function purchaseOrder(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
        $pay_password = $this->_post('pay_password');
        $this->checkPwd($user_id,$pay_password);
        $tradingM = new \addons\member\model\Trading();
        $TransferM = new \addons\member\model\Transfer();
        $datas = $TransferM->findData($user_id);
        if($datas['power']!=1){
            $time = date('Y-m-d');
            $map['update_time'] = ['between',[$time.' 00:00:00',$time.' 23:59:59']];
            $map['to_user_id'] = $user_id;
            $map['status'] = 0;
            $res = $tradingM->where($map)->count();
            if($res>100) return $this->failJSON(lang('TRANSFER_BUT_ALREADY_100'));
        }
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON(lang('TRANSFER_RIGHT_ORDER'));
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON(lang('TRANSFER_ORDER_EXISTS'));
        if($user_id==$trading['user_id']) return $this->failJSON(lang('TRANSFER_BUY_OWN'));
        if($trading['type']!=0) return $this->failJSON(lang('TRANSFER_ALREADY_BUY'));
        if($trading['to_user_id']) return $this->failJSON(lang('TRANSFER_ALREADY_BUY'));
        if($trading['status']) return $this->failJSON(lang('TRANSFER_ALREADY_BUY'));
        $TradingLog = new \addons\member\model\TradingLog();
        $info = [
            'order_id'=>$trading['order_id'],
            'user_id'=>$user_id,
            'remark'=>'用户买入',
            'create_at'=>NOW_DATETIME,
            'type'=>1,
        ];
        $TradingLog->add($info);
        $data = [
                'to_user_id' =>$user_id,
                'type'=>1,
                'update_time'=>NOW_DATETIME,
        ];
        $tradingM = new \addons\member\model\Trading();

        $res = $tradingM->where(['id'=>$trad_id])->update($data);
        if($res){
            return $this->successJSON(lang('TRANSFER_BUY_SUC'));
        }else{
            return $this->failJSON(lang('TRANSFER_BUY_FAIL'));
        }
    }

    /**
     * 提交付款凭证
     * @param trad_id int
     * @param file base64
     */
    public function referOrder(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
        $tradingM = new \addons\member\model\Trading();
        try{
            $trad_id = $this->_post('trad_id');
            if($trad_id<=0) return $this->failJSON(lang('TRANSFER_RIGHT_ORDER'));
            $trading = $tradingM->findTrad($trad_id);
            if(!$trading) return $this->failJSON(lang('TRANSFER_ORDER_EXISTS'));
            if($user_id!=$trading['to_user_id']) return $this->failJSON(lang('TRANSFER_NOT_YOUR'));
            if($trading['type']!=1) return $this->failJSON(lang('TRANSFER_WRONG_STATUS'));
            $pay_password = $this->_post('pay_password');
            $this->checkPwd($user_id,$pay_password);

            $qrcode = $this->_post('file');
            $savePath = 'transaction/proof/'.$user_id.'/';
            $data = $this->base_img_upload($qrcode, $user_id, $savePath);
            if(!$data['success']) return $this->failJSON(lang('TRANSFER_UPLOAD_VOUCHER_FAIL'));

            $TradingLog = new \addons\member\model\TradingLog();
            $info = [
                'order_id'=>$trading['order_id'],
                'user_id'=>$user_id,
                'remark'=>'用户上传付款凭证',
                'voucher'=>$data['path'],
                'create_at'=>NOW_DATETIME,
                'type'=>2,
            ];
            $TradingLog->add($info);

            $trading['type'] = 2;
            $trading['voucher'] = $data['path'];
            $trading['update_time'] = NOW_DATETIME;
            $res = $tradingM->save($trading);
            if(!$res) return $this->failJSON(lang('TRANSFER_SAVE_FAIL'));
            return $this->successJSON(lang('TRANSFER_SAVE_SUC'));
        }catch(\Exception $e){
            return $this->failJSON($e->getMessage());
        }  
    }


    /**
     * 确认收款
     * @param trad_id int 
     * @return pay_password string 
     */
    public function ConfirmOrder(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
        $tradingM = new \addons\member\model\Trading();
        $userM = new \addons\member\model\MemberAccountModel();
        $pay_password = $this->_post('pay_password');
        $user = $this->checkPwd($user_id,$pay_password);
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON(lang('TRANSFER_RIGHT_ORDER'));
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON(lang('TRANSFER_ORDER_EXISTS'));
        if($user_id!=$trading['user_id']) return $this->failJSON(lang('TRANSFER_NOT_YOUR'));
        if($trading['type']!=2) return $this->failJSON(lang('TRANSFER_WRONG_STATUS'));
        $sysM = new \web\common\model\sys\SysParameterModel();
        $rate = $sysM->getValByName('is_buy_tax')?$sysM->getValByName('buy_tax'):0;
        $balanceM = new \addons\member\model\Balance();
        $balanceM->startTrans();
        $number = bcmul(($trading['number'] + $trading['number']*$rate/100), 1,2);
        if($trading['trans_mode']==1){
            try{
                $trading['type'] = 3;
                $trading['update_time'] = NOW_DATETIME;
                $res = $tradingM->save($trading);
                if(!$res){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_SAVE_FAIL'));
                }
                $coin_id = 4;//CBC余额
                $userAmount = $balanceM->updateBalance($trading['to_user_id'],$coin_id,$number,1);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_CBC3_ADD'));
                }

                $type = 7;
                $change_type = 1; //增加
                $remark = '确认收款-用户增加激活码';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($trading['to_user_id'], $number, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type,$user_id ,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
                }

            }catch(\Exception $e){
                return $this->failJSON($e->getMessage());
            }
        }else{
            try{
                $trading['type'] = 3;
                $trading['update_time'] = NOW_DATETIME;
                $res = $tradingM->save($trading);
                if(!$res){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_SAVE_FAIL'));
                }
                $coin_id = 4;//CBC余额
                $userAmount = $balanceM->updateBalance($trading['to_user_id'],$coin_id,$number,1);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_CBC3_ADD'));
                }

                $type = 7;
                $change_type = 1; //增加
                $remark = '确认收款-用户增加激活码';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($trading['to_user_id'], $number, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type,$user_id ,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
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
                    return $this->failJSON(lang('TRANSFER_CBC3_LESS'));
                }
                $type = 7;
                $change_type = 0; //减少
                $remark = '确认收款-用户减少CBC锁仓';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, $user_id,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
                }
                $AwardService = new \web\api\service\AwardService();
                $res = $AwardService->tradingReward($trading['fee_num'],$trading['user_id']);
                //计算奖金
                if(!$res){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_REWARD_FAIL'));
                }
            }catch(\Exception $e){
                return $this->failJSON($e->getMessage());
            }
        }

        // $TransferM = new \addons\member\model\Transfer();
        // $result = $TransferM->findData($user_id);
        // if($result['can_sell']>0) $result['can_sell'] = $result['can_sell']-1;
        // $res = $TransferM->save($result);
        // if(!$res){
        //     $balanceM->rollback();
        //     return $this->failJSON(lang('TRANSFER_SELL_UPDATE_FAIL'));
        // }
        $TradingLog = new \addons\member\model\TradingLog();
        $info = [
            'order_id'=>$trading['order_id'],
            'user_id'=>$user_id,
            'remark'=>'用户确认收款',
            'create_at'=>NOW_DATETIME,
            'type'=>3,
        ];
        $TradingLog->add($info);

        $TransferM = new \addons\member\model\Transfer();
        $info = $TransferM->findData($trading['to_user_id']);
        if($info){
            $info['today_quota'] = $info['today_quota']+$trading['number']*2;
            $info['today_at'] = NOW_DATETIME;
            $res = $TransferM->save($info);
            if(!$res){
                $balanceM->rollback();
                return $this->failJSON(lang('TRANSFER_QUOTA_UPDATE_FAIL'));
            }
        }else{
            $arr = [
                'user_id'       => $trading['to_user_id'],
                'today_quota'   => $trading['number']*2,
                'today_at'      => NOW_DATETIME,
                'power'         => 0,
                'create_at'     => NOW_DATETIME,
            ];
            $res = $TransferM->add($arr);
            if(!$res){
                $balanceM->rollback();
                return $this->failJSON(lang('TRANSFER_QUOTA_UPDATE_FAIL'));
            }
        }
        $balanceM->commit();
        return $this->successJSON(lang('TRANSFER_CONFIRM_SUC'));
    
    }

    /**
     * 订单列表
     */
    public function orderList(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
        $tradingM = new \addons\member\model\Trading();
        $row = $this->_post('row')?$this->_post('row'):20;
        $page = $this->_post('page')?$this->_post('page')*$row:0;
        if($this->_post('status')){
            $map['status'] = 1;
            $map['user_id|to_user_id'] = $user_id;
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
            $count = $tradingM->where(['user_id'=>$value['user_id'],'status'=>0])->count();
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
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
        $map['user_id'] = ['neq',$user_id];
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

        $order = 'price asc';
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
            $count = $tradingM->where(['user_id'=>$value['user_id'],'status'=>0])->count();
            $list[$key]['count'] = $count;
        }
        $this->successJSON($list);
    }

    /**
     * 获取外网行情
     */
    public function getCoinInfo(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
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
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
        $tradingM = new \addons\member\model\Trading();
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON(lang('TRANSFER_RIGHT_ORDER'));
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading)  return $this->failJSON(lang('TRANSFER_ORDER_EXISTS'));
        if(!($user_id==$trading['user_id']||$user_id==$trading['to_user_id'])) return $this->failJSON(lang('TRANSFER_NOT_YOUR'));
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
        $trading['real_name'] = $user['real_name'];
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
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
        $tradingM = new \addons\member\model\Trading();
        $userM = new \addons\member\model\MemberAccountModel();
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON(lang('TRANSFER_RIGHT_ORDER'));
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON(lang('TRANSFER_ORDER_EXISTS'));
        $user = $userM->getDetail($trading['user_id']);
        $count = $tradingM->getCount(['user_id'=>$trading['user_id'],'status'=>0]);
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
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
        $tradingM = new \addons\member\model\Trading();
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->where(['id'=>$user_id])->find();
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON(lang('TRANSFER_RIGHT_ORDER'));
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON(lang('TRANSFER_ORDER_EXISTS'));
        if($user_id==$trading['user_id']&&0==$trading['type']&&0==$trading['status']){
            if($trading['trans_mode']){
                try{
                    $balanceM = new \addons\member\model\Balance();
                    $balanceM->startTrans();
                    $coin_id = 4;
                    $amount = $trading['number'];
                    $userAmount = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
                    if(!$userAmount){
                        $balanceM->rollback();
                        return $this->failJSON(lang('TRANSFER_CBC2_ADD'));
                    }

                    $type = 8;
                    $change_type = 1; //增加
                    $remark = '用户取消订单，增加激活码';
                    $recordM = new \addons\member\model\TradingRecord();
                    $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                    if(!$r_id){
                        $balanceM->rollback();
                        return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
                    }

                    $sysM = new \web\common\model\sys\SysParameterModel();
                    $less_total = $sysM->getValByName('less_total');
                    $less_total = $less_total+$amount;
                    $res = $sysM->setValByName('less_total',$less_total);
                    if(!$res){
                        $balanceM->rollback();
                        $this->failJSON(lang('TRANSFER_TOTAL_FAIL'));
                    }

                    $TradingLog = new \addons\member\model\TradingLog();
                    $info = [
                        'order_id'=>$trading['order_id'],
                        'user_id'=>$user_id,
                        'remark'=>'用户取消',
                        'create_at'=>NOW_DATETIME,
                        'type'=>4,
                    ];
                    $TradingLog->add($info);

                    $TransferM = new \addons\member\model\Transfer();
                    $res = $TransferM->updateQuota($user_id,$amount,1);
                    if(!$res){
                        $balanceM->rollback();
                        $this->failJSON(lang('TRANSFER_QUOTA_FAIL'));
                    }

                    $trading['status'] = 1;
                    $trading['update_time'] = NOW_DATETIME;
                    $res = $tradingM->save($trading);
                    if($res){
                        $balanceM->commit();
                        $this->successJSON(lang('TRANSFER_CANCLE_SUC'));
                    }else{
                        $balanceM->rollback();
                        $this->failJSON(lang('TRANSFER_CANCLE_FAIL'));
                    } 
                }catch(\Exception $e){
                    $this->failJSON($e->getMessage());
                }
            }else{
                try{
                    $balanceM = new \addons\member\model\Balance();
                    $balanceM->startTrans();
                    $coin_id = 2;
                    $amount = $trading['number'];
                    $userAmount = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
                    if(!$userAmount){
                        $balanceM->rollback();
                        return $this->failJSON(lang('TRANSFER_CBC2_ADD'));
                    }

                    $type = 8;
                    $change_type = 1; //增加
                    $remark = '用户取消订单，增加可用';
                    $recordM = new \addons\member\model\TradingRecord();
                    $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                    if(!$r_id){
                        $balanceM->rollback();
                        return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
                    }

                    $coin_id = 1;//CBC
                    $total = bcmul(($trading['number']+$trading['fee_num']), 1,2);
                    $userAmount = $balanceM->updateBalance($user_id,$coin_id,$total,1);
                    if(!$userAmount){
                        $balanceM->rollback();
                        return $this->failJSON(lang('TRANSFER_CBC1_ADD'));
                    }
                    $type = 8;
                    $change_type = 1; //减少
                    $remark = '用户取消订单，增加总额';
                    $recordM = new \addons\member\model\TradingRecord();
                    $r_id = $recordM->addRecord($user_id, $total, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                    if(!$r_id){
                        $balanceM->rollback();
                        return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
                    }

                    $coin_id = 3;//CBC
                    $total = bcmul(($trading['number']+$trading['fee_num']), 1,2);
                    $userAmount = $balanceM->updateBalance($user_id,$coin_id,$total);
                    if(!$userAmount){
                        $balanceM->rollback();
                        return $this->failJSON(lang('TRANSFER_CBC3_LESS'));
                    }
                    $type = 8;
                    $change_type = 0; //减少
                    $remark = '用户取消订单，减少锁仓';
                    $recordM = new \addons\member\model\TradingRecord();
                    $r_id = $recordM->addRecord($user_id, $total, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                    if(!$r_id){
                        $balanceM->rollback();
                        return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
                    }

                    $sysM = new \web\common\model\sys\SysParameterModel();
                    $less_total = $sysM->getValByName('less_total');
                    $less_total = $less_total+$amount;
                    $res = $sysM->setValByName('less_total',$less_total);
                    if(!$res){
                        $balanceM->rollback();
                        $this->failJSON(lang('TRANSFER_TOTAL_FAIL'));
                    }
                    
                    $TradingLog = new \addons\member\model\TradingLog();
                    $info = [
                        'order_id'=>$trading['order_id'],
                        'user_id'=>$user_id,
                        'remark'=>'用户取消',
                        'create_at'=>NOW_DATETIME,
                        'type'=>4,
                    ];
                    $TradingLog->add($info);

                    $TransferM = new \addons\member\model\Transfer();
                    $res = $TransferM->updateQuota($user_id,$amount,1);
                    if(!$res){
                        $balanceM->rollback();
                        $this->failJSON(lang('TRANSFER_QUOTA_FAIL'));
                    }

                    $trading['status'] = 1;
                    $trading['update_time'] = NOW_DATETIME;
                    $res = $tradingM->save($trading);
                    if($res){
                        $balanceM->commit();
                        $this->successJSON(lang('TRANSFER_CANCLE_SUC'));
                    }else{
                        $balanceM->rollback();
                        $this->failJSON(lang('TRANSFER_CANCLE_FAIL'));
                    } 
                }catch(\Exception $e){
                    $this->failJSON($e->getMessage());
                }
            }
                
        }else{
            // if(!($user_id==$trading['user_id']||$user_id==$trading['to_user_id'])) return $this->failJSON(lang('TRANSFER_NOT_YOUR'));
            // if($trading['type']==3||$trading['type']==2) return $this->failJSON(lang('TRANSFER_WRONG_STATUS'));
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
            return $this->failJSON(lang('TRANSFER_ORDER_WRONG'));
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
                return $this->failJSON(lang('TRANSFER_NAME_ADDRESS'));
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
                return $this->failJSON(lang('TRANSFER_SET_PAY'));
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
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
        $tradingM = new \addons\member\model\Trading();
        $userM = new \addons\member\model\MemberAccountModel();
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON(lang('TRANSFER_RIGHT_ORDER'));
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON(lang('TRANSFER_ORDER_EXISTS'));
        if($trading['type']!=2) return $this->failJSON(lang('TRANSFER_WRONG_STATUS'));
        if($user_id!=$trading['to_user_id']) return $this->failJSON(lang('TRANSFER_NOT_YOUR'));
        $user = $userM->getDetail($trading['user_id']);
        if($user['region_code']=='86'){
            $msg = '【CBC】尊敬的'.$user['real_name'].'先生/女士，您的订单在CBC系统出售成功，买家已经打款，请您在收到款之后去平台确认发货。';
        }else{
            $msg = '【CBC】 Dear Mr / Madam '.$user['real_name'].', your order has been successfully sold in CBC system. The buyer has already made a payment. Please go to the platform to confirm the delivery after receiving the payment.';
        }
        $phone = $user['region_code'].$user['phone'];
        $res = \addons\member\utils\Sms::sendOrder($phone,$msg);
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
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
        $tradingM = new \addons\member\model\Trading();
        $userM = new \addons\member\model\MemberAccountModel();
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON(lang('TRANSFER_RIGHT_ORDER'));
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON(lang('TRANSFER_ORDER_EXISTS'));
        if(!($user_id==$trading['to_user_id']||$user_id==$trading['user_id'])) return $this->failJSON(lang('TRANSFER_NOT_YOUR'));
        $content = $this->_post('content');
        if(!$content) return $this->failJSON(lang('TRANSFER_COMPLAINT_EMPTY'));
        $data = [
            'user_id'=>$user_id,
            'trad_id'=>$trad_id,
            'content'=>$content,
        ];
        $TradingComplaint = new \addons\member\model\TradingComplaint();
        $res = $TradingComplaint->addComplaint($data);
        if($res) $this->successJSON(lang('TRANSFER_COMPLAINT_SUC'));
        else $this->failJSON(lang('TRANSFER_COMPLAINT_FAIL'));
    }

    /**
     * 用户投诉记录
     */
    public function UserComplaintList(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData(lang('COMMON_LOGIN'));
        $TradingComplaint = new \addons\member\model\TradingComplaint();
        $filter = 'user_id = '.$user_id;
        $page = $this->_post('page')?$this->_post('page'):0;
        $size = $this->_post('rows')?$this->_post('rows'):15;
        $res = $TradingComplaint->getList($filter,$page*$size,$size);
        $this->successJSON($res);
    }

    /**
     * 获取支付方式
     */
    private function getUserPayList($uid){
        $m = new \addons\member\model\PayConfig();
        return $m->getUserPay($uid);
    }

    

}
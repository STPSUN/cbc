<?php
/**
 * Created by PhpStorm.
 * User: zhuangminghan 
 * Date: 2018/10/16
 * Time: 16:48
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
        $sysM = new \web\common\model\sys\SysParameterModel();
        $this->forbiddenTime($sysM);
        $number = $this->_post('amount');
        $amount = bcmul($number, $sysM->getValByName('cbc_price'),4);
        if($amount<=0) return $this->failJSON('请输入正确的挂买金额');
        $payM = new \addons\member\model\PayConfig();
        $paylist = $payM->getUserPay($user_id);
        if(!$paylist)  return $this->failJSON('没有设置支付方式，请设置');

        $rate = $sysM->getValByName('is_deal_tax')?$sysM->getValByName('deal_tax'):0;
        $fee_num = bcmul($amount,$rate,4);
        $total = $amount+$fee_num;
        $balanceM = new \addons\member\model\Balance();
        $coin_id = 2;//CBC
        $userAmount = $balanceM->getBalanceByType($user_id,$coin_id);
        if($amount>$userAmount['amount']) return $this->failJSON('你的CBC余额少于'.$amount);
        $balanceM->startTrans();
        $userAmount = $balanceM->updateBalance($user_id,$coin_id,$amount);
        if(!$userAmount){
            $balanceM->rollback();
            return $this->failJSON('减少CBC余额失败');
        }

        $type = 6;
        $change_type = 0; //减少
        $remark = '用户挂卖';
        $recordM = new \addons\member\model\TradingRecord();
        $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
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
        $type = 8;
        $change_type = 1; //增加
        $remark = '用户挂卖';
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
        $pay_password = md5($this->_post('pay_password'));
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->getDetail($user_id);
        if($user['pay_password'] != $pay_password){
            return $this->failJSON('支付密码错误');
        }
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
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON('请选择正确的订单');
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON('订单不存在');
        if($user_id!=$trading['to_user_id']) return $this->failJSON('该订单不是您的订单');
        if($trading['type']!=1) return $this->failJSON('订单状态错误');
        $pay_password = md5($this->_post('pay_password'));
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->getDetail($user_id);
        if($user['pay_password'] != $pay_password){
            return $this->failJSON('支付密码错误');
        }

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
        $user = $userM->where(['id'=>$user_id])->find();
        $trad_id = $this->_post('trad_id');
        if($trad_id<=0) return $this->failJSON('请选择正确的订单');
        $trading = $tradingM->findTrad($trad_id);
        if(!$trading) return $this->failJSON('订单不存在');
        if($user_id!=$trading['user_id']) return $this->failJSON('该订单不是您的订单');
        if($trading['type']!=2) return $this->failJSON('订单状态错误');
        $pay_password = $this->_post('pay_password');
        $pay_password = md5($pay_password);
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->getDetail($user_id);
        if($user['pay_password'] != $pay_password){
            return $this->failJSON('支付密码错误');
        }
        $balanceM = new \addons\member\model\Balance();
        $balanceM->startTrans();
        $trading['type'] = 3;
        $trading['update_time'] = NOW_DATETIME;
        $res = $tradingM->save($trading);
        if(!$res){
            $balanceM->rollback();
            return $this->failJSON('订单保存失败');
        }
        $coin_id = 2;//CBC余额
        $userAmount = $balanceM->updateBalance($trading['to_user_id'],$coin_id,$trading['amount'],1);
        if(!$userAmount){
            $balanceM->rollback();
            return $this->failJSON('增加CBC失败');
        }

        $type = 7;
        $change_type = 1; //增加
        $remark = '用户买入';
        $recordM = new \addons\member\model\TradingRecord();
        $r_id = $recordM->addRecord($trading['to_user_id'], $trading['amount'], $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type,$user_id ,$remark);
        if(!$r_id){
            $balanceM->rollback();
            return $this->failJSON('增加记录失败');
        }
        //删除锁仓金额
        $coin_id = 3;//CBC
        $amount = bcmul(($trading['fee_num']+$trading['amount']), 1,2);
        $userAmount = $balanceM->updateBalance($user_id,$coin_id,$amount);
        if(!$userAmount){
            $balanceM->rollback();
            return $this->failJSON('减少CBC锁仓失败');
        }
        $type = 9;
        $change_type = 0; //减少
        $remark = '用户买入';
        $recordM = new \addons\member\model\TradingRecord();
        $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, $user_id,$remark);
        if(!$r_id){
            $balanceM->rollback();
            return $this->failJSON('增加记录失败');
        }
        //计算奖金
        $res = $this->mathBonus($trading);
        if(!$res){
            $balanceM->rollback();
            return $this->failJSON('奖金发放失败');
        }
        $balanceM->commit();
        return $this->successJSON('确认收款成功');
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
        $this->successJSON($list);
    }

    /**
     * 交易大厅
     */
    public function TradingHall(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');
        $tradingM = new \addons\member\model\Trading();
        $row = $this->_post('row')?$this->_post('row'):20;
        $page = $this->_post('page')?$this->_post('page')*$row:0;
        $list = $tradingM->getOrderList(['type'=>0],$page,$row);
        $this->successJSON($list);
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
            $trading['play'] = 1;
            $user = $userM->getDetail($trading['user_id']);
            $trading['phone'] = $user['phone'];
            $trading['pay'] = $this->getUserPayAll($trading['user_id']);
        }else{
            $trading['play'] = 0;
            $user = $userM->getDetail($trading['user_id']);
            $trading['phone'] = $user['phone'];
            $trading['pay'] = $this->getUserPayAll($trading['to_user_id']);
        }
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
        $sysM = new \web\common\model\sys\SysParameterModel();
        $trading['price'] = $sysM->getValByName('cbc_price');
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
            $balanceM = new \addons\member\model\Balance();
            $balanceM->startTrans();
            $coin_id = 2;
            $amount = $trading['amount'];
            $userAmount = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
            if(!$userAmount){
                $balanceM->rollback();
                return $this->failJSON('减少CBC余额失败');
            }

            $type = 10;
            $change_type = 1; //增加
            $remark = '用户挂卖';
            $recordM = new \addons\member\model\TradingRecord();
            $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
            if(!$r_id){
                $balanceM->rollback();
                return $this->failJSON('增加记录失败');
            }

            $coin_id = 3;//CBC
            $total = bcmul(($trading['amount']+$trading['fee_num']), 1,2);
            $userAmount = $balanceM->updateBalance($user_id,$coin_id,$total);
            if(!$userAmount){
                $balanceM->rollback();
                return $this->failJSON('增加CBC锁仓失败');
            }
            $type = 11;
            $change_type = 0; //减少
            $remark = '用户挂卖';
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
    public function getUserPayAll($uid=false){
        $m = new \addons\member\model\PayConfig();
        if($uid){
            $user_id = $uid;
            return $m->getUserPay($user_id);
        }else{
            $user_id = $this->user_id;
            if(empty($user_id)){
                return $this->failJSON('missing arguments');
            }
        }
        try{
            $data = $m->getUserPay($user_id);
            return $this->successJSON($data);
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }


    /**
     * 定时器访问
     * 超过30分钟未付款则取消订单
     */
    public function cancleOrder(){
        $tradingM = new \addons\member\model\Trading();
        $map['type'] = 1;
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

    /**
     * 计算奖金
     */
    public function mathBonus($trading){
        return true;
    }
}
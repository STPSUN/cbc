<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace web\index\controller;

/**
 * Description of Finance
 *
 * @author shilinqing
 */
class Finance extends Base {

    //明细
    public function index() {
        $m = new \addons\member\model\TradingRecord();
        $list = $m->getDataList(-1, -1, 'user_id=' . $this->user_id, '*', 'id desc');
        $this->assign('list', $list);
        return $this->fetch();
    }
    
    public function bonus_record(){
        $m = new \addons\member\model\BonusRecord();
        $list = $m->getDataList(-1,-1,'user_id='.$this->user_id,'','id desc');
        $this->assign('list', $list);
        $totalM = new \addons\member\model\TotalBonusRecord();
        $total_data = $totalM->where('user_id='.$this->user_id)->find();
        $total_bonus = $total_data['total_bonus']; //未结算
        $count_total_bonus = $total_data['invite_total_bonus'] + 
                            $total_data['duipen_total_bonus'] + 
                            $total_data['manage_total_bonus'] + 
                            $total_data['leader_total_bonus'] +
                            $total_data['center_total_bonus'] +
                            $total_data['recast_total_bonus'];
        $this->assign('total_bonus',$total_bonus);
        $this->assign('count_total_bonus',$count_total_bonus);
        
        $this->assign('invite_total_bonus',$total_data['invite_total_bonus']);
        $manage_total_bonus = sprintf('%.2f',$total_data['manage_total_bonus'] + $total_data['duipen_total_bonus']);
        $this->assign('manage_total_bonus',$manage_total_bonus);
        $this->assign('leader_total_bonus',$total_data['leader_total_bonus']);
        $this->assign('center_total_bonus',$total_data['center_total_bonus']);
        $this->assign('recast_total_bonus',$total_data['recast_total_bonus']);
        
        //左右区业绩
        $userM = new \addons\member\model\MemberAccountModel();
        $_total = $userM->getDetail($this->user_id,'left_total,right_total');
        $this->assign('left_total',$_total['left_total']);
        $this->assign('right_total',$_total['right_total']);
        return $this->fetch();
    }

    public function recharge() {
        $rechargeM = new \addons\order\model\RechargeModel();
        $list = $rechargeM->getDataList(-1, -1, 'user_id=' . $this->user_id, '*', 'id desc');
        $this->assign('list', $list);
        return $this->fetch();
    }

    //充值
    public function doRecharge() {
        if (IS_POST) {
            $data = $_POST;
            $data['user_id'] = $this->user_id;
            $data['ref_no'] = $this->createOrderSn();
            $data['pic'] = $_POST['image'];
            $rechargeM = new \addons\order\model\RechargeModel();
            try {
                $data['update_time'] = NOW_DATETIME;
                $rechargeM->add($data);
                return $this->successData('提交成功');
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
        } else {
            return $this->fetch();
        }
    }

    public function without() {
        $withoutM = new \addons\order\model\WithoutModel();
        $list = $withoutM->getDataList(-1, -1, 'user_id=' . $this->user_id, '*', 'id desc');
        $this->assign('list', $list);
        return $this->fetch();
    }

    //提现
    public function doWithout() {
        if (IS_POST) {
            $data['amount'] = $this->_post('amount');
            $data['rate'] = $this->_post('rate');
            $data['user_id'] = $this->user_id;
            $data['ref_no'] = $this->createOrderSn();
            $withoutM = new \addons\order\model\WithoutModel();
            $balanceM = new \addons\member\model\Balance();
            $recordM = new \addons\member\model\TradingRecord();
            //判断奖金余额是否足够
            $balance = $balanceM->getBalanceByType($this->user_id, 3);
            if ($data['amount'] <= 0) {
                return $this->failData('金额必须大于0');
            }
            if ($data['rate'] > 0) {
                if (($data['amount'] + $data['rate']) > $balance['amount']) {
                    return $this->failData('分红余额不足');
                }
            } else {
                if ($data['amount'] > $balance['amount']) {
                    return $this->failData('分红余额不足');
                }
            }

            try {
                $withoutM->startTrans();
                //减少用户余额---提现金额
                $balanceM->updateBalance($this->user_id, 3, $data['amount']);
                //获取用户销售分红
                $memberBalance = $balanceM->getBalanceByType($this->user_id, 3);
                $recordM->addRecord($this->user_id, $data['amount'], $memberBalance['before_amount'], $memberBalance['amount'], 3, 2, 0, 0, '提现');
                //更新用户提现冻结金额
                $balanceM->where(['id' => $memberBalance['id']])->update(['withdraw_frozen_amount' => $memberBalance['withdraw_frozen_amount'] + $data['amount']]);
                //扣除提现手续费
                if ($data['rate'] > 0) {
                    $balanceM->updateBalance($this->user_id, 3, $data['rate']);
                    $memberBalance = $balanceM->getBalanceByType($this->user_id, 3);
                    $recordM->addRecord($this->user_id, $data['rate'], $memberBalance['before_amount'], $memberBalance['amount'], 3, 2, 0, 0, '提现手续费');
                }
                $data['update_time'] = NOW_DATETIME;
                $withoutM->add($data);
                $withoutM->commit();
                return $this->successData('提交成功');
            } catch (\Exception $ex) {
                $withoutM->rollback();
                return $this->failData($ex->getMessage());
            }
        } else {
            $sysConfigM = new \web\common\model\sys\SysParameterModel();
            $IsRate = $sysConfigM->where(['field_name' => 'is_without_tax'])->value('parameter_val');
            $rateData = $sysConfigM->where(['field_name' => 'without_tax'])->find();
            if ($IsRate == 1) {
                $rate = $rateData['parameter_val'];
            } else {
                $rate = 0;
            }
            $this->assign('rate', $rate);
            return $this->fetch();
        }
    }

    /*
     * 图片上传
     */

    public function base64_upload() {
        $file = $this->_post('file');
        $data = $this->base_img_upload($file, $this->user_id, 'proof/');
        return $this->successJSON($data);
    }

    /**
     * 保存base64 图片
     * @param type $base64
     * @param type $user_id
     * @return boolean|string
     */
    private function base_img_upload($base64, $user_id, $savePath) {
        // 获取表单上传文件 例如上传了001.jpg
        if (empty($base64)) {
            return false;
        }
        $_message = array(
            'success' => false,
            'message' => '',
        );
        $rootPath = UPLOADFOLDER;
        $uploadFolder = substr($rootPath, 1);
        $uploadPath = $uploadFolder . $savePath;
        $path = $_SERVER['DOCUMENT_ROOT'] . $uploadPath;
        $file_name = time() . getMD5Name(3, $user_id);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64, $result)) {
            $ext = array('jpg', 'gif', 'png', 'jpeg');
            $type = $result[2];
            if (!in_array($type, $ext)) {
                $_message['message'] = '图片格式错误';
                return $_message;
            }
            $pic_path = $path . $file_name . "." . $type;
            $file_size = file_put_contents($pic_path, base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64)));
            if (!$pic_path || $file_size > 10 * 1024 * 1024) {
                unlink($pic_path);
                $_message['message'] = '图片保存失败';
                return $_message;
            }
        } else {
            $_message['message'] = '图片格式编码错误';
            return $_message;
        }
        $_message['success'] = true;
        $_message['message'] = '上传成功';
        $_message['path'] = $uploadPath . $file_name . '.' . $type;
        return $_message;
    }

    /*
     * 生成随机订单号
     */
    public function createOrderSn() {
        return date('YmdHis') . rand(10000, 99999);
    }

    /**
     * 奖金转订阅分
     */
    public function change_balance(){
        $b = new \addons\member\model\Balance();
        if(IS_POST){
            $amount = $this->_post('amount');
            if($amount <= 0){
                return $this->failData('金额必须大于0');
            }
            $bonus_type = 3; //奖金类型
            $sub_type = 1; //订阅分
            $current_bonus_amount = $b->getBalanceAmountByType($this->user_id, $bonus_type);
            if($amount > $current_bonus_amount){
                return $this->failData('奖金余额不足');
            }
            try{
                $recordM = new \addons\member\model\TradingRecord();
                $b->startTrans();
                $remark = '资产转换';
                $type = 7; //资产转换
                //减少奖金, 增加订阅费 ,并填写记录
                $bonus_balance = $b->updateBalance($this->user_id, $bonus_type, $amount);
                if($bonus_balance != false){
                    $change_type = 0;
                    $record_id = $recordM->addRecord($this->user_id, $amount, $bonus_balance['before_amount'], $bonus_balance['amount'], $bonus_type, $type, $change_type,0, $remark);
                    if($record_id > 0){
                        $sub_balance = $b->updateBalance($this->user_id, $sub_type, $amount, true);
                        if($sub_balance != false){
                            $change_type = 1;
                            $record_id = $recordM->addRecord($this->user_id, $amount, $sub_balance['before_amount'], $sub_balance['amount'], $sub_type, $type, $change_type, 0, $remark);
                            if($record_id > 0){
                                $b->commit();
                                return $this->successData();
                            }
                        }
                            
                    }
                    
                }
            } catch (\Exception $ex) {
                $b->rollback();
                return $this->failData($ex->getMessage());
            }
        }else{
            $type = 3; //分红奖金类型
            $where['user_id'] = $this->user_id;
            $where['type'] = $type;
            $bonus_amount = $b->where($where)->value('amount');
            $this->assign('bonus_amount',$bonus_amount);
            return $this->fetch();
        }
    }
    
    /**
     * 转账
     */
    public function transfer(){
        $b = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
//        $sub_type = 1; //订阅分类型
        if(IS_POST){
            //对象用户
            $to_username = $this->_post('username');
            $amount = floatval($this->_post('amount'));
            $pay_password = $this->_post('pay_password');
            $sub_type = $this->_post('type'); //金额类型
            if($amount <= 0){
                return $this->failData('数量必须为正数');
            }
            $m = new \addons\member\model\MemberAccountModel();
            $data = $m->getDetail($this->user_id,'pay_password');
            if($data['pay_password'] != md5($pay_password)){
                return $this->failData('支付密码错误,请重新输入');
            }
            $to_user_id = $m->getUserIDByUsername($to_username); //转出用户
            if(empty($to_user_id)){
                return $this->failData('转出用户不存在');
            }
            $paramM = new \web\common\model\sys\SysParameterModel();
            $is_tax = $paramM->getValByName('is_transfer_tax');
            $tax_amount = 0; //手续费金额 下单方出
            if($is_tax == 1){
                $tax_rate = $paramM->getValByName('transfer_tax'); //手续费比率
                $tax_amount = $tax_rate * $amount / 100;
            }
            $total_amount = $amount + $tax_amount;
            $balance = $b->verifyStock($this->user_id,$total_amount,$sub_type);
            if(empty($balance)){
                return $this->failData('余额不足');
            }
            if($total_amount > $balance['amount']){
                return $this->failData('余额不足');
            }
            try{
                $b->startTrans();
                //扣除当前用户余额, 添加转出用户余额
                $balance = $b->updateBalance($this->user_id, $sub_type, $total_amount);
                if($balance != false){
                    $type = 0; //转账
                    $change_type = 0; //减少
                    $remark = '用户转出,手续费金额:'.$tax_amount;
                    $record_id = $recordM->addRecord($this->user_id, $total_amount, $balance['before_amount'], $balance['amount'], $sub_type, $type, $change_type, $to_user_id, $remark);
                    if($record_id > 0){
                        $to_balance = $b->updateBalance($to_user_id, $sub_type, $amount, true);
                        if($to_balance != false){
                            $change_type = 1;
                            $remark = '用户转入';
                            $record_id = $recordM->addRecord($to_user_id, $amount, $to_balance['before_amount'], $to_balance['amount'], $sub_type, $type, $change_type, $this->user_id, $remark);
                            if($record_id > 0){
                                $b->commit();
                                return $this->successData();
                            }
                        }
                    }
                }

            } catch (\Exception $ex) {
                $b->rollback();
                return $this->failData($ex->getMessage());
            }
            
        }else{
            $sysConfigM = new \web\common\model\sys\SysParameterModel();
            $isRate = $sysConfigM->getValByName('is_transfer_tax');
            $transfer_tax = $sysConfigM->getValByName('transfer_tax');
            if ($isRate == 1) {
                $rate = $transfer_tax;
            } else {
                $rate = 0;
            }
            $this->assign('rate', $rate);
//            $sub_amount = $b->getBalanceAmountByType($this->user_id, $sub_type);
//            $this->assign('sub_amount',$sub_amount);
            return $this->fetch();
        }
    }
}

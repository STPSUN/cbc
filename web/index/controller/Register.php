<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace web\index\controller;

/**
 * Description of Register
 *
 * @author shilinqing
 */
class Register extends Base{
    
    public function index(){
        if(IS_POST){
            $m = new \addons\member\model\MemberAccountModel();
            $inviter_username = $this->_post('inviter');
            $pid = $m->getUserIDByUsername($inviter_username);
            if(empty($pid)){
                return $this->failData('所填写销售经理用户不存在');
            }
            $data['pid'] = $pid;
            $data['create_id'] = $this->user_id;//账户创建人
            $data['username'] = $this->_post('username');
            $data['phone'] = $this->_post('phone');
            $data['card_no'] = $this->_post('card_no');
            $password = $this->_post('password');
            $pay_password = $this->_post('pay_password');
            $data['real_name'] = $this->_post('real_name');
            $data['address'] = $this->_post('address');
//            if (!preg_match("/^(?![\d]+$)(?![a-zA-Z]+$)(?![^\da-zA-Z]+$).{6,16}$/", $password)) {
//                return $this->failData('密码必须含有数字，字母，特殊符号中的两种。');
//            }
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
            //报单中心
            $center_username = $this->_post('center_username');
            $center_id = 0;
            if(!empty($center_username)){
                $center_id = $m->getUserIDByUsername($center_username,1);
                if(empty($center_id)){
                    return $this->failData('所选报单中心不存在');
                }
                $data['center_id'] = $center_id;
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
            
            $meal_id = $this->_post('meal_id');//套餐id
            $mealM = new \addons\baodan\model\MealConfig();
            $meal = $mealM->getDetail($meal_id);
            $price = $meal['price'];
            //使用空单回填
            $m->startTrans();
            try {
                $can_backfill = $m->where('id='.$this->user_id)->value('can_backfill');
                if($can_backfill == 1){
                    $use_backfill = $this->_post('use_backfill');
                    if($use_backfill == 1){
                        $ret = $this->_backfill($meal,$data);
                        if($ret){
                            $m->commit();
                            return $this->successData();
                        }else{
                            $m->rollback();
                            return $this->failData('创建空单回填账号失败');
                        }
                    }
                }
                //是否需要获取特权分字段
                $is_center = $m->userIsCenter($this->user_id);
                $sp_amount = 0;
                $bonus_amount = 0;
                if($is_center == 1){
                    $sp_amount = $this->_post('sp_amount'); //特权分
                }
                $bonus_amount = $this->_post('bonus_amount'); //奖金
                $sub_amount = $this->_post('sub_amount'); //订阅分
                if(empty($sub_amount)){
                    return $this->failData('订阅分必须大于0');
                }
                if($bonus_amount > 0 && $sp_amount > 0){
                    return $this->failData('特权分与奖金只能选择其中一种');
                }
                $total_amount = $sub_amount + $bonus_amount + $sp_amount;

                if($total_amount != $price){
                    return $this->failData('购买套餐所需金额不正确');
                }
                if($bonus_amount > $price / 2){
                    return $this->failData('使用的奖金金额不能超过50%');
                }
                if($sp_amount > $price / 10){
                    return $this->failData('使用的特权积分不可超过10%');
                }
            
//                扣除余额,添加消费记录
                $balanceM = new \addons\member\model\Balance();
                $recordM = new \addons\member\model\TradingRecord();
                $record_type = 4; //注册
                $change_type = 0; // 0 = 减少,1=增加
                $remark = '注册花费';
                if(!empty($sub_amount)){
                    $balance_type = 1;//订阅费
                    $sub_balance = $balanceM->verifyStock($this->user_id, $sub_amount, $balance_type);
                    if(empty($sub_balance)){
                        return $this->failData('余额不足');
                    }
                    $sub_balance = $balanceM->updateBalance($this->user_id, $balance_type, $sub_amount);
                    if(!empty($sub_balance)){
                        //添加消费记录
                        $recordM->addRecord($this->user_id, $sub_amount, $sub_balance['before_amount'], $sub_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                    }
                }
                if(!empty($bonus_amount)){
                    $balance_type = 3;//奖金
                    $bonus_balance = $balanceM->verifyStock($this->user_id, $bonus_amount, $balance_type);
                    if(empty($bonus_balance)){
                        return $this->failData('余额不足');
                    }
                    $bonus_balance = $balanceM->updateBalance($this->user_id, $balance_type, $bonus_amount);
                    if(!empty($bonus_balance)){
                        //添加消费记录
                        $recordM->addRecord($this->user_id, $bonus_amount, $bonus_balance['before_amount'], $bonus_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                    }
                    
                }else if(!empty($sp_amount)){
                    $balance_type = 4;//特权分
                    $sp_balance = $balanceM->verifyStock($this->user_id, $sp_amount, $balance_type);
                    if(empty($sp_balance)){
                        return $this->failData('余额不足');
                    }
                    $sp_balance = $balanceM->updateBalance($this->user_id, $balance_type, $sp_amount);
                    if(!empty($sp_balance)){
                        //添加消费记录
                        $recordM->addRecord($this->user_id, $sp_amount, $sp_balance['before_amount'], $sp_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                    }
                }
                
//                添加用户
                $data['meal_id'] = $meal_id;
                $data['self_total'] = $price; //自身业绩
                $data['register_time'] = NOW_DATETIME;
                $user_id = $m->add($data);
                if($user_id > 0){
//                    添加余额数据
                    $bcm = new \addons\config\model\BalanceConf();
                    $type_list = $bcm->getDataList(-1,-1,'','id','id asc');
                    $cgjs_amount = $this->_countAmount($price, $meal['lever']);
                    $zzzc_amount = $this->_multiAmount($price);
                    foreach($type_list as $k => $type){
                        $type = $type['id'];
                        
                        if($type == 2){
                            //参股基数
                            $_balance['amount'] = $cgjs_amount;
                        }else if($type == 5){
                            //增值资产
                            $_balance['amount'] = $zzzc_amount;
                        }
                        $_balance['user_id'] = $user_id;
                        $_balance['type'] = $type;
                        $_balance['update_time'] = NOW_DATETIME;
                        $balanceM->add($_balance);
                        unset($_balance['amount']);
                    }
//                    后添加订单记录
                    $orderM = new \addons\baodan\model\MealOrder();
                    $order_code = $this->newOrderCode();
                    $order_id = $orderM->addOrder($user_id, $meal_id, $order_code, $sub_amount, $bonus_amount, $sp_amount, $data['real_name'], $data['phone'], $data['address']);
                    if($order_id > 0){
                        //更新团队直推业绩
                        $m->updateTeamDirectTotal($user_id, $price);
//                        发放奖金
                        if(!empty($sp_amount)){
                            $price = $price * 0.9; //使用特权分, 奖金按90% 计算
                        }
                        $has_send = $this->_sendBonus($user_id,$data['aid'],$data['pid'],$data['position'], $price,$meal['devlop_bonus_rate'],$meal['bonus_limit'],$center_id);
                        if($has_send['success'] == false){
                            $m->rollback();
                            return $this->failData($has_send['message']);
                        }
                    }
                    $m->commit();
                    return $this->successData($user_id);
                }else{
                    $m->rollback();
                    return $this->failData('注册失败');
                }
                
            } catch (\Exception $ex) {
                $m->rollback();
                return $this->failData($ex);
            }
            
        }else{
            $position = $this->_get('position');
            $this->assign('position',$position);
            $aid_username = $this->_get('aid_username');
            $this->assign('aid_username',$aid_username);
            //查询套餐
            $m = new \addons\baodan\model\MealConfig();
            $list = $m->getDataList(-1,-1,'','','id asc');
            $this->assign('meals',$list);
            //查询是否用户中心
            $u = new \addons\member\model\MemberAccountModel();
            $is_center = $u->userIsCenter($this->user_id);
            $this->assign('is_center', $is_center);
            $can_backfill = $u->where('id='.$this->user_id)->value('can_backfill');
            $this->assign('can_backfill',$can_backfill);
            //查询订阅分 1 ,奖金 3 ,特权分余额 4
            $b = new \addons\member\model\Balance();
            $sub_amount = $b->getBalanceAmountByType($this->user_id, 1);
  
            $this->assign('sub_amount',$sub_amount);
            $bonus_amount = $b->getBalanceAmountByType($this->user_id, 3);
            $this->assign('bonus_amount',$bonus_amount);
            if($is_center == 1){
                $sp_amount = $b->getBalanceAmountByType($this->user_id, 4);
                $this->assign('sp_amount',$sp_amount);
            }
            return $this->fetch();
        }
    }
    
    public function load_meal_price(){
        $meal_id = $this->_get('meal_id');
        $m = new \addons\baodan\model\MealConfig();
        $price = $m->getFieldByID($meal_id, 'price');
        return $price;
    }
    
    
    private function _backfill($meal, $data){
        $m = new \addons\member\model\MemberAccountModel();
        $balanceM = new \addons\member\model\Balance();
        $flag = false;
        $meal_id = $meal['id'];
        $is_backfill = 1;//空单回填账号
        $advance_amount = $meal['price']; //预支回填余额
        $data['meal_id'] = $meal_id;
        $data['self_total'] = 0; //自身业绩
        $data['is_backfill'] = $is_backfill;
        $data['advance_amount'] = $advance_amount;
        $data['register_time'] = NOW_DATETIME;
        $user_id = $m->add($data);
        if($user_id > 0){
//                    添加余额数据
            $bcm = new \addons\config\model\BalanceConf();
            $type_list = $bcm->getDataList(-1,-1,'','id','id asc');
            $cgjs_amount = $this->_countAmount($meal['price'], $meal['lever']);
            $zzzc_amount = $this->_multiAmount($meal['price']);
            foreach($type_list as $k => $type){
                $type = $type['id'];

                if($type == 2){
                    //参股基数
                    $_balance['amount'] = $cgjs_amount;
                }else if($type == 5){
                    //增值资产
                    $_balance['amount'] = $zzzc_amount;
                }
                $_balance['user_id'] = $user_id;
                $_balance['type'] = $type;
                $_balance['update_time'] = NOW_DATETIME;
                $balanceM->add($_balance);
                unset($_balance['amount']);
            }
//                    后添加订单记录
            $orderM = new \addons\baodan\model\MealOrder();
            $order_code = $this->newOrderCode();
            $order_id = $orderM->addOrder($user_id, $meal_id, $order_code, 0, 0,0, $data['real_name'], $data['phone'], $data['address']);
            if($order_id > 0){
                $flag = true;
            }
        }
        return $flag;
        
    }


    /**
     * 发放奖金
     */
    private function _sendBonus($user_id,$aid,$pid,$position,$price,$devlop_bonus_rate, $limit , $center_id){
        $data['success'] = true;
        $totalBonusM = new \addons\member\model\TotalBonusRecord(); //奖金汇总表
        $conM = new \web\common\model\sys\SysParameterModel();
        $invite_send = $conM->getValByName('invite_send');
        $pv_value = $conM->getValByName('pv_value');
        try{
            //推荐奖励
            $totalBonusM->inviteSend($invite_send,$price, $pid,$pv_value);
            //报单中心奖励
            if($center_id != 0){
                $center_send = $conM->getValByName('center_send');
                $totalBonusM->centerSend($center_send, $price, $center_id,$pv_value);
            }
            //对碰奖励 按照上级节点自身所购买的套餐级别拿对碰奖励比率。devlop_bonus_rate : 发展对碰比率
            $totalBonusM->duipenSend($price, $aid, $user_id, $position, $pv_value);
            //领导奖励
            $totalBonusM->leaderSend($user_id, $price,$pv_value);
            return $data;
        } catch (\Exception $ex) {
            $data['success'] = false;
            $data['message'] = $ex->getMessage();
            return $data;
        }

        
    }
    
    private function _multiAmount($price){
        $m = new \web\common\model\sys\SysParameterModel();
        $inc_asset_rate = $m->getValByName('inc_asset_rate');
        $amount = $price * 0.1 / $inc_asset_rate;
        return $amount;
    }

    private function _countAmount($amount ,$rate){
        $m = new \web\common\model\sys\SysParameterModel();
        $pv_value = $m->getValByName('pv_value');
        $amount = $amount * $pv_value * $rate;
        return $amount;
    }

    private function newOrderCode(){
        $code = 'M'.date('YmdHis').rand(0,9999);
        return $code;
    }
    
    /**
     * 复投
     */
    public function levelup(){
        if(IS_POST){
            $u = new \addons\member\model\MemberAccountModel();
            //是否需要获取特权分字段
            $is_center = $u->userIsCenter($this->user_id);
            $sp_amount = 0;
            $bonus_amount = 0;
            if($is_center == 1){
                $sp_amount = $this->_post('sp_amount'); //特权分
            }
            $bonus_amount = $this->_post('bonus_amount'); //奖金
            $sub_amount = $this->_post('sub_amount'); //订阅分
            if(empty($sub_amount)){
                return $this->failData('订阅分必须大于0');
            }
            if($bonus_amount > 0 && $sp_amount > 0){
                return $this->failData('特权分与奖金只能选择其中一种');
            }
            $total_amount = $sub_amount + $bonus_amount + $sp_amount;
            $meal_id = $this->_post('meal_id');//套餐id
            $mealM = new \addons\baodan\model\MealConfig();
            $meal = $mealM->getDetail($meal_id);
            $price = $meal['price'];
            if($total_amount != $price){
                return $this->failData('购买套餐所需金额不正确');
            }
            if($bonus_amount > $price / 2){
                return $this->failData('使用的奖金金额不能超过50%');
            }
            if($sp_amount > $price / 10){
                return $this->failData('使用的特权积分不可超过10%');
            }
            try{
                $balanceM = new \addons\member\model\Balance();
                $recordM = new \addons\member\model\TradingRecord();
                $record_type = 4; //注册
                $change_type = 0; // 0 = 减少,1=增加
                $remark = '用户复投';
                $balanceM->startTrans();
                if(!empty($sub_amount)){
                    $balance_type = 1;//订阅费
                    $sub_balance = $balanceM->verifyStock($this->user_id, $sub_amount, $balance_type);
                    if(empty($sub_balance)){
                        return $this->failData('余额不足');
                    }
                    $sub_balance = $balanceM->updateBalance($this->user_id, $balance_type, $sub_amount);
                    if(!empty($sub_balance)){
                        //添加消费记录
                        $recordM->addRecord($this->user_id, $sub_amount, $sub_balance['before_amount'], $sub_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                    }
                }
                if(!empty($bonus_amount)){
                    $balance_type = 3;//奖金
                    $bonus_balance = $balanceM->verifyStock($this->user_id, $bonus_amount, $balance_type);
                    if(empty($bonus_balance)){
                        return $this->failData('余额不足');
                    }
                    $bonus_balance = $balanceM->updateBalance($this->user_id, $balance_type, $bonus_amount);
                    if(!empty($bonus_balance)){
                        //添加消费记录
                        $recordM->addRecord($this->user_id, $bonus_amount, $bonus_balance['before_amount'], $bonus_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                    }
                    
                }else if(!empty($sp_amount)){
                    $balance_type = 4;//特权分
                    $sp_balance = $balanceM->verifyStock($this->user_id, $sp_amount, $balance_type);
                    if(empty($sp_balance)){
                        return $this->failData('余额不足');
                    }
                    $sp_balance = $balanceM->updateBalance($this->user_id, $balance_type, $sp_amount);
                    if(!empty($sp_balance)){
                        //添加消费记录
                        $recordM->addRecord($this->user_id, $sp_amount, $sp_balance['before_amount'], $sp_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                    }
                }
                $record_type = 5;//复投
                $change_type = 1;
                $cgjs_amount = $this->_countAmount($price, $meal['lever']);
                $balance_type = 2;
                $zzzc_amount = $this->_multiAmount($price);
                $cgjs_balance = $balanceM->updateBalance($this->user_id, $balance_type, $cgjs_amount,true);
                if(!empty($cgjs_balance)){
                    $recordM->addRecord($this->user_id, $cgjs_amount, $cgjs_balance['before_amount'], $cgjs_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                }
                //更新zzzc
                $balance_type = 5;
                $zzzc_balance = $balanceM->updateBalance($this->user_id, $balance_type, $zzzc_amount,true);
                if(!empty($zzzc_balance)){
                    $recordM->addRecord($this->user_id, $zzzc_amount, $zzzc_balance['before_amount'], $zzzc_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                }
                //更新自身业绩, 和套餐id 
                $user_data = $u->getDetail($this->user_id,'id,self_total,meal_id');
                $user_data['self_total'] = $user_data['self_total'] + $price;
                //todo  优化meal_id 逻辑
                if($meal_id > $user_data['meal_id']){
                    $user_data['meal_id'] = $meal_id;
                }
                $u->save($user_data);
                //发放见点奖励
                $bonusRecordM = new \addons\member\model\TotalBonusRecord();
                $bonusRecordM->sendRecastBonus($this->user_id,$price);
                $balanceM->commit();
                return $this->successData();
                
            } catch (\Exception $ex) {
                $balanceM->rollback();
                return $this->failData($ex->getMessage());
            }
            
        }else{
            $u = new \addons\member\model\MemberAccountModel();
            $meal_id = $u->where('id='.$this->user_id)->value('meal_id');
            // 2018年10月14日14:36:47 未购买套餐,跳转第一次购买套餐页面
            if(empty($meal_id)){
                $url = url("firstblood");
                $this->redirect($url);
                exit;
            }
            $m = new \addons\baodan\model\MealConfig();
            $list = $m->getDataList(-1,-1,'','','id asc');
            $this->assign('meals',$list);
            //查询是否用户中心
            $is_center = $u->userIsCenter($this->user_id);
            $this->assign('is_center', $is_center);
            //查询订阅分 1 ,奖金 3 ,特权分余额 4
            $b = new \addons\member\model\Balance();
            $sub_amount = $b->getBalanceAmountByType($this->user_id, 1);
  
            $this->assign('sub_amount',$sub_amount);
            $bonus_amount = $b->getBalanceAmountByType($this->user_id, 3);
            $this->assign('bonus_amount',$bonus_amount);
            if($is_center == 1){
                $sp_amount = $b->getBalanceAmountByType($this->user_id, 4);
                $this->assign('sp_amount',$sp_amount);
            }
            return $this->fetch();
        }
    }
    
    public function firstblood(){
        if(IS_POST){
            $u = new \addons\member\model\MemberAccountModel();
            //是否需要获取特权分字段
            $is_center = $u->userIsCenter($this->user_id);
            $sp_amount = 0;
            $bonus_amount = 0;
            if($is_center == 1){
                $sp_amount = $this->_post('sp_amount'); //特权分
            }
            $bonus_amount = $this->_post('bonus_amount'); //奖金
            $sub_amount = $this->_post('sub_amount'); //订阅分
            if(empty($sub_amount)){
                return $this->failData('订阅分必须大于0');
            }
            if($bonus_amount > 0 && $sp_amount > 0){
                return $this->failData('特权分与奖金只能选择其中一种');
            }
            $total_amount = $sub_amount + $bonus_amount + $sp_amount;
            $meal_id = $this->_post('meal_id');//套餐id
            $mealM = new \addons\baodan\model\MealConfig();
            $meal = $mealM->getDetail($meal_id);
            $price = $meal['price'];
            if($total_amount != $price){
                return $this->failData('购买套餐所需金额不正确');
            }
            if($bonus_amount > $price / 2){
                return $this->failData('使用的奖金金额不能超过50%');
            }
            if($sp_amount > $price / 10){
                return $this->failData('使用的特权积分不可超过10%');
            }
            try{
                $balanceM = new \addons\member\model\Balance();
                $recordM = new \addons\member\model\TradingRecord();
                $record_type = 4; //注册
                $change_type = 0; // 0 = 减少,1=增加
                $remark = '用户注册';
                $u->startTrans();
                if(!empty($sub_amount)){
                    $balance_type = 1;//订阅费
                    $sub_balance = $balanceM->verifyStock($this->user_id, $sub_amount, $balance_type);
                    if(empty($sub_balance)){
                        return $this->failData('余额不足');
                    }
                    $sub_balance = $balanceM->updateBalance($this->user_id, $balance_type, $sub_amount);
                    if(!empty($sub_balance)){
                        //添加消费记录
                        $recordM->addRecord($this->user_id, $sub_amount, $sub_balance['before_amount'], $sub_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                    }
                }
                if(!empty($bonus_amount)){
                    $balance_type = 3;//奖金
                    $bonus_balance = $balanceM->verifyStock($this->user_id, $bonus_amount, $balance_type);
                    if(empty($bonus_balance)){
                        return $this->failData('余额不足');
                    }
                    $bonus_balance = $balanceM->updateBalance($this->user_id, $balance_type, $bonus_amount);
                    if(!empty($bonus_balance)){
                        //添加消费记录
                        $recordM->addRecord($this->user_id, $bonus_amount, $bonus_balance['before_amount'], $bonus_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                    }
                    
                }else if(!empty($sp_amount)){
                    $balance_type = 4;//特权分
                    $sp_balance = $balanceM->verifyStock($this->user_id, $sp_amount, $balance_type);
                    if(empty($sp_balance)){
                        return $this->failData('余额不足');
                    }
                    $sp_balance = $balanceM->updateBalance($this->user_id, $balance_type, $sp_amount);
                    if(!empty($sp_balance)){
                        //添加消费记录
                        $recordM->addRecord($this->user_id, $sp_amount, $sp_balance['before_amount'], $sp_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                    }
                }
                $record_type = 4;//复投
                $change_type = 1;
                $cgjs_amount = $this->_countAmount($price, $meal['lever']);
                $balance_type = 2;
                $zzzc_amount = $this->_multiAmount($price);
                $cgjs_balance = $balanceM->updateBalance($this->user_id, $balance_type, $cgjs_amount,true);
                if(!empty($cgjs_balance)){
                    $recordM->addRecord($this->user_id, $cgjs_amount, $cgjs_balance['before_amount'], $cgjs_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                }
                //更新zzzc
                $balance_type = 5;
                $zzzc_balance = $balanceM->updateBalance($this->user_id, $balance_type, $zzzc_amount,true);
                if(!empty($zzzc_balance)){
                    $recordM->addRecord($this->user_id, $zzzc_amount, $zzzc_balance['before_amount'], $zzzc_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                }
                //更新自身业绩, 和套餐id 
                $data = $u->getDetail($this->user_id,'id,aid,pid,center_id,position,self_total,real_name,phone,address');
                $data['self_total'] = $data['self_total'] + $price;
                
                $data['meal_id'] = $meal_id;
                $u->save($data);
                
                $orderM = new \addons\baodan\model\MealOrder();
                $order_code = $this->newOrderCode();
                $order_id = $orderM->addOrder($this->user_id, $meal_id, $order_code, $sub_amount, $bonus_amount, $sp_amount, $data['real_name'], $data['phone'], $data['address']);
                if($order_id > 0){
                    //更新团队直推业绩
                    $u->updateTeamDirectTotal($this->user_id, $price);
//                        发放奖金
                    if(!empty($sp_amount)){
                        $price = $price * 0.9; //使用特权分, 奖金按90% 计算
                    }
                    $has_send = $this->_sendBonus($this->user_id,$data['aid'],$this->user_id,$data['position'], $price,$meal['devlop_bonus_rate'],$meal['bonus_limit'],$data['center_id']);
                    if($has_send['success'] == false){
                        $u->rollback();
                        return $this->failData($has_send['message']);
                    }
                }
                $u->commit();
                return $this->successData();
                
            }catch (\Exception $ex) {
                $balanceM->rollback();
                return $this->failData($ex->getMessage());
            }
        }else{
            $u = new \addons\member\model\MemberAccountModel();
            $meal_id = $u->where('id='.$this->user_id)->value('meal_id');
            // 2018年10月14日14:36:47 未购买套餐,跳转第一次购买套餐页面
            if(!empty($meal_id)){
                $url = url("levelup");
                $this->redirect($url);
                exit;
            }
            $m = new \addons\baodan\model\MealConfig();
            $list = $m->getDataList(-1,-1,'','','id asc');
            $this->assign('meals',$list);
            //查询是否用户中心
            $is_center = $u->userIsCenter($this->user_id);
            $this->assign('is_center', $is_center);
            //查询订阅分 1 ,奖金 3 ,特权分余额 4
            $b = new \addons\member\model\Balance();
            $sub_amount = $b->getBalanceAmountByType($this->user_id, 1);
  
            $this->assign('sub_amount',$sub_amount);
            $bonus_amount = $b->getBalanceAmountByType($this->user_id, 3);
            $this->assign('bonus_amount',$bonus_amount);
            if($is_center == 1){
                $sp_amount = $b->getBalanceAmountByType($this->user_id, 4);
                $this->assign('sp_amount',$sp_amount);
            }
            return $this->fetch();
        }
    }
    
    public function bucha(){
        if(IS_POST){
            $u = new \addons\member\model\MemberAccountModel();
            $m = new \addons\baodan\model\MealConfig();
            //是否需要获取特权分字段
            $is_center = $u->userIsCenter($this->user_id);
            $sp_amount = 0;
            $bonus_amount = 0;
            if($is_center == 1){
                $sp_amount = $this->_post('sp_amount'); //特权分
            }
            $bonus_amount = $this->_post('bonus_amount'); //奖金
            $sub_amount = $this->_post('sub_amount'); //订阅分
            if(empty($sub_amount)){
                return $this->failData('订阅分必须大于0');
            }
            if($bonus_amount > 0 && $sp_amount > 0){
                return $this->failData('特权分与奖金只能选择其中一种');
            }
            $total_amount = $sub_amount + $bonus_amount + $sp_amount;
            $meal_id = $this->_post('meal_id');//套餐id
            $mealM = new \addons\baodan\model\MealConfig();
            $meal = $mealM->getDetail($meal_id);
            $price = $meal['price'];
            $current_meal_id = $u->where('id='.$this->user_id)->value('meal_id');
            $current_price = $m->where('id='.$current_meal_id)->value('price');
            $price = $price - $current_price;
            if($total_amount != $price){
                return $this->failData('购买套餐所需金额不正确');
            }
            if($bonus_amount > $price / 2){
                return $this->failData('使用的奖金金额不能超过50%');
            }
            if($sp_amount > $price / 10){
                return $this->failData('使用的特权积分不可超过10%');
            }
            try{
                $balanceM = new \addons\member\model\Balance();
                $recordM = new \addons\member\model\TradingRecord();
                $record_type = 6; //补差升级
                $change_type = 0; // 0 = 减少,1=增加
                $remark = '用户补差升级';
                $balanceM->startTrans();
                if(!empty($sub_amount)){
                    $balance_type = 1;//订阅费
                    $sub_balance = $balanceM->verifyStock($this->user_id, $sub_amount, $balance_type);
                    if(empty($sub_balance)){
                        return $this->failData('余额不足');
                    }
                    $sub_balance = $balanceM->updateBalance($this->user_id, $balance_type, $sub_amount);
                    if(!empty($sub_balance)){
                        //添加消费记录
                        $recordM->addRecord($this->user_id, $sub_amount, $sub_balance['before_amount'], $sub_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                    }
                }
                if(!empty($bonus_amount)){
                    $balance_type = 3;//奖金
                    $bonus_balance = $balanceM->verifyStock($this->user_id, $bonus_amount, $balance_type);
                    if(empty($bonus_balance)){
                        return $this->failData('余额不足');
                    }
                    $bonus_balance = $balanceM->updateBalance($this->user_id, $balance_type, $bonus_amount);
                    if(!empty($bonus_balance)){
                        //添加消费记录
                        $recordM->addRecord($this->user_id, $bonus_amount, $bonus_balance['before_amount'], $bonus_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                    }
                    
                }else if(!empty($sp_amount)){
                    $balance_type = 4;//特权分
                    $sp_balance = $balanceM->verifyStock($this->user_id, $sp_amount, $balance_type);
                    if(empty($sp_balance)){
                        return $this->failData('余额不足');
                    }
                    $sp_balance = $balanceM->updateBalance($this->user_id, $balance_type, $sp_amount);
                    if(!empty($sp_balance)){
                        //添加消费记录
                        $recordM->addRecord($this->user_id, $sp_amount, $sp_balance['before_amount'], $sp_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                    }
                }
                $record_type = 6;//补差升级
                $change_type = 1;
                $cgjs_amount = $this->_countAmount($price, $meal['lever']);
                $balance_type = 2;
                $zzzc_amount = $this->_multiAmount($price);
                $cgjs_balance = $balanceM->updateBalance($this->user_id, $balance_type, $cgjs_amount,true);
                if(!empty($cgjs_balance)){
                    $recordM->addRecord($this->user_id, $cgjs_amount, $cgjs_balance['before_amount'], $cgjs_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                }
                //更新zzzc
                $balance_type = 5;
                $zzzc_balance = $balanceM->updateBalance($this->user_id, $balance_type, $zzzc_amount,true);
                if(!empty($zzzc_balance)){
                    $recordM->addRecord($this->user_id, $zzzc_amount, $zzzc_balance['before_amount'], $zzzc_balance['amount'], $balance_type, $record_type, $change_type, 0, $remark);
                }
                //更新自身业绩, 和套餐id 
                $user_data = $u->getDetail($this->user_id,'id,self_total,meal_id');
                $user_data['self_total'] = $user_data['self_total'] + $price;
                $user_data['meal_id'] = $meal_id;
               
                $u->save($user_data);
                $balanceM->commit();
                return $this->successData();
                
            } catch (\Exception $ex) {
                $balanceM->rollback();
                return $this->failData($ex->getMessage());
            }
            
        }else{
            $u = new \addons\member\model\MemberAccountModel();
            $meal_id = $u->where('id='.$this->user_id)->value('meal_id');
            // 2018年10月14日14:36:47 未购买套餐,跳转第一次购买套餐页面
            if(empty($meal_id)){
                $url = url("firstblood");
                $this->redirect($url);
                exit;
            }
            $m = new \addons\baodan\model\MealConfig();
            $list = $m->getDataList(-1,-1,'id>'.$meal_id,'','id asc');
            $this->assign('meals',$list);
            //查询当前套餐, 金额
            $current_price = $m->where('id='.$meal_id)->value('price');
            $this->assign('current_price',$current_price);
            //查询是否用户中心
            $is_center = $u->userIsCenter($this->user_id);
            $this->assign('is_center', $is_center);
            //查询订阅分 1 ,奖金 3 ,特权分余额 4
            $b = new \addons\member\model\Balance();
            $sub_amount = $b->getBalanceAmountByType($this->user_id, 1);
  
            $this->assign('sub_amount',$sub_amount);
            $bonus_amount = $b->getBalanceAmountByType($this->user_id, 3);
            $this->assign('bonus_amount',$bonus_amount);
            if($is_center == 1){
                $sp_amount = $b->getBalanceAmountByType($this->user_id, 4);
                $this->assign('sp_amount',$sp_amount);
            }
            return $this->fetch();
        }
    }
    
    
}

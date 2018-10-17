<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace web\index\controller;

/**
 * Description of Crontab
 *
 * @author shilinqing
 */
class Crontab extends \web\common\controller\BaseController {
    
    //释放奖金, 超出部分按会员等级杠杆回股数基金
//    10=推荐奖金，11=对碰奖金，12=管理奖金，13=领导奖金，14=报单中心奖金，15=复投奖金
    public function releaseBonus(){
//        $day = date('d');
//        $i  = $day / 10; 
//        if(($day / 10) != 1 || ($day / 10) != 2 ||($day / 10) != 3){
//            exit;
//        }
        $limit = 100;
        $m = new \addons\member\model\TotalBonusRecord();
        $b = new \addons\member\model\Balance();
        $u = new \addons\member\model\MemberAccountModel();
        $recordM = new \addons\member\model\TradingRecord();
        $data = $m->getCrontabList();
        try{
            foreach($data as $k => $bonus){
                $user_id = $bonus['user_id'];
                $_user = $u->where('id='.$user_id)->field('meal_id,is_backfill,advance_amount')->find();
                $meal_id = $_user['meal_id'];
                
                $is_backfill = $_user['is_backfill']; //是否为回填账号 
                $advance_amount = $_user['advance_amount'];//剩余需回填金额
                
                $total_bonus = $bonus['total_bonus'];
                $invite_bonus = $bonus['invite_bonus'];
                $duipen_bonus = $bonus['duipen_bonus'];
                $manage_bonus = $bonus['manage_bonus'];
                $leader_bonus = $bonus['leader_bonus'];
                $center_bonus = $bonus['center_bonus'];
                $recast_bonus = $bonus['recast_bonus'];
                
                if($total_bonus <= 0){
                    //没有奖金
                    continue;
                }
                if($is_backfill == 1 && $advance_amount > 0){
                    //需要回填
                    $backfill_amount = $total_bonus * 0.1; //扣出10%作为回填
                    if($backfill_amount > $advance_amount){
                        $backfill_amount = $advance_amount;
                    }
                    $_user['id'] = $user_id;
                    $_user['advance_amount'] = $advance_amount - $backfill_amount;
                    $u->save($_user);//更新需要回填的金额
                    
                    $total_bonus =  $total_bonus - $backfill_amount;//总数-回填金额 = 要结算的余额
                    
                }
                //更新奖金总金额
                //查询 type 2 参股基数余额
                $type = 2;
                $cgjs_balance = $b->getBalanceByType($user_id, $type);
                $cgjs_balance['before_amount'] = $cgjs_balance['amount'];
                if($total_bonus <= $cgjs_balance['amount']){
                    //减少参股基数余额
                    $cgjs_balance['amount'] = $cgjs_balance['amount'] - $total_bonus;
 
                }else{
                    //奖金统计 比 参股基数大
                    $chajia = $total_bonus - $cgjs_balance['amount'];
                    $total_bonus = $cgjs_balance['amount'];
                    
                    $mealM = new \addons\baodan\model\MealConfig();
                    $lever = $mealM->where('id='.$meal_id)->value('lever');
                    $cgjs_balance['amount'] = $chajia * $lever; // 差价 x 杠杆
                    
                }
                $cgjs_balance['update_time'] = NOW_DATETIME;
                $b->save($cgjs_balance);
                $recordM->addRecord($user_id, $total_bonus, $cgjs_balance['before_amount'], $cgjs_balance['amount'], 2, 6, 0, 0, '释放参股基数');
                //更新奖金
                echo '发放奖金总额:'.$total_bonus;
                echo '<br />';
                $bonus_balance = $b->updateBalance($user_id, 3, $total_bonus, true);
                //添加记录
                $change_type = 1; //增加
                $asset_type = 3; //资产类型 3= 奖金
                $before_amount = $bonus_balance['before_amount'];
                if($invite_bonus > 0){
                    $record_type = 10; 
                    $remark = '发放推荐奖金';
                    $after_amount = $before_amount + $invite_bonus;
                    $recordM->addRecord($user_id, $invite_bonus, $before_amount, $after_amount, $asset_type, $record_type, $change_type, 0, $remark);
                    $before_amount = $after_amount;
                    
                }
                if($duipen_bonus > 0){
                    $record_type = 11; 
                    $remark = '发放对碰奖金';
                    $after_amount = $before_amount + $duipen_bonus;
                    $recordM->addRecord($user_id, $duipen_bonus, $before_amount, $after_amount, $asset_type, $record_type, $change_type, 0, $remark);
                    $before_amount = $after_amount;
                    
                }
                if($manage_bonus > 0){
                    $record_type = 12; 
                    $remark = '发放管理奖金';
                    $after_amount = $before_amount + $manage_bonus;
                    $recordM->addRecord($user_id, $manage_bonus, $before_amount, $after_amount, $asset_type, $record_type, $change_type, 0, $remark);
                    $before_amount = $after_amount;
                }
                if($leader_bonus > 0){
                    $record_type = 13; 
                    $remark = '发放领导奖金';
                    $after_amount = $before_amount + $leader_bonus;
                    $recordM->addRecord($user_id, $leader_bonus, $before_amount, $after_amount, $asset_type, $record_type, $change_type, 0, $remark);
                    $before_amount = $after_amount;
                }
                if($center_bonus > 0){
                    $record_type = 14; 
                    $remark = '发放报单中心奖金';
                    $after_amount = $before_amount + $center_bonus;
                    $recordM->addRecord($user_id, $center_bonus, $before_amount, $after_amount, $asset_type, $record_type, $change_type, 0, $remark);
                    $before_amount = $after_amount;
                }
                if($recast_bonus > 0){
                    $record_type = 15; 
                    $remark = '发放复投奖金';
                    $after_amount = $before_amount + $recast_bonus;
                    $recordM->addRecord($user_id, $recast_bonus, $before_amount, $after_amount, $asset_type, $record_type, $change_type, 0, $remark);
                    $before_amount = $after_amount;
                }
                //清空字段
                $bonus['total_bonus'] = 0;
                $bonus['invite_bonus'] = 0;
                $bonus['duipen_bonus'] = 0;
                $bonus['manage_bonus'] = 0;
                $bonus['leader_bonus'] = 0;
                $bonus['center_bonus'] = 0;
                $bonus['recast_bonus'] = 0;
                $m->save($bonus);
                
            }
            
//            $m->commit();
        } catch (\Exception $ex) {
//            $m->rollback();
            echo $ex->getMessage();
        }
        
    }
    
    /**
     * 计算每日波比
     */
    public function countDailyTotal(){
        $today = date('Y-m-d');
        $today = date('Y-m-d',strtotime("$today -1 day"));
        $start_time = date('Y-m-d H:i:s',mktime(0, 0, 0, date('m'), date('d')-1, date('Y')));
        $end_time = date('Y-m-d H:i:s',mktime(23, 59, 59 , date('m'), date('d')-1, date('Y')));

        $m = new \addons\member\model\BonusRecord();
        $where['update_time'] = array(">=",$start_time);
        $where1['update_time'] = array("<=",$end_time);
        $daily_total_bonus = $m->where($where)->where($where1)->sum('amount');
//        dump($daily_total_bonus);
        $t = new \addons\member\model\TradingRecord();
        $where['type'] = 4;
        $total_regiser_use = $t->where($where)->where($where1)->sum('amount');
//        dump($total_regiser_use);
        $d = new \addons\member\model\DailyTotalRecord();
        dump($today);
        $where2['date'] = $today;
        $has_data = $d->where($where2)->find();
        if(empty($has_data)){
            $_data['total_register_amount'] = $total_regiser_use;
            $_data['total_bonus_amount'] = $daily_total_bonus;
            $_data['date'] = $today;
            $d->add($_data);
        }else{
            dump($has_data);
        }
        
    }
    
}

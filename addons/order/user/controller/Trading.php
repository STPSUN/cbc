<?php

namespace addons\order\user\controller;

/**
 @author zhuangminghan 
 * 订单管理
 */
class Trading extends \web\user\controller\AddonUserBase {

    public function index() {
        $type = $this->_get('type');
        if ($type == '') {
            $type = 0;
        }
        $this->assign('type', $type);
        return $this->fetch();
    }

    public function loadList() {
        $keyword = $this->_get('keyword');
        $type = $this->_get('type');
        $filter = 'type=' . $type;
        if ($keyword != null) {
            $filter .= ' and sphone like "%' . $keyword . '%" or order_id like "%' . $keyword . '%" or amount like "%' . $keyword . '%"';
        }
        // echo $filter.'<br>';
        $r = new \addons\member\model\Trading();
        $total = $r->getTrandTotal($filter);
        $rows = $r->getList($filter,$this->getPageIndex(), $this->getPageSize());
        return $this->toDataGrid($total, $rows);
    }


    /**
    * 取消订单
    */
    public function cancleOrder(){
        $id = $this->_get('id');
        $r = new \addons\member\model\Trading();
        $info = $r->findTrad($id);
        if($info['type']==0||$info['type']==2){
            if($info['trans_mode']){
                $info['status'] = 1;
                $info['update_time'] = NOW_DATETIME;
                $user_id = $info['user_id'];
                $balanceM = new \addons\member\model\Balance();
                $balanceM->startTrans();
                $coin_id = 4;
                $amount = $info['number'];
                $userAmount = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failData('增加CBC余额失败');
                }

                $type = 8;
                $change_type = 1; //增加
                $remark = '系统取消，增加可用余额';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failData('增加记录失败');
                }

                $TransferM = new \addons\member\model\Transfer();
                $res = $TransferM->updateQuota($user_id,$amount,1);
                if(!$res){
                    $balanceM->rollback();
                    $this->failJSON('增加用户挂卖额度失败');
                }
                
                $res = $r->save($info);
                if($res){
                    $balanceM->commit();
                    return $this->successData('取消成功');
                }else{
                    $balanceM->rollback();
                    return $this->failData('取消失败');
                } 
            }else{
                $info['status'] = 1;
                $info['update_time'] = NOW_DATETIME;
                $user_id = $info['user_id'];
                $balanceM = new \addons\member\model\Balance();
                $balanceM->startTrans();
                $coin_id = 2;
                $amount = $info['number'];
                $userAmount = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failData('增加CBC余额失败');
                }

                $type = 8;
                $change_type = 1; //增加
                $remark = '系统取消，增加可用余额';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failData('增加记录失败');
                }


                $coin_id = 1;
                $amount = bcmul(($info['number']+$info['fee_num']), 1,2);
                $userAmount = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failData('增加CBC总额失败');
                }

                $type = 8;
                $change_type = 1; //增加
                $remark = '系统取消，增加总额';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failData('增加记录失败');
                }

                $coin_id = 3;//CBC
                $total = bcmul(($info['number']+$info['fee_num']), 1,2);
                $userAmount = $balanceM->updateBalance($user_id,$coin_id,$total);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failData('减少CBC锁仓失败');
                }
                $type = 8;
                $change_type = 0; //减少
                $remark = '系统取消，减少锁仓';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $total, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, 0,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failData('增加记录失败');
                }
                $TransferM = new \addons\member\model\Transfer();
                $res = $TransferM->updateQuota($user_id,$info['number'],1);
                if(!$res){
                    $balanceM->rollback();
                    $this->failJSON('增加用户挂卖额度失败');
                }

                $res = $r->save($info);
                if($res){
                    $balanceM->commit();
                    return $this->successData('取消成功');
                }else{
                    $balanceM->rollback();
                    return $this->failData('取消失败');
                } 
            }
                
        }elseif($info['type']==1){
            $info['type'] = 0;
            $info['to_user_id'] = 0;
            $info['update_time'] = NOW_DATETIME;
            $res = $r->save($info);
            if($res) return $this->successData('取消成功');
            else return $this->failData('取消失败');
        }else{
            return $this->failData('无法取消');
        }
    }


    /**
    * 确认收款
    */
    public function confirmMoney(){
        $id = $this->_get('id');
        $r = new \addons\member\model\Trading();
        $trading = $r->findTrad($id);
        if(!$trading) return $this->failData('错误的订单');
        $balanceM = new \addons\member\model\Balance();
        $balanceM->startTrans();
        if($trading['type']==2){
            if($trading['trans_mode']){
                $user_id = $trading['user_id'];
                $trading['type'] = 3;
                $trading['update_time'] = NOW_DATETIME;
                $res = $r->save($trading);
                if(!$res){
                    $balanceM->rollback();
                    return $this->failData('订单保存失败');
                }
                $coin_id = 4;//CBC余额
                $userAmount = $balanceM->updateBalance($trading['to_user_id'],$coin_id,$trading['number'],1);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failData('增加CBC失败');
                }
                $type = 7;
                $change_type = 1; //增加
                $remark = '系统确认收款，增加激活码';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($trading['to_user_id'], $trading['number'], $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type,$user_id ,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failData('增加记录失败');
                }
            }else{
                $user_id = $trading['user_id'];
                $trading['type'] = 3;
                $trading['update_time'] = NOW_DATETIME;
                $res = $r->save($trading);
                if(!$res){
                    $balanceM->rollback();
                    return $this->failData('订单保存失败');
                }
                $coin_id = 4;//CBC余额
                $userAmount = $balanceM->updateBalance($trading['to_user_id'],$coin_id,$trading['number'],1);
                if(!$userAmount){
                    $balanceM->rollback();
                    return $this->failData('增加CBC失败');
                }
                $type = 7;
                $change_type = 1; //增加
                $remark = '系统确认收款，增加激活码';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($trading['to_user_id'], $trading['number'], $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type,$user_id ,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failData('增加记录失败');
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
                    return $this->failData('减少CBC锁仓失败');
                }
                $type = 7;
                $change_type = 0; //减少
                $remark = '系统确认收款，减少锁仓';
                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type, $user_id,$remark);
                if(!$r_id){
                    $balanceM->rollback();
                    return $this->failData('增加记录失败');
                }
                $AwardService = new \web\api\service\AwardService();
                $res = $AwardService->tradingReward($trading['fee_num'],$trading['user_id']);
                //计算奖金
                if(!$res){
                    $balanceM->rollback();
                    return $this->failJSON('奖金发放失败');
                }
            }
            $TransferM = new \addons\member\model\Transfer();
            $info = $TransferM->findData($trading['to_user_id']);
            if($info){
                $info['today_quota'] = $info['today_quota']+$trading['number']*2;
                $info['today_at'] = NOW_DATETIME;
                $info['update_at'] = NOW_DATETIME;
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
                    'update_at'     => NOW_DATETIME,
                    'create_at'     => NOW_DATETIME,
                ];
                $res = $TransferM->add($arr);
                if(!$res){
                    $balanceM->rollback();
                    return $this->failJSON(lang('TRANSFER_QUOTA_UPDATE_FAIL'));
                }
            }
            $balanceM->commit();
            return $this->successData('确认收款成功');
        }else{
            return $this->failData('无法确认收款');
        }
    }

}

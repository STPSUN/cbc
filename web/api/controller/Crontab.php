<?php

namespace web\api\controller;

use think\Lang;
use think\Log;
use web\api\model\AwardIssue;
use web\api\model\MemberNodeIncome;
use web\api\service\AwardService;
use web\api\service\NodeService;

class Crontab extends \web\common\controller\Controller {


    protected function _initialize() {
        
    }

    /**
     * 定时器访问
     * 超过30分钟未付款则取消订单
     */
    public function cancleOrder(){
        $tradingM = new \addons\member\model\Trading();
        $map['type'] = 1;
        $map['status'] = 0;
        $map['update_time'] = ['lt',date('Y-m-d H:i:s',(time()-60*60))];
        $list = $tradingM->where($map)->select();
        if(!$list) return $this->failJSON('no trading list');
        $TradingLog = new \addons\member\model\TradingLog();
        foreach ($list as $key => $value) {
            $data = $value;
            $data['to_user_id'] = 0;
            $data['type'] = 0;
            $data['update_time']=NOW_DATETIME;

            $infoLog = [
                'order_id'=>$value['order_id'],
                'user_id'=>0,
                'remark'=>'自动取消',
                'create_at'=>NOW_DATETIME,
                'type'=>8,
            ];
            $TradingLog->add($infoLog);
            $res = $tradingM->save($data);
        }
        if($res) $this->successJSON('update success');
        else $this->failJSON('update failed');
    }

    /**
     * 定时恢复挂卖金额
     * 恢复每日总量
     * 更新用户额度
     */
    public function auto_quota(){
        $TransferM = new \addons\member\model\Transfer();
        $sysM = new \web\common\model\sys\SysParameterModel();
        $total = $sysM->getValByName('total_transaction');
        $res =  $sysM->setValByName('less_total',$total);
        $page = 0;
        $map['update_at'] = ['lt',date('Y-m-d').' 00:00:00'];
        $map['today_quota'] = ['neq',0];
        $list = $TransferM->where($map)->limit($page,5000)->select();
        while ($list) {
            $time = date('Y-m-d H:i:s');
            $page++;
            foreach ($list as $k => $v) {
                if($v['today_quota']!=0){
                    $list[$k]['quota'] = $v['today_quota']+$v['quota'];
                    $list[$k]['today_quota'] = 0;
                    // $list[$k]['can_sell'] = $v['can_sell']+1;
                    $list[$k]['today_at'] = $time;
                    $list[$k]['quota_at'] = $time;
                    $list[$k]['update_at'] = $time;
                }
            }
            $TransferM->saveAll($list);
            $list = $TransferM->limit($page*5000,5000)->select();
        }
        echo 'success';
    }

    /**
     * 定时释放理财奖金
     */
    public function auto_receive(){
        $finaM = new \addons\member\model\Financial();
        $map['end_at'] = ['lt',date('Y-m-d H:i:s')];
        $list = $finaM->where($map)->select();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        if(empty($list)) return $this->failJSON('no financial list');
        foreach ($list as $key => $value) {
            $info = $value;
            $user_id = $info['user_id'];
            $financial_id = $info['id'];
            $balanceM->startTrans();
            $type = 12;
            $coin_id = 4;
            $amount = $info['interset']+$info['amount'];
            $userAsset = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
            if(!$userAsset){
                $balanceM->rollback();
                return $this->failJSON(lang('COMMON_ADD_AMOUNT_WRONG'));
            }
            $in_record_id = $recordM->addRecord($user_id, $amount, $userAsset['before_amount'], $userAsset['amount'], $coin_id, $type,1, $user_id,'用户理财');
            if(empty($in_record_id)){
                $balanceM->rollback();
                return $this->failJSON(lang('COMMON_UPDATE_FAIL'));
            }

            $data = [
                    'status'        =>1,
                    'update_at'     =>date('Y-m-d H:i:s'),
            ];
            $res = $finaM->where(['id'=>$financial_id])->update($data);
            if(!$res){
                $balanceM->rollback();
                return $this->failJSON(lang('INVESTMENT_ADD_FAIL'));
            }
            $balanceM->commit();
            return $this->successJSON();
        }
            
    }

    /**
     * 节点释放
     */
    public function nodeRelease()
    {
        return true;
        set_time_limit(0);
        $incomeM = new MemberNodeIncome();
        $is_release = $incomeM->whereTime('create_time','today')->find();
        if(!empty($is_release))
            return;

        $nodeS = new NodeService();

        $nodeS->nodeRelease();

        // $nodeS->updateBalanceReleaseNum();
    }

    public function person_relase(){
        $wsas = $this->_post('wsas845');
        if($wsas!='wsas888') return false;
        $user_id = $this->_post('user_id');
        $nodeS = new NodeService();
        $nodeS->ReissueNode($user_id);
    }
    /**
     * 超级节点释放，奖励发放
     */
    public function superNodeAward()
    {
        return true;
        set_time_limit(0);
        $awardIssue = new AwardIssue();
        $awardS = new AwardService();

        $data = $awardIssue->where('status',1)->limit(1)->select();

        $awardIssue->startTrans();
        try
        {
            foreach ($data as $v)
            {
                $awardS->tradingReward($v['amount'],$v['user_id']);
                $awardIssue->save([
                    'status' => 2,
                    'update_time' => NOW_DATETIME
                ],[
                    'id' => $v['id'],
                ]);

                Log::record('超级节点奖励释放成功：' . $v['user_id']);
            }

            $awardIssue->commit();
        }catch (\Exception $e)
        {
            $awardIssue->rollback();
            Log::record('超级节点奖励释放失败');
        }
    }

    /**
     * 输出错误JSON信息。
     * @param type $message     
     */
    protected function failJSON($message) {
        $message = lang($message);
        $jsonData = array('success' => false, 'message' => $message);
        $json = json_encode($jsonData, true);
        echo $json;
        exit;
    }

    /**
     * 输出成功JSON信息
     * @param type $data
     */
    protected function successJSON($data = NULL, $msg = "success") {
        if (is_array($data) || is_object($data)) {
            $data = $this->_setDataLang($data);
        }
        $jsonData = array('success' => true, 'data' => $data, 'message' => $msg);
        $json = json_encode($jsonData, 1);
        echo $json;
        exit;
    }

    /**
     * 新增balance缺失
     */
    public function addBalance(){
        $sql = 'select user_id from tp_member_balance GROUP BY user_id HAVING count(user_id)<5';
        // $sql = 'select id user_id from tp_member_account WHERE id not in (select user_id from tp_member_balance GROUP BY user_id)';
        $balanceM = new \addons\member\model\Balance();
        $list = $balanceM->query($sql);
        foreach ($list as $key => $value) {
            $arr = $balanceM->where(['user_id'=>$value['user_id']])->select();
            $one1 = false;
            $one2 = false;
            $one3 = false;
            $one4 = false;
            $one5 = false;
            foreach ($arr as $k => $v) {
                if($v['type']==1){
                    $one1 = true;
                }elseif($v['type']==2){
                    $one2 = true;
                }elseif($v['type']==3){
                    $one3 = true;
                }elseif($v['type']==4){
                    $one4 = true;
                }elseif($v['type']==5){
                    $one5 = true;
                }
            }
            if(!$one1){
                $data = [
                    'user_id'=>$value['user_id'],
                    'type'=>1,
                    'update_time'=>NOW_DATETIME,
                ];
                $balanceM->add($data);
            }
            if(!$one2){
                $data = [
                    'user_id'=>$value['user_id'],
                    'type'=>2,
                    'update_time'=>NOW_DATETIME,
                ];
                $balanceM->add($data);
            }
            if(!$one3){
                $data = [
                    'user_id'=>$value['user_id'],
                    'type'=>3,
                    'update_time'=>NOW_DATETIME,
                ];
                $balanceM->add($data);
            }
            if(!$one4){
                $data = [
                    'user_id'=>$value['user_id'],
                    'type'=>4,
                    'update_time'=>NOW_DATETIME,
                ];
                $balanceM->add($data);
            }
            if(!$one5){
                $data = [
                    'user_id'=>$value['user_id'],
                    'type'=>5,
                    'update_time'=>NOW_DATETIME,
                ];
                $balanceM->add($data);
            }
        }

    }



    /**
     * 释放所有节点奖励
     */
    public function releaseAllNode($page=0){
        set_time_limit(0);
        $nodeS = new \web\api\model\MemberNode;
        $nodeIncomeS = new \web\api\model\MemberNodeIncome;
        $redis = \think\Cache::connect(\think\Config::get('global_cache'));
        $page = $redis->get('release_page');
        // $page = 2000;
        if($page>=0){
            $page = $page+1000;
        }else{
            $page = 0;
        }
        
        echo '---'.$page.'---';
        $redis->set('release_page',$page);
        $map['type'] = ['in','2,3,4,5,6,7'];
        $allnode = $nodeS->field('id,type,user_id,sum(node_num) as node_num,sum(total_num) as total_num')->where($map)->group('user_id')->limit($page,1000)->select();
        // $supernode = $nodeS->field('id,type,user_id,sum(node_num) as node_num,sum(total_num) as total_num')->where(['type'=>8])->group('user_id')->select();
        // $superrelease = $nodeIncomeS->where(['type'=>8])->field('user_id,sum(amount) amount')->group('user_id')->select();
        // foreach ($supernode as $k => $v) {
        //     $supernode[$k]['can_release'] = $v['total_num']*$v['node_num'];
        //     foreach ($superrelease as $key => $value) {
        //         if($v['user_id']==$value['user_id']){
        //             $less = $v['total_num']*$v['node_num']-$value['amount'];
        //             $supernode[$k]['can_release'] = $less;
        //         }
        //     }
        //     if($supernode[$k]['can_release']>0){
        //         $this->relasenode($v['user_id'],$supernode[$k]['can_release'],$v['id'],$nodeIncomeS,$v['type']);
        //     }
        // }
        // print_r($allnode);
        if(!$allnode){
            exit();
        }
        $id = [];
        foreach ($allnode as $key => $value) {
            $id[] = $value['user_id'];
        }
        $where['user_id'] = ['in',$id];
        $where['type'] = ['in','2,3,4,5,6,7'];
        $allrelease = $nodeIncomeS->where($where)->field('user_id,sum(amount) amount')->group('user_id')->select();
        foreach ($allnode as $k => $v) {
            $allnode[$k]['can_release'] = $v['total_num'];
            foreach ($allrelease as $key => $value) {
                if($v['user_id']==$value['user_id']){
                    $less = $v['total_num']-$value['amount'];
                    $allnode[$k]['can_release'] = $less;
                }
            }
            if($allnode[$k]['can_release']>0){
                $this->relasenode($v['user_id'],$allnode[$k]['can_release'],$v['id'],$nodeIncomeS,$v['type']);
            }
        }
        echo '||success---page:'.$page;
        
        // $page = $page+500;
        // $this->releaseAllNode($page);
        // print_r($allnode);exit();
    }


    /**
     * 单点释放
     */
    public function relasenode($user_id,$amount,$member_node_id,$nodeIncomeS,$type){
        if($amount<0) return false;
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        $balanceM->startTrans();
        if($type==8){
            $type = 14;    
            $coin_id = 4;
            $change_type = 1; //增加
            $remark = '超级节点释放';
            $userAmount = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
            if(!$userAmount){
                $balanceM->rollback();
                return false;
            }

            $recordM = new \addons\member\model\TradingRecord();
            $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type,$user_id ,$remark);
            if(!$r_id){
                $balanceM->rollback();
                return false;
            }
        }else{
            $type = 14;    
            $coin_id = 1;
            $change_type = 1; //增加
            $remark = '节点释放';
            $userAmount = $balanceM->updateBalance($user_id,$coin_id,$amount,1);
            if(!$userAmount){
                $balanceM->rollback();
                return false;
            }

            $recordM = new \addons\member\model\TradingRecord();
            $r_id = $recordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type,$user_id ,$remark);
            if(!$r_id){
                $balanceM->rollback();
                return false;
            }

            $coin_id = 2;
            $fee = bcmul($amount, 0.7,2);

            $userAmount = $balanceM->updateBalance($user_id,$coin_id,$fee,1);
            if(!$userAmount){
                $balanceM->rollback();
                return false;
            }

            $r_id = $recordM->addRecord($user_id, $fee, $userAmount['before_amount'], $userAmount['amount'],$coin_id, $type,$change_type,$user_id ,$remark);
            if(!$r_id){
                $balanceM->rollback();
                return false;
            }
        }
        $data = ['user_id'=>$user_id,'amount'=>$amount,'create_time'=>NOW_DATETIME,'type'=>0,'member_node_id'=>$member_node_id];
        $res = $nodeIncomeS->add($data);
        if(!$res){
            $balanceM->rollback();
            return false;
        }     
        $balanceM->commit();

    }


    public function deleteSuper(){
        return false;
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        $nodeIncomeS = new \web\api\model\MemberNodeIncome;
        $map['type'] = 14;
        $map['remark'] = '超级节点释放';
        $list = $recordM->where($map)->group('user_id')->select();
        foreach ($list as $k => $v) {
            $where['user_id'] = $v['user_id'];
            $where['type'] = 4;
            $data['amount'] = $v['after_amount'];
            $data['before_amount'] = $v['before_amount'];
            $balanceM->where($where)->update($data);
            $maps['type'] = 14;
            $maps['remark'] = '超级节点释放';
            $maps['id'] = ['neq',$v['id']];
            $maps['user_id'] = $v['user_id'];

            echo $recordM->where($maps)->delete().'|||';
        }
    }


    public function deleteNode(){
        $limit = $this->_post('page');
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        $nodeIncomeS = new \web\api\model\MemberNodeIncome;
        $map['type'] = 14;
        $map['before_amount'] = ['neq',0];
        $map['update_time'] = ['gt','2018-11-18 14:00:00'];
        $map['remark'] = '节点释放';
        $list = $recordM->where($map)->group('user_id')->limit($limit,1000)->order('id desc')->select();
        foreach ($list as $k => $v) {
            $where['user_id'] = $v['user_id'];
            $where['type'] = 1;
            $data['amount'] = $v['before_amount'];
            $balanceM->where($where)->update($data);
            $maps['id'] = $v['id'];
            echo $recordM->where($maps)->delete().'|||';
        }
    }

    public function deleteNodeT(){
        $limit = $this->_post('page');
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        $nodeIncomeS = new \web\api\model\MemberNodeIncome;
        $map['type'] = 14;
        $map['asset_type'] = 2;
        $map['before_amount'] = ['neq',0];
        $map['update_time'] = ['gt','2018-11-18 14:00:00'];
        $map['remark'] = '节点释放';
        $list = $recordM->where($map)->group('user_id')->limit($limit,1000)->order('id desc')->select();
        foreach ($list as $k => $v) {
            $where['user_id'] = $v['user_id'];
            $where['type'] = 2;
            $data['amount'] = $v['before_amount'];
            $balanceM->where($where)->update($data);
            $maps['id'] = $v['id'];
            echo $recordM->where($maps)->delete().'|||';
        }
    }    


}


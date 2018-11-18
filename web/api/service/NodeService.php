<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/26
 * Time: 14:21
 */

namespace web\api\service;


use addons\member\model\Balance;
use addons\member\model\MemberAccountModel;
use addons\member\model\TradingRecord;
use think\Log;
use web\api\model\AwardIssue;
use web\api\model\MemberNode;
use web\api\model\MemberNodeIncome;
use web\api\model\Node;

class NodeService extends \web\common\controller\Service
{
    /**
     * 节点释放
     */
    public function nodeRelease()
    {
        set_time_limit(0);
        //CBC总额节点释放数据
        $total = $this->getNodeUsers(1);
        //可用节点释放数据
        $use = $this->getNodeUsers(2);
        //激活码节点释放数据
        $super_node = $this->getNodeUsers(4,8);

        //插入CBC总额流水记录
        $this->insertRecord($total,1);
        //插入可用流水记录
        $this->insertRecord($use,2);

        //更新总额余额
        $this->updateTotalBalance($total);
        //更新可用余额
        $this->updateUseBalance($use);

        //插入激活码流水记录
        $this->insertSuperNodeRecord($super_node);
        //更新激活码余额
        $this->updateKeyBalance($super_node);
    }

    public function ReissueNode($user_id){
        echo 'in list';
        if(!$user_id) return false;
        $nodeM = new MemberNode();
        $TradingRecordM = new TradingRecord();
        $BalanceM = new Balance();
        $nodedata = $nodeM->where(['user_id'=>$user_id])->select();
        $AwardIssue = new AwardIssue();
        $MemberNodeIncome = new MemberNodeIncome();
        $BalanceM->startTrans();
        foreach ($nodedata as $key => $value) {
            if($value['type']!=8){
                $release_num = $value['release_num'];
                $userAmount = $BalanceM->updateBalance($user_id,1,$release_num,1);
                if(!$userAmount){
                    $BalanceM->rollback();
                    return false;
                } 
                $r_id = $TradingRecordM->addRecord($user_id, $release_num, $userAmount['before_amount'], $userAmount['amount'],1, 14,1, 0,'节点释放');
                if(!$r_id){
                    $BalanceM->rollback();
                    return false;
                } 
                $amount = $release_num*0.7;
                $userAmount = $BalanceM->updateBalance($user_id,2,$amount,1);
                if(!$userAmount){
                    $BalanceM->rollback();
                    return false;
                } 
                $r_id = $TradingRecordM->addRecord($user_id, $amount, $userAmount['before_amount'], $userAmount['amount'],2, 14,1, 0,'节点释放');
                if(!$r_id){
                    $BalanceM->rollback();
                    return false;
                }
                $data = [
                    'member_node_id'=>$value['id'],
                    'create_time'=>NOW_DATETIME,
                    'amount'=>$release_num,
                    'type'=>$value['type'],
                    'user_id'=>$user_id,
                ];
                $MemberNodeIncome->add($data);
            }else{
                $release_num = $value['release_num']*0.7;
                $amount = $value['release_num']*0.3;
                $userAmount = $BalanceM->updateBalance($user_id,4,$release_num,1);
                if(!$userAmount){
                    $BalanceM->rollback();
                    return false;
                } 
                $r_id = $TradingRecordM->addRecord($user_id, $release_num, $userAmount['before_amount'], $userAmount['amount'],4, 14,1, 0,'超级节点释放');
                if(!$r_id){
                    $BalanceM->rollback();
                    return false;
                }
                $info = [
                    'member_node_id'=>$value['id'],
                    'create_time'=>NOW_DATETIME,
                    'amount'=>$release_num,
                    'type'=>$value['type'],
                    'user_id'=>$user_id,
                ];
                $MemberNodeIncome->add($info);

                $data['amount'] = $amount;
                $data['user_id'] = $user_id;
                $data['type'] = 1;
                $data['create_time'] = NOW_DATETIME;
                $res = $AwardIssue->add($data);
                if(!$res){
                    $BalanceM->rollback();
                    return false;
                }
            }
        }
        echo ' success';
        $BalanceM->commit();
    }
    /**
     * 超级节点释放，插入流水记录
     */
    private function insertSuperNodeRecord($nodes)
    {
        $incomeM = new MemberNodeIncome();
        $recordM = new TradingRecord();
        $awardIssueM = new AwardIssue();
        $total_record_sql = '';
        $toal_record_value = "";

        $award_sql = '';
        $award_value = '';

        $income_sql = '';
        $income_value = '';

        foreach ($nodes as $v)
        {
            $amount = bcmul($v['release_num'],0.7,2);
            $toal_record_value .= $v['user_id'] . ',' . $v['user_id'] . ',' . 4 . ',' . 14 . ',' . 1 . ',' . $amount . ',' . "'" . '节点释放' . "'" . ',' . "'" . NOW_DATETIME . "'" . ';';

            $award_amount = bcmul($v['release_num'],0.3,2);
            $award_value .= $award_amount . ',' . $v['user_id'] . ',' . 1 . ',' . "'" . NOW_DATETIME . "'" . ';';

            $income_value .= $v['member_node_id'] . ',' . $v['release_num'] . ',' . $v['type'] . ',' . $v['user_id'] . ',' . "'" . NOW_DATETIME . "'" . ';';
        }

        $toal_record_value = rtrim($toal_record_value,';');
        $toal_record_value = explode(';',$toal_record_value);

        $award_value = rtrim($award_value,';');
        $award_value = explode(';',$award_value);
        foreach ($toal_record_value as $v)
        {
            $total_record_sql .= '(' .$v . ')' . ',';
        }

        foreach ($award_value as $v)
        {
            $award_sql .= '(' . $v . ')' . ',';
        }

        $income_value = rtrim($income_value, ';');
        $income_value = explode(';',$income_value);

        foreach ($income_value as $v)
        {
            $income_sql .= '(' .$v . ')' . ',';
        }

        $total_record_sql = rtrim($total_record_sql,',');
        $record_sql = "insert into tp_trading_record (user_id,to_user_id,asset_type,type,change_type,amount,remark,update_time) values" . $total_record_sql;

        $award_sql = rtrim($award_sql,',');
        $income_sql = rtrim($income_sql,',');
        $sql = "insert into tp_award_issue (amount,user_id,status,create_time) values" . $award_sql;
        $incomeSql = "insert into tp_member_node_income (member_node_id,amount,type,user_id,create_time) values" . $income_sql;

        $recordM->execute($record_sql);
        $awardIssueM->execute($sql);
        $incomeM->execute($incomeSql);
    }

    //CBC总额插入流水记录
    private function insertRecord($nodes,$asset_type)
    {
        $recordM = new TradingRecord();
        $incomeM = new MemberNodeIncome();
        $total_record_sql = '';
        $toal_record_value = "";

        $income_sql = '';
        $income_value = '';
        if($asset_type == 1)
        {
            foreach ($nodes as $v)
            {
                $toal_record_value .= $v['user_id'] . ',' . $v['user_id'] . ',' . $asset_type . ',' . 14 . ',' . 1 . ',' . $v['release_num'] . ',' . "'" . '节点释放' . "'" . ',' . "'" . NOW_DATETIME . "'" . ';';

                $income_value .= $v['member_node_id'] . ',' . $v['release_num'] . ',' . $v['type'] . ',' . $v['user_id'] . ',' . "'" . NOW_DATETIME . "'" . ';';
            }
        }else
        {
            foreach ($nodes as $v)
            {
                $amount = bcmul($v['release_num'],0.7,2);
                $toal_record_value .= $v['user_id'] . ',' . $v['user_id'] . ',' . $asset_type . ',' . 14 . ',' . 1 . ',' . $amount . ',' . "'" . '节点释放' . "'" . ',' . "'" . NOW_DATETIME . "'" . ';';

                $income_value .= $v['member_node_id'] . ',' . $v['release_num'] . ',' . $v['type'] . ',' . $v['user_id'] . ',' . "'" . NOW_DATETIME . "'" . ';';
            }
        }

        $toal_record_value = rtrim($toal_record_value,';');
        $toal_record_value = explode(';',$toal_record_value);

        $income_value = rtrim($income_value, ';');
        $income_value = explode(';',$income_value);

        foreach ($toal_record_value as $v)
        {
            $total_record_sql .= '(' .$v . ')' . ',';
        }

        foreach ($income_value as $v)
        {
            $income_sql .= '(' .$v . ')' . ',';
        }

        $total_record_sql = rtrim($total_record_sql,',');
        $income_sql = rtrim($income_sql,',');

        $record_sql = "insert into tp_trading_record (user_id,to_user_id,asset_type,type,change_type,amount,remark,update_time) values" . $total_record_sql;
        $income_sql = "insert into tp_member_node_income (member_node_id,amount,type,user_id,create_time) values" . $income_sql;

        $recordM->execute($record_sql);

        if($asset_type == 1)
        {
            $incomeM->execute($income_sql);
        }
    }

    /**
     * 更新CBC总额
     */
    private function updateTotalBalance($nodes)
    {
        $balance_ids = '';
        $amount_sql = '';
        $before_amount_sql = '';


        $result = [];
        foreach ($nodes as $key => $value) {
            $has = false;
            foreach ($result as $k => $v) {
                if($v['balance_id']==$value['balance_id']){
                    $result[$k]['amount'] = $result[$k]['amount'] + $value['release_num'];
                    $has = true;
                    break;
                }
            }
            if(!$has){
                $tmp = $value;
                $tmp['amount'] = $tmp['release_num'] + $tmp['amount'];
                $result[] = $tmp;
            }
        }


        foreach ($result as $v)
        {
            $balance_ids .= $v['balance_id'] . ',';
            $amount_sql .= ' when ' . $v['balance_id'] . ' then ' . $v['amount'];
            $before_amount_sql .= ' when ' . $v['balance_id'] . ' then ' . $v['amount'];
        }

        $balance_ids = rtrim($balance_ids,',');
        $sql = 'update tp_member_balance set amount = CASE id ' . $amount_sql . ' end where id in(' . $balance_ids . ')';

        $balanceM = new Balance();
        $balanceM->execute($sql);
    }

    /**
     * 更新激活码
     */
    private function updateKeyBalance($nodes)
    {
        $awardS = new AwardService();

        $balance_ids = '';
        $amount_sql = '';
        $before_amount_sql = '';

        foreach ($nodes as $v)
        {
            $balance_ids .= $v['balance_id'] . ',';
            $release_num = bcmul($v['release_num'],0.7,2);
            $amount = $release_num + $v['amount'];
            $amount_sql .= ' when ' . $v['balance_id'] . ' then ' . $amount;
            $before_amount_sql .= ' when ' . $v['balance_id'] . ' then ' . $v['amount'];

//            $trading_amount = bcmul($v['release_num'],0.3,2);
//            $awardS->tradingReward($trading_amount,$v['user_id']);
        }

        $balance_ids = rtrim($balance_ids,',');
        $sql = 'update tp_member_balance set amount = CASE id ' . $amount_sql . ' end where id in(' . $balance_ids . ')';

        $balanceM = new Balance();
        $balanceM->execute($sql);
    }

    /**
     * 更新可用余额
     */
    private function updateUseBalance($nodes)
    {
        $balance_ids = '';
        $amount_sql = '';
        $before_amount_sql = '';

        $result = [];
        foreach ($nodes as $key => $value) {
            $has = false;
            foreach ($result as $k => $v) {
                if($v['balance_id']==$value['balance_id']){
                    $result[$k]['amount'] = $result[$k]['amount'] + bcmul($value['release_num'],0.7,2);
                    $has = true;
                    break;
                }
            }
            if(!$has){
                $tmp = $value;
                $tmp['amount'] = bcmul($tmp['release_num'],0.7,2) + $tmp['amount'];
                $result[] = $tmp;
            }
        }

        foreach ($result as $v)
        {
            $balance_ids .= $v['balance_id'] . ',';
            $amount_sql .= ' when ' . $v['balance_id'] . ' then ' . $v['amount'];
            $before_amount_sql .= ' when ' . $v['balance_id'] . ' then ' . $v['amount'];
        }

        $balance_ids = rtrim($balance_ids,',');
        $sql = 'update tp_member_balance set amount = CASE id ' . $amount_sql . ' end where id in(' . $balance_ids . ')';

        $balanceM = new Balance();
        $balanceM->execute($sql);
    }

    /**
     * 用户节点释放
     */
    private function release($member_node)
    {
        $nodeServiceM = new NodeService();
//        $release = $nodeServiceM->getReleaseNum($member_node);

        $recordM = new TradingRecord();
        $balanceM = new Balance();
        $incomeM = new MemberNodeIncome();

        $user_id = $member_node['user_id'];
        $amount = $member_node['release_num'];
        $member_node_id = $member_node['id'];
        $node_type = $member_node['type'];

        $recordM->startTrans();
        try
        {
            $remark = '节点释放';
            if($node_type < 8)
            {
                Log::record("开始释放普通节点，user_id:" . $user_id);
                //总额
                $total_balance = $balanceM->getBalanceAmountByType($user_id,1);
                $total_balance_after = bcadd($total_balance,$amount,2);
                $recordM->addRecord(0,$amount,$total_balance,$total_balance_after,1,14,1,$user_id,$remark);
                $balanceM->updateBalance($user_id,1,$amount,true);

                //可用余额
                $use_balance = $balanceM->getBalanceAmountByType($user_id,2);
                $use_amount = bcmul($amount,0.7,2);
                $use_balance_after = $use_balance + $use_amount;
                $recordM->addRecord(0,$use_amount,$use_balance,$use_balance_after,2,14,1,$user_id,$remark);
                $balanceM->updateBalance($user_id,2,$use_amount,true);

                Log::record("普通节点释放成功，user_id:" . $user_id);
            }else
            {
                Log::record("开始释放超级节点，user_id:" . $user_id);
                //超级节点释放
                $key_balance = $balanceM->getBalanceAmountByType($user_id,4);
                $key_amount = bcmul($amount,0.7,2);
                $key_balance_after = $key_balance + $key_amount;
                $recordM->addRecord(0,$key_amount,$key_balance,$key_balance_after,4,14,1,$user_id,$remark);
                $balanceM->updateBalance($user_id,4,$key_amount,true);

                $awardS = new AwardService();
                $amount = bcmul($amount,0.3,2);
                $awardS->tradingReward($amount,$user_id);
                Log::record("超级节点释放成功，user_id:" . $user_id);
            }

            $data = array();
            $data = array(
                'member_node_id'    => $member_node_id,
                'create_time'       => NOW_DATETIME,
                'amount'            => $amount,
                'type'              => $node_type,
                'user_id'           => $user_id,
            );

            $incomeM->add($data);

            $recordM->commit();
            return true;
        }catch (\Exception $e)
        {
            Log::record("节点释放失败，user_id:" . $user_id);
            $recordM->rollback();
            return false;
        }
    }

    /**
     * 获取可释放节点的用户
     */
    private function getNodeUsers($type=null,$node_type=null)
    {
        $memberNodeM = new MemberNode();
        $map['n.pass_time'] = array('>=',time());
        if($type)
            $map['b.type'] = $type;
        if($node_type)
            $map['n.type'] = 8;
        else
            $map['n.type'] = array('<>',8);

//        $nodes = $memberNodeM->field('id,node_id,release_num,type,user_id')->where($map)->select();
        $nodes = $memberNodeM->alias('n')
                    ->field('n.release_num,n.user_id,b.id balance_id,b.amount,n.id member_node_id,n.type')
                    ->join('member_balance b','b.user_id = n.user_id','left')
                    ->where($map)
                    ->select();

        return $nodes;
    }

    public function updateBalanceReleaseNum()
    {
        $userM = new MemberAccountModel();
        $users = $userM->column('id');
        foreach ($users as $v)
        {
            $this->updateReleaseNum($v);
        }
    }

    /**
     * 更新用户余额节点日释放值
     */
    private function updateReleaseNum($user_id)
    {
        $memberNodeM = new MemberNode();
        $where['user_id'] = $user_id;
        $where['pass_time'] = array('>=',time());
        $where['status'] = 1;
        $release_num = $memberNodeM->where($where)->sum('release_num');

        $balanceM = new Balance();
        Log::record("准备更新节点日释放");
        $res = $balanceM->save([
            'amount' => empty($release_num) ? 0 : $release_num,
        ],[
            'type'  => 5,
            'user_id'   => $user_id,
        ]);
        if($res)
        {
            Log::record("节点日释放更新成功");
            return true;
        }else
        {
            Log::record("节点日释放更新失败");
            return false;
        }
    }

    /**
     * 赠送微型节点
     */
    public function sendNode($user_id)
    {
        $nodeM = new Node();
        $memberNodeM = new MemberNode();
        //判断微型节点是否存在
        $res = $memberNodeM->where(['type'=>1,'user_id'=>$user_id])->find();
        if($res) return false;
        $balanceM = new \addons\member\model\Balance();
        $node = $nodeM->where('type',1)->find();
        if(empty($node)) return false;
        $data = [];
        $data = array(
            'node_id'   => $node['id'],
            'node_num'  => 1,
            'user_id'   => $user_id,
            'create_time'   => NOW_DATETIME,
            'type'      => $node['type'],
            'status'    => 1,
            'release_num'   => $node['release_num'],
            'total_num' => $node['total_num'],
            'pass_time' => time() + ($node['days'] * 24 * 60 * 60),
        );
        $memberNodeM->startTrans();
        // $balance = $balanceM->updateBalance($user_id, 5, $node['release_num'],1);
        // if(!$balance){
        //     $memberNodeM->rollback();
        //     $this->failJSON(lang('NODE_ADD'));
        // }
        $res = $memberNodeM->add($data);
        if($res){
            $memberNodeM->commit();
            return true;
        }else{
            $memberNodeM->rollback();
            return false;
        }
    }

    public function getDayNodeCount($user_id)
    {
        $incomeM = new MemberNodeIncome();

        $amount = $incomeM->where('user_id',$user_id)->whereTime('create_time','today')->sum('amount');

        return $amount;
    }
}



















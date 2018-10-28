<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/26
 * Time: 14:21
 */

namespace web\api\service;


use addons\member\model\Balance;
use addons\member\model\TradingRecord;
use think\Log;
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
        $nodes = $this->getNodeUsers();
//        print_r($nodes);exit();
        foreach ($nodes as $v)
        {
            $this->release($v);
        }
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
        $member_node_id = $member_node['node_id'];
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
                $recordM->addRecord(0,$amount,$total_balance,$total_balance_after,1,13,1,$user_id,$remark);
                $balanceM->updateBalance($user_id,1,$amount,true);

                //可用余额
                $use_balance = $balanceM->getBalanceAmountByType($user_id,2);
                $use_amount = bcmul($amount,0.7,2);
                $use_balance_after = $use_balance + $use_amount;
                $recordM->addRecord(0,$use_amount,$use_balance,$use_balance_after,2,13,1,$user_id,$remark);
                $balanceM->updateBalance($user_id,2,$use_amount,true);

                Log::record("普通节点释放成功，user_id:" . $user_id);
            }else
            {
                Log::record("开始释放超级节点，user_id:" . $user_id);
                //超级节点释放
                $key_balance = $balanceM->getBalanceAmountByType($user_id,4);
                $key_amount = bcmul($amount,0.7,2);
                $key_balance_after = $key_balance + $key_amount;
                $recordM->addRecord(0,$key_amount,$key_balance,$key_balance_after,4,13,1,$user_id,$remark);
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
    private function getNodeUsers()
    {
        $memberNodeM = new MemberNode();
        $map['pass_time'] = array('>=',time());
        $nodes = $memberNodeM->field('node_id,release_num,type,user_id')->where($map)->select();

        return $nodes;
    }

    /**
     * 获取用户节点日释放量
     */
    public function getReleaseNum($user_id)
    {
        $memberNodeM = new MemberNode();
        $where['user_id'] = $user_id;
        $where['pass_time'] = array('>=',time());
        $where['type'] = array('<>',8);
        $normal_num = $memberNodeM->where($where)->sum('release_num');

        $super_where['user_id'] = $user_id;
        $super_where['pass_time'] = array('>=',time());
        $super_where['type'] = 8;
        $super_num  = $memberNodeM->where($super_where)->sum('release_num');

        $data = array(
            'normal_num'    => $normal_num,
            'super_num'     => $super_num,
        );

        return $data;
    }

    /**
     * 赠送微信节点
     */
    public function sendNode($user_id)
    {
        $nodeM = new Node();
        $memberNodeM = new MemberNode();
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
        $balance = $balanceM->updateBalance($user_id, 5, $node['release_num'],1);
        if(!$balance){
            $memberNodeM->rollback();
            $this->failJSON(lang('NODE_ADD'));
        }
        $res = $memberNodeM->add($data);
        if($res){
            $memberNodeM->commit();
            return true;
        }else{
            $memberNodeM->rollback();
            return false;
        }
    }
}



















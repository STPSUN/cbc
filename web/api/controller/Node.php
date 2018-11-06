<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/17
 * Time: 13:49
 */

namespace web\api\controller;


use addons\member\model\Balance;
use addons\member\model\MemberAccountModel;
use addons\member\model\TradingRecord;
use think\Log;
use think\Request;
use think\Validate;
use web\api\model\MemberNode;
use web\api\model\MemberNodeApply;
use web\api\model\MemberNodeIncome;
use web\api\service\MemberService;
use web\api\service\NodeService;

class Node extends ApiBase
{
    //购买节点
    public function buyNode()
    {
//        echo 2;exit();
        $param = Request::instance()->post();

        $validate = new Validate([
            'give_username'  => 'number',
            'node_id'   => 'require|integer',
        ]);
        if(!$validate->check($param))
            return $this->failJSON($validate->getError());

        $node_id = $param['node_id'];
        $give_username = $param['give_username'];
        $balance_type = 4;

        $nodeM = new \web\api\model\Node();
        $memberNodeM = new MemberNode();
        $node = $nodeM->getDetail($node_id);
        if(empty($node))
            return $this->failJSON(lang('NODE_EMPTY'));

        $amount = $node['cbc_num'];
        $recordM = new \addons\member\model\TradingRecord();
        $memberM= new MemberAccountModel();
        $balanceM = new \addons\member\model\Balance();

        $give_user_id = 0;
        if(!empty($give_username))
        {
            $give_user_id = $memberM->getUserByPhone($give_username);
            if(empty($give_user_id))
                return $this->failJSON(lang('NODE_USER_EXISTS'));
        }
        $data = [];
        $data = array(
                'node_id'   => $node['id'],
                'node_num'   => 1,
                'user_id'   => $this->user_id,
                'create_time'   => NOW_DATETIME,
                'type'  => $node['type'],
                'status'    => 1,
                'release_num'   => $node['release_num'],
                'total_num' => $node['total_num'],
                'pass_time' => time() + ($node['days'] * 24 * 60 * 60),
            );

        $node_level_user_id = $this->user_id;

        if(!empty($give_username))
        {
            $node_level_user_id = $give_user_id;
            $data['user_id'] = $give_user_id;
            $data['give_user_id'] = $this->user_id;
            $filter = 'user_id = ' . $give_user_id . ' and type = ' . $node['type'];
        }else
        {
            $filter = 'user_id = ' . $this->user_id . ' and type = ' . $node['type'];
        }

        $user_node_num = $memberNodeM->getSum($filter,'node_num');
        if($user_node_num >= $node['node_num'])
            return $this->failJSON(lang('NODE_BUY_LIMIT'));

        $balance = $balanceM->verifyStock($this->user_id,$amount,$balance_type);
        if(empty($balance)){
            return $this->failJSON(lang('NODE_LESS_AMOUNT'));
        }
        if($amount > $balance['amount']){
            return $this->failJSON(lang('NODE_LESS_AMOUNT'));
        }

        $memberNodeM->startTrans();
        try
        {
            $balance = $balanceM->updateBalance($this->user_id, $balance_type, $amount);
            if($balance != false){
                $type = 3; //购买节点
                $change_type = 0; //减少
                $remark = '节点认购';
                $recordM->addRecord($this->user_id, $amount, $balance['before_amount'], $balance['amount'], $balance_type, $type, $change_type, $give_user_id, $remark);
            }
            $memberNodeM->save($data);

            //会员升级
//            $memberS = new MemberService();
//            $memberS->memberLevel($this->user_id);

            $node_user = $memberM->getDetail($node_level_user_id);
            if($node_user['node_level'] < $node['type'])
            {
                $memberM->save([
                    'node_level' => $node['type'],
                ],[
                    'id' => $node_user['id'],
                ]);
            }
        }catch (\Exception $e)
        {
            $memberNodeM->rollback();
            return $this->failJSON($e->getMessage());
        }

        $memberNodeM->commit();
        return $this->successJSON();

    }

    /**
     * 超级节点申请
     */
    public function nodeApply()
    {
        $param = Request::instance()->post();
        $validate = new Validate([
            'username'  => 'require',
            'phone'     => 'require',
            'pusername' => 'require',
            'node_id'   => 'require',
        ]);

        if(!$validate->check($param))
            return $this->failJSON($validate->getError());

        $username = $param['username'];

        $nodeM = new \web\api\model\Node();
        $memberM = new MemberAccountModel();
        $memberNodeM = new MemberNode();
        $nodeApplyM = new MemberNodeApply();

        $node = $nodeM->getDetail($param['node_id']);
        if(empty($node))
            return $this->failJSON(lang('NODE_NOT_EXISTS'));

        $apply = $nodeApplyM->where(['username' => $username, 'status' => 1])->find();
        if($apply)
            return $this->failJSON(lang('NODE_EXAMINE'));

        $user = $memberM->getUserByPhone($param['username']);
        if(empty($user))
            return $this->failJSON(lang('NODE_USER_NOT_EXISTS'));

        $pUser = $memberM->getUserByPhone($param['pusername']);
        if(empty($pUser))
            return $this->failJSON(lang('NODE_USER_PID_EXISTS'));

        $filter = 'user_id = ' . $this->user_id . ' and type = ' . $node['type'];
        $user_node_num = $memberNodeM->getSum($filter,'node_num');
        if($user_node_num >= $node['node_num'])
            return $this->failJSON(lang('NODE_BUY_LIMIT'));

        $data = array(
            'username'  => $param['username'],
            'phone'     => $param['phone'],
            'pusername' => $param['pusername'],
            'update_time'   => NOW_DATETIME,
            'status'    => 1,
        );


        $nodeApplyM->save($data);

        return $this->successJSON();
    }

    /**
     * 获取节点
     */
    public function getNode()
    {
        $memberNodeM = new MemberNode();

        $data = $memberNodeM->field('type,status,id node_id')->where('user_id',$this->user_id)->select();

        $incomeM = new MemberNodeIncome();
        foreach ($data as &$v)
        {
            if($v['status'] == 1)
                $v['status'] = lang('NODE_START');
            else
                $v['status'] = lang('NODE_DOWN');

            $amount = $incomeM->where(['member_node_id' => $v['node_id'], 'user_id' => $this->user_id])->sum('amount');
            $v['release_num'] = empty($amount) ? 0 : $amount;
        }

        return $this->successJSON($data);
    }

    /**
     * 获取节点明细
     */
    public function getNodeDetail()
    {
        $param = Request::instance()->post();
        $validate = new Validate([
            'node_id'   => 'require',
        ]);

        $conf = array(
            'page'  => empty($param['page']) ? 1 : $param['page'],
            'list_rows' => empty($param['list_rows']) ? 5 : $param['list_rows']
        );

        if(!$validate->check($param))
            return $this->failJSON($validate->getError());

//        print_r($param['node_id']);exit();
        $incomeM = new MemberNodeIncome();
        $filter = "user_id = '" . $this->user_id . "' and member_node_id = " . $param['node_id'];
        $fields = "id,create_time,amount,type";
        $data = $incomeM->getDataList2($conf['page'],$conf['list_rows'],$filter,$fields,'create_time desc');

        return $this->successJSON($data);
    }

    /**
     * 节点列表
     */
    public function nodeList()
    {
        $nodeM = new \web\api\model\Node();
        $fields = "id,type,node_num,cbc_num";
        $data = $nodeM->getDataList('','','',$fields,'type asc');

        return  $this->successJSON($data);
    }

    /**
     * 节点释放
     */
    public function release()
    {
        $nodeS = new NodeService();
        $nodeS->updateBalanceReleaseNum();

        echo 1;
    }

}

















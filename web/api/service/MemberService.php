<?php
namespace web\api\service;
use addons\member\model\MemberAccountModel;
use addons\member\user\controller\Member;
use think\Log;

/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/23
 * Time: 15:18
 */
class MemberService extends \web\common\controller\Service
{
    private $is_next;

    /**
     * 会员升级
     */
    public function memberLevel($user_id)
    {
        $awardS = new AwardService();
        $userM = new MemberAccountModel();
        $user = $userM->getDetail($user_id);
        $pusers = $awardS->getParentUser($user['pid']);

        $userM->startTrans();
        try{
            Log::record("会员升级开始");
            $this->memberLevelUpdate($user_id);
            foreach ($pusers as $v)
            {
                $this->memberLevelUpdate($v['user_id']);
            }

            Log::record("会员升级成功");
            $userM->commit();

            return true;
        }catch (\Exception $e)
        {
            Log::record("会员升级失败");
            $userM->rollback();

            return false;
        }
    }

    /**
     * 单个会员升级
     */
    private function memberLevelUpdate($user_id)
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->getDetail($user_id);
        if(empty($user))
            return;

        $this->is_next = true;  //true 可持续往下升级, false 中级终止
        $user_level = $user['user_level'];

        for($i = 0; $i <= 5; $i++)
        {
            if($this->is_next == false)
                break;

            switch ($user_level)
            {
                case 0:
                    $this->is_next = $this->levelOne($user['id']);   break;
                case 1:
                    $this->is_next = $this->level2($user['id']);     break;
                case 2:
                    $this->is_next = $this->levelEgt($user['id'],2,300000,90,3);  break;
                case 3:
                    $this->is_next = $this->levelEgt($user['id'],3,2000000,270,4);  break;
                case 4:
                    $this->is_next = $this->levelEgt($user['id'],4,6000000,810,5);  break;
                case 5:
                    $this->is_next = $this->levelEgt($user['id'],5,18000000,2430,6);  break;
            }

            $user_level++;  //下一个等级
        }
    }

    private function levelEgt2($user_id,$level_condition,$amount,$user_num,$level)
    {
        //直推符合等级的部门数量
        $num = $this->getTeamLevelAmount($user_id,$level_condition);
        if($num < 3)
            return;

        $team = $this->getTotalAmount($user_id);
        $amount = $team['amount'];
        $user_num = $team['user_num'];
        if($amount )
        if($team['amount'] < $amount || $team['user_num'] < $user_num)
            return;

        $this->levelUpdate($user_id,$level);
    }

    /**
     * 盟主及以上升级
     * @param $user_id
     * @param $level_condition  直推部门会员等级
     * @param $amount           节点数量
     * @param $user_num         注册人数
     * @param $level            下一个等级
     */
    private function levelEgt($user_id,$level_condition,$amount,$user_num,$level)
    {
        //直推符合等级的部门数量
        $num = $this->getTeamLevelAmount($user_id,$level_condition);
        if($num < 3)
            return false;
        $team = $this->getTotalAmount($user_id);
        if($team['amount'] < $amount || $team['user_num'] < $user_num)
            return false;

        $this->levelUpdate($user_id,$level);

        return true;
    }

    private function level2($user_id)
    {
        $direct_num = $this->directBuyNode($user_id);
        if($direct_num < 3)
            return false;

        $team = $this->getTotalAmount($user_id);
        if($team['amount'] < 60000 || $team['user_num'] < 30)
            return false;

        $this->levelUpdate($user_id,2);

        return true;
    }

    private function levelOne($user_id)
    {
        $direct_num = $this->directNum($user_id);
        if($direct_num < 1)
            return false;

        $real_user_num = $this->directRealUserNum($user_id);
        if($real_user_num < 1)
            return false;

        $team = $this->getTotalAmount($user_id);
        if($team['amount'] < 100)
            return false;

        $this->levelUpdate($user_id,1);

        return true;
    }

    /**
     * 直推人数
     */
    private function directNum($user_id)
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $num = $userM->where('pid',$user_id)->count();

        return $num;
    }

    /**
     * 直推实名人数
     */
    private function directRealUserNum($user_id)
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $num = $userM->where(['pid' => $user_id, 'is_auth' => 1])->count();

        return $num;
    }

    /**
     * 直推且购买节点的人数
     */
    private function directBuyNode($user_id)
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $data = $userM->alias('u')
                ->field('r.user_id')
                ->join('trading_record r','u.id = r.user_id')
                ->where(['u.pid' => $user_id, 'r.type' => 3])
                ->select();

        $users = array();
        $num = 0;
        foreach ($data as $v)
        {
            if(!in_array($v['user_id'],$users))
            {
                array_push($users,$v['user_id']);
                $num++;
            }
        }
        return $num;
    }

    /**
     * 获取总业绩(包含自身)
     */
    private function getTotalAmount($user_id)
    {
        $recordM = new \addons\member\model\TradingRecord();
        //用户本人业绩
        $user_amount = $recordM->where(['user_id' => $user_id, 'type' => 3])->sum('amount');
        //伞下业绩和注册人数
        $team = $this->getTeamAmount($user_id,$user_num);
        $amount = $team['amount'];

        $data = array(
            'amount' => $amount,
            'user_num' => $team['user_num'],
        );
        return $data;
    }

    /**
     * 获取盟友及以上,直推部门符合升级的数量
     */
    private function getTeamLevelAmount($user_id,$level)
    {
        $userM = new \addons\member\model\MemberAccountModel();
        //直推会员
        $users = $userM->field('id')->where('pid',$user_id)->select();
        $num = 0;   //直推部门符合升级条件的会员数量
        foreach ($users as $v)
        {
            $user = $userM->field('user_level')->where('id',$v['id'])->find();
            if($user['user_level'] >= $level)
                $num++;

            $level_num = $this->getDirectLevelNum($v['id'],$level);
            if($level_num == 1)
                $num++;
            if($num >= 3)
                break;
        }

        return $num;
    }

    /**
     * 递归伞下符合等级的会员
     */
    private function getDirectLevelNum($id,$level=2,&$level_num=0)
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $data = $userM->field('id')->where('pid',$id)->select();
        foreach ($data as $v)
        {
            $where['id'] = $v['id'];
            $where['user_level'] = array('>=',$level);
            $user = $userM->where($where)->find();
            if($user)
            {
                $level_num = 1;
                break;
            }

            $users = $userM->where('pid',$v['id'])->select();
            if(!empty($users))
            {
                $this->getDirectLevelNum($v['id'],$level,$level_num);
            }
        }

        return $level_num;
    }

    /**
     * 获取伞下业绩
     */
    private function getTeamAmount($id,&$amount=0,&$user_num=0)
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $recordM = new \addons\member\model\TradingRecord();
        $data = $userM->where('pid',$id)->select();
        foreach ($data as $v)
        {
            $user_num++;
            $amount += $recordM->where(['user_id' => $v['id'], 'type' => 3])->sum('amount');

            $users = $userM->where('pid',$v['id'])->select();
            if(!empty($users))
            {
                $this->getTeamAmount($v['id'],$amount,$user_num);
            }
        }

        $data = array(
            'amount' => $amount,
            'user_num'  => $user_num,
        );
        return $data;
    }

    /**
     * 更新会员等级
     * @param $user_id
     * @param $level
     */
    private function levelUpdate($user_id,$level)
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $userM->save([
            'user_level' => $level,
        ],[
            'id' => $user_id,
        ]);
    }

    /**
     * 获取伞下会员
     */
    public function getTeam($id,&$team=array())
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $data = $userM->field('id,phone,real_name')->where('pid',$id)->select();
        foreach ($data as $v)
        {
            $temp = array(
                'user_id'   => $v['id'],
                'phone'     => $v['phone'],
                'real_name' => $v['real_name'],
            );

            $team[] = $temp;

            $users = $userM->where('pid',$v['id'])->select();
            if(!empty($users))
            {
                $this->getTeam($v['id'],$team);
            }
        }

        return $team;
    }


    /**
     * 获取伞下会员
     */
    public function getTreeTeam($id,$team=array(),$num)
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $users = $userM->where('pid',$id)->find();
        if(!$users)
        {
            return $team;
        }
        if($num==3){
            return $team;
        }
        $num++;
        $data = $userM->field('id,phone,real_name')->where('pid',$id)->select();
        foreach ($data as $v)
        {
            $temp = array(
                'user_id'   => $v['id'],
                'phone'     => $v['phone'],
                'real_name' => $v['real_name'],
            );
            $team[] = $temp;
            $users = $userM->where('pid',$v['id'])->find();
            if($users)
            {
                $team = $this->array_unique_duble(array_merge($team,$this->getTreeTeam($v['id'],$team,$num)),'user_id');
            }
        }

        return $team;
    }


    /**
     * 获取伞下会员
     */
    public function getTreeId($id,$team=array())
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $users = $userM->field('id,phone,real_name')->where('pid',$id)->find();
        if(!$users)
        {
            return $team;
        }
        
        $data = $userM->field('id,phone,real_name')->where('pid',$id)->select();
        foreach ($data as $v)
        {
            $temp = $v['id'];
            $team[] = $temp;
            $users = $userM->where('pid',$v['id'])->find();
            if($users)
            {
                $team = array_unique(array_merge($team,$this->getTreeId($v['id'],$team)));
            }
        }

        return $team;
    }

    /**
     * 二维数组去重
     */
    public function array_unique_duble($arr,$key){
        $result = [];
        foreach ($arr as $ke => $value) {
            $has = false;
            foreach ($result as $k => $v) {
                if($v[$key]==$value[$key]){
                    $has = true;
                    break;
                }
            }
            if(!$has){
                $result[] = $value;
            }
        }
        return $result;
    }
    
    /**
     * 获取伞下实名会员
     */
    public function getTeamId($id,&$team=array())
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $data = $userM->field('id,phone,real_name')->where(['pid' => $id, 'is_auth' => 1])->select();
        foreach ($data as $v)
        {
            $temp = array(
                'user_id'   => $v['id'],
                'phone'     => $v['phone'],
                'real_name' => $v['real_name'],
            );

            $team[] = $temp;

            $users = $userM->field('id,phone,real_name')->where('pid',$v['id'])->select();
            if(!empty($users))
            {
                $this->getTeamId($v['id'],$team);
            }
        }

        return $team;
    }

    /**
     * 根据层级获取伞下会员
     */
    public function getTeamByTier($id,&$team=array(),&$tier=3,&$num=1)
    {
        if($num > $tier)
            return $team;
        $num++;
        $userM = new \addons\member\model\MemberAccountModel();
        $data = $userM->field('id,phone,real_name')->where('pid',$id)->select();
        foreach ($data as $v)
        {
            $temp = array(
                'user_id'   => $v['id'],
                'phone'     => $v['phone'],
                'real_name' => $v['real_name'],
            );

            $team[] = $temp;

            $users = $userM->field('id,phone,real_name')->where('pid',$v['id'])->select();
            if(!empty($users))
            {
                $this->getTeam($v['id'],$team,$tier,$num);
            }
        }

        return $team;
    }
}

















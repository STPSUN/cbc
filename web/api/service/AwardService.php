<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/24
 * Time: 11:34
 */

namespace web\api\service;


use addons\member\model\Balance;
use addons\member\model\MemberAccountModel;
use addons\member\model\TradingRecord;
use think\Log;

class AwardService extends \web\common\controller\Service
{
    protected $peer_amount;
    protected $peer_user_id;
    protected $peer_num;    //平级奖酋长
    protected $trad_data;   //交易奖数据
    protected $share_data;  //分享奖数据
    protected $peer_data;   //评级奖数据

    /**
     * 交易奖
     */
    public function tradingReward($amount,$user_id)
    {
        $this->peer_amount = 0;
        $this->trad_data = array();
        $this->share_data = array();
        $this->peer_data  = array();

        $userM = new MemberAccountModel();
        $user = $userM->getDetail($user_id);
        $pusers = $this->getParentUser($user['pid']);
        $this->peer_user_id = $pusers[0]['user_id'];
        $userM->startTrans();
//        try
//        {
            Log::record("奖励开始发放");
            //交易奖励
            $this->userReward($amount,$pusers,$user_id);
            //分享奖励
            $this->shareReward($amount,$pusers,$user_id);
            // print_r($pusers);
//             print_r($this->trad_data);
            $arr = $this->getPeerAmount();
//             print_r($arr);exit();
            //获取平级奖的用户数据
//            $this->getPeerAmount($pusers);
            //平级奖励
            $this->peerReward($arr,$pusers);
            Log::record("奖励发放成功");

            $userM->commit();
            return true;
//        }catch (\Exception $e)
//        {
//            Log::record("奖励发放失败");
//            Log::record($e->getMessage());
//            $userM->rollback();
//            return false;
//        }

    }

    /**
     * 获取用户交易奖和分享奖的和
     */
    private function getPeerAmount()
    {
        ksort($this->trad_data);
        $arr = [];
        $num = 0;
        foreach ($this->trad_data as $k => $v)
        {
            if($num == 0)
            {
                $arr[] = $v;
            }else
            {
                $temp = $v;
                $temp['amount'] = 0;
                $arr[] = $temp;
            }
        }
        
        $num=1;
        $do = 0;
        while ($num) {
            if(!isset($arr[$do])){
                $num = 0;
                break;
            } 
            $son = $arr[$do];
            $do++;
            if(isset($arr[$do])){
                $father = $arr[$do];
                if($son['level']>=$father['level']){
                    $arr[$do]['amount'] = bcmul(($father['amount']+$son['amount']/10), 1,2);
                }
            }
                
        }

        return $arr;
    }

    private function peerReward($arr,$pusers)
    {
        foreach ($arr as $k => $v)
        {
            if($k==0){
//                $found_key = array_search($v['user_id'], array_column($pusers, 'user_id'));
//                $son = $pusers[$found_key-1];
            }else{
                $son = $arr[$k-1];
                $this->sendTradeReward($v['amount'],$son['user_id'],$v['user_id'],11,'平级奖');
            }

        }
    }

    /**
     * 获取上级会员
     */
    public function peerReward2($pid,&$pUsers=array())
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $puser = $userM->getDetail($pid);

        $temp = array(
            'user_id'   => $puser['id'],
            'user_level' => $puser['user_level']
        );
        $pUsers[] = $temp;

        $user = $userM->getDetail($puser['pid']);
        if(!empty($user))
        {
            $this->getParentUser($user['id'],$pUsers);
        }

        return $pUsers;
    }

    /**
     * 平级奖
     */
    private function peerReward3($pusers,$amount)
    {
        $userM = new MemberAccountModel();

        $amount = bcmul($amount,0.1,2);
        for($i = 0; $i < count($pusers); $i++)
        {
            $level = $userM->where('id',$pusers[$i]['user_id'])->value('user_level');
            if($level >= 4)
                $this->peer_num++;
            else
                continue;

            if($this->peer_num < 2)
                continue;

            if($pusers[$i]['user_level'] == $level)
            {
                $this->sendTradeReward($amount,$this->peer_user_id,$pusers[$i]['user_id'],11,'平级奖励');
                break;
            }
        }
    }

    /**
     * 分享奖励
     */
    private function shareReward($amount,$pusers,$user_id)
    {
        $num = 0;
        foreach ($pusers as $k => $v)
        {

            if($num > 2)
            {
                break;
            }

            $level = $v['user_level'];
            switch ($num)
            {
                case 0:
                {
                    $this->sendShareReward($amount,0.15,$user_id,$v['user_id'],10,$level,$k);

                    break;
                }
                case 1:
                {
                    //盟友及以上，拿2代分享奖
                    if($v['user_level'] < 2)
                        break;

                    $this->sendShareReward($amount,0.12,$user_id,$v['user_id'],10,$level,$k);
                    break;
                }
                case 2:
                {
                    //酋长及以上，拿3代分享奖
                    if($v['user_level'] < 4)
                        break;

                    $this->sendShareReward($amount,0.09,$user_id,$v['user_id'],10,$level,$k);
                    break;
                }
            }

            $num++;
        }
    }

    /**
     * 发放分享奖励
     * @param $amount
     * @param $rate
     * @param $user_id
     * @param $to_user_id
     * @param $type
     */
    private function sendShareReward($amount,$rate,$user_id,$to_user_id,$type,$level,$k)
    {
        $remark = '分享奖励';
        $user_amount = bcmul($amount,$rate,2);
        $this->sendTradeReward($user_amount,$user_id,$to_user_id,$type,$remark);

//        echo $to_user_id . '/';

//        $arr = $this->trad_data;
        if($level >= 4)
        {
            if(isset($this->trad_data[$k]))
            {
                if($this->trad_data[$k]['user_id'] == $to_user_id)
                {
                    $this->trad_data[$k]['amount'] += $user_amount;
                }
            }else
            {
                $share_temp = array(
                    'user_id'   => $to_user_id,
                    'level'     => $level,
                    'amount'    => $user_amount
                );

                $this->trad_data[$k] = $share_temp;
            }

        }

    }

    /**
     * 用户交易奖
     */
    private function userReward($amount,$puers,$user_id)
    {
        $use_rate = 0;  //当前会员可得奖励比率
        $level = 0;     //当前会员等级
        $type = 9;
        $remark = '交易奖励';
        foreach ($puers as $k => $v)
        {
            switch ($v['user_level'])
            {
                case 2:
                {
                    if($level >= 2)
                    {
//                        $level = 2;
                        break;
                    }
                    $level = 2;
                    $use_rate = 0.12;
                    $rate = 0.12;
                    $user_amount = bcmul($amount,$rate,2);
                    $this->sendTradeReward($user_amount,$user_id,$v['user_id'],$type,$remark);
                    break;
                }
                case 3:
                {
                    if($level >= 3)
                    {
//                        $level = 3;
                        break;
                    }

                    $level = 3;
                    $rate = 0.21 - $use_rate;
                    $use_rate = 0.21;
                    $user_amount = $amount * $rate;
                    $this->sendTradeReward($user_amount,$user_id,$v['user_id'],$type,$remark);
                    break;
                }
                case 4:
                {
                    if($level >= 4)
                    {
                        $trad_temp = array(
                            'user_id'   => $v['user_id'],
                            'level'     => 4,
                            'amount'    => 0
                        );
                        $this->trad_data[$k] = $trad_temp;
//                        $level = 4;
                        break;
                    }
                    $level = 4;
                    $rate = 0.3 - $use_rate;
                    $use_rate = 0.3;
                    $user_amount = $amount * $rate;
                    $this->sendTradeReward($user_amount,$user_id,$v['user_id'],$type,$remark);

                    $trad_temp = array(
                        'user_id'   => $v['user_id'],
                        'level'     => 4,
                        'amount'    => $user_amount
                    );
                    $this->trad_data[$k] = $trad_temp;
                    break;
                }
                case 5:
                {
                    if($level >= 5)
                    {
                        $trad_temp = array(
                            'user_id'   => $v['user_id'],
                            'level'     => 5,
                            'amount'    => 0
                        );
                        $this->trad_data[$k] = $trad_temp;
//                        $level = 5;
                        break;
                    }
                    $level = 5;
                    $rate = 0.4 - $use_rate;
                    $use_rate = 0.4;
                    $user_amount = $amount * $rate;
                    $this->sendTradeReward($user_amount,$user_id,$v['user_id'],$type,$remark);

                    $trad_temp = array(
                        'user_id'   => $v['user_id'],
                        'level'     => 5,
                        'amount'    => $user_amount
                    );
                    $this->trad_data[$k] = $trad_temp;
                    break;
                }
                case 6:
                {
                    if($level >= 6)
                    {
                        $trad_temp = array(
                            'user_id'   => $v['user_id'],
                            'level'     => 6,
                            'amount'    => 0
                        );
                        $this->trad_data[$k] = $trad_temp;
//                        $level = 6;
                        break;
                    }
                    $level = 6;
                    $rate = 0.45 - $use_rate;
                    $user_amount = $amount * $rate;
                    $this->sendTradeReward($user_amount,$user_id,$v['user_id'],$type,$remark);

                    $trad_temp = array(
                        'user_id'   => $v['user_id'],
                        'level'     => 6,
                        'amount'    => $user_amount
                    );
                    $this->trad_data[$k] = $trad_temp;
                    break;
                }
            }

//            if($level == 6)
//                break;
        }

    }

    /**
     * 发放奖励
     * @param $amount
     * @param $user_id
     */
    private function sendTradeReward($amount,$user_id,$to_user_id,$type,$remark)
    {
        $recordM = new TradingRecord();
        $balanceM = new Balance();

        //总额
        $total_balance = $balanceM->getBalanceAmountByType($to_user_id,1);
        $total_balance_after = bcadd($total_balance,$amount,2);
        $recordM->addRecord($to_user_id,$amount,$total_balance,$total_balance_after,1,$type,1,$user_id,$remark);
        $balanceM->updateBalance($to_user_id,1,$amount,true);

        //可用余额
        $use_balance = $balanceM->getBalanceAmountByType($to_user_id,2);
        $use_amount = bcmul($amount,0.7,2);
        $use_balance_after = $use_balance + $use_amount;
        $recordM->addRecord($to_user_id,$use_amount,$use_balance,$use_balance_after,2,$type,1,$user_id,$remark);
        $balanceM->updateBalance($to_user_id,2,$use_amount,true);

        //平级奖金额
//        if($to_user_id == $this->peer_user_id)
//        {
//            $this->peer_amount += $amount;
//        }

    }

    /**
     * 获取上级会员
     */
    public function getParentUser($pid,&$pUsers=array())
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $puser = $userM->getDetail($pid);

        $temp = array(
            'user_id'   => $puser['id'],
            'user_level' => $puser['user_level']
        );
        $pUsers[] = $temp;

        $user = $userM->getDetail($puser['pid']);
        if(!empty($user))
        {
            $this->getParentUser($user['id'],$pUsers);
        }

        return $pUsers;
    }


}





















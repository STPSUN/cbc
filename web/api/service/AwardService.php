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

    /**
     * 交易奖
     */
    public function tradingReward($amount,$user_id)
    {
        $this->peer_amount = 0;

        $userM = new MemberAccountModel();
        $user = $userM->getDetail($user_id);
        $pusers = $this->getParentUser($user['pid']);
        $this->peer_user_id = $pusers[0]['user_id'];
        $userM->startTrans();
        try
        {
            Log::record("奖励开始发放");
            //交易奖励
            $this->userReward($amount,$pusers,$user_id);
            //分享奖励
            $this->shareReward($amount,$pusers,$user_id);
            //平级奖励
            $this->peerReward($pusers,$this->peer_amount);
            Log::record("奖励发放成功");

            $userM->commit();
            return true;
        }catch (\Exception $e)
        {
            Log::record("奖励发放失败");
            Log::record($e->getMessage());
            $userM->rollback();
            return false;
        }

    }

    /**
     * 平级奖
     */
    private function peerReward($pusers,$amount)
    {
        $userM = new MemberAccountModel();
        $level = $userM->where('id',$this->peer_user_id)->value('user_level');
        if($level < 4)
            return;

        $amount = bcmul($amount,0.1,2);
        for($i = 0; $i < count($pusers); $i++)
        {
            if($i == 0)
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
        for($i = 0; $i < count($pusers); $i++)
        {
            switch ($i)
            {
                case 0:
                {
                    $this->sendShareReward($amount,0.15,$user_id,$pusers[$i]['user_id'],10);
                    break;
                }
                case 1:
                {
                    if($pusers[$i]['user_level'] < 2)
                        break;

                    $this->sendShareReward($amount,0.12,$user_id,$pusers[$i]['user_id'],10);
                    break;
                }
                case 2:
                {
                    if($pusers[$i]['user_level'] < 4)
                        break;
                    $this->sendShareReward($amount,0.09,$user_id,$pusers[$i]['user_id'],10);
                    break;
                }
            }
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
    private function sendShareReward($amount,$rate,$user_id,$to_user_id,$type)
    {
        $remark = '分享奖励';
        $user_amount = bcmul($amount,$rate,2);
        $this->sendTradeReward($user_amount,$user_id,$to_user_id,$type,$remark);
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
        foreach ($puers as $v)
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
//                        $level = 4;
                        break;
                    }
                    $level = 4;
                    $rate = 0.3 - $use_rate;
                    $use_rate = 0.3;
                    $user_amount = $amount * $rate;
                    $this->sendTradeReward($user_amount,$user_id,$v['user_id'],$type,$remark);
                    break;
                }
                case 5:
                {
                    if($level >= 5)
                    {
//                        $level = 5;
                        break;
                    }
                    $level = 5;
                    $rate = 0.4 - $use_rate;
                    $use_rate = 0.4;
                    $user_amount = $amount * $rate;
                    $this->sendTradeReward($user_amount,$user_id,$v['user_id'],$type,$remark);
                    break;
                }
                case 6:
                {
                    if($level >= 6)
                    {
//                        $level = 6;
                        break;
                    }
                    $level = 6;
                    $rate = 0.45 - $use_rate;
                    $user_amount = $amount * $rate;
                    $this->sendTradeReward($user_amount,$user_id,$v['user_id'],$type,$remark);
                    break;
                }
            }

            if($level == 6)
                break;
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
        if($to_user_id == $this->peer_user_id)
        {
            $this->peer_amount += $amount;
        }

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





















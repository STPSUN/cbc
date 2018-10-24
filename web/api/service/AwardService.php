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
    /**
     * 交易奖
     */
    public function tradingReward($amount,$user_id)
    {
        $userM = new MemberAccountModel();
        $user = $userM->getDetail($user_id);
        $pusers = $this->getParentUser($user['pid']);

        $this->userReward($amount,$pusers,$user_id);
    }

    /**
     * 分享奖励
     */
    private function shareReward($amount,$pusers,$user_id)
    {
        for($i = 1; $i <= count($pusers); $i++)
        {
            switch ($i)
            {
                case 1:
                {
                    break;
                }
                case 2:
                {
                    break;
                }
                case 3:
                {
                    break;
                }
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
                    $this->sendTradeReward($user_amount,$user_id,$v['user_id']);
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
                    $this->sendTradeReward($user_amount,$user_id,$v['user_id']);
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
                    $this->sendTradeReward($user_amount,$user_id,$v['user_id']);
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
                    $this->sendTradeReward($user_amount,$user_id,$v['user_id']);
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
                    $this->sendTradeReward($user_amount,$user_id,$v['user_id']);
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
    private function sendTradeReward($amount,$user_id,$to_user_id)
    {
        $recordM = new TradingRecord();
        $balanceM = new Balance();

        //总额
        $total_balance = $balanceM->getBalanceAmountByType($to_user_id,1);
        $total_balance_after = bcadd($total_balance,$amount,2);
        $recordM->addRecord($user_id,$amount,$total_balance,$total_balance_after,1,9,1,$to_user_id,'交易奖励');

        //可用余额
        $use_balance = $balanceM->getBalanceAmountByType($to_user_id,2);
        $use_amount = bcmul($amount,0.7,2);
        $use_balance_after = $use_balance + $use_amount;
        $recordM->addRecord($user_id,$use_amount,$use_balance,$use_balance_after,2,9,1,$to_user_id,'交易奖励');
    }

    /**
     * 获取上级会员
     */
    private function getParentUser($pid,&$pUsers=array())
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





















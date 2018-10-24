<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/24
 * Time: 11:34
 */

namespace web\api\service;


use addons\member\model\MemberAccountModel;

class AwardService extends \web\common\controller\Service
{
    /**
     * 发放交易奖
     */
    public function sendTradingReward($amount,$user_id)
    {
        $userM = new MemberAccountModel();
        $user = $userM->getDetail($user_id);
        $pusers = $this->getParentUser($user['pid']);

        print_r($pusers);exit();
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
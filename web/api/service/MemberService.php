<?php

/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/23
 * Time: 15:18
 */
class MemberService extends \web\common\controller\Service
{
    /**
     * 会员升级
     */
    public function levelUpdate($user_id)
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->getDetail($user_id);
        if(empty($user))
            return;

        switch ($user['user_level'])
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
            case 4:
            {
                break;
            }
            case 5:
            {
                break;
            }
            case 6:
            {
                break;
            }
        }
    }

}

















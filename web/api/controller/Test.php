<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/28
 * Time: 15:08
 */

namespace web\api\controller;


use web\api\service\AwardService;
use web\api\service\MemberService;
use web\api\service\NodeService;

class Test extends ApiBase
{
    public function award()
    {
        $awardS = new AwardService();
        $res = $awardS->tradingReward(300,93);
        if($res)
            echo 1;
        else
            echo 2;
    }

    public function release()
    {
        $nodeS = new NodeService();
        $nodeS->nodeRelease();
        $nodeS->updateBalanceReleaseNum();
    }

    public function level()
    {
        $memberS = new MemberService();
        $res = $memberS->memberLevel(87);
        if($res)
            echo 1;
        else
            echo 2;
    }

}











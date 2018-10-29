<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/28
 * Time: 15:08
 */

namespace web\api\controller;


use web\api\service\AwardService;

class Test extends ApiBase
{
    public function award()
    {
        $awardS = new AwardService();
        $res = $awardS->tradingReward(10000,85);
//        if($res)
//            echo 1;
//        else
//            echo 2;
    }
}
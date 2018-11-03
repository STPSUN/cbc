<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/28
 * Time: 15:08
 */

namespace web\api\controller;


use think\Db;
use web\api\model\AwardIssue;
use web\api\model\MemberNodeIncome;
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
        $incomeM = new MemberNodeIncome();
//        $is_release = $incomeM->whereTime('create_time','today')->find();
//        if(!empty($is_release))
//            return;

        $nodeS = new NodeService();
        $nodeS->nodeRelease();
//        $nodeS->updateBalanceReleaseNum();
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


    public function addtime(){

        set_time_limit(0);
        $list=Db::table('tp_member_node')->select();
        foreach( $list as $v){
            $id=$v['id'];
            $addtime= $v['addtime'];
            $type=$v['type'];
            $pieces = explode(".", $addtime);
            $create_time=$pieces[0];

            $sjc=strtotime($create_time);
            if($type==1){
                $pass_time=$sjc+30*24*60*60;
            }
            if($type==3){
                $pass_time=$sjc+80*24*60*60;
            }
            if($type==4){
                $pass_time=$sjc+100*24*60*60;
            }
            if($type==7){
                $pass_time=$sjc+160*24*60*60;
            }
            if($type==8){
                $pass_time=$sjc+365*24*60*60;
            }

            Db::table('tp_member_node')->where("id='$id'")->update(['create_time' =>$create_time,'pass_time'=>$pass_time]);

        }
    }
    public function regtime()
    {
        set_time_limit(0);
        $list = Db::table('tp_member_account')->select();
        foreach ($list as $v) {
            $id = $v['id'];
            $addtime = $v['id_stand'];

            $pieces = explode(".", $addtime);
            $register_time = $pieces[0];
            $type_max=Db::table('tp_member_node')->where("user_id=$id")->max('type');

            Db::table('tp_member_account')->where("id='$id'")->update(['register_time' => $register_time,'node_level'=>$type_max]);

        }
    }

    public function test()
    {
        $time = date('Y-m-d H:i:s',1539569142);
        echo $time;
    }

    public function superAward()
    {
        set_time_limit(0);
        $awardIssue = new AwardIssue();
        $awardS = new AwardService();

        $data = $awardIssue->where('status',1)->limit(1)->select();

        $awardIssue->startTrans();
        try
        {
            foreach ($data as $v)
            {
                $awardS->tradingReward($v['amount'],$v['user_id']);
                $awardIssue->save([
                    'status' => 2,
                    'update_time' => NOW_DATETIME
                ],[
                    'id' => $v['id'],
                ]);
            }

            $awardIssue->commit();
            echo 1;
        }catch (\Exception $e)
        {
            $awardIssue->rollback();
            echo 2;
        }

    }

}











<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/28
 * Time: 15:08
 */

namespace web\api\controller;


use think\Cache;
use think\Db;
use web\api\model\AwardIssue;
use web\api\model\MemberNodeIncome;
use web\api\service\AwardService;
use web\api\service\MemberService;
use web\api\service\NodeService;

class Test extends ApiBase
{
    public function _initialize()
    {
        // exit();
    }

    public function award()
    {
        $awardS = new AwardService();
        $res = $awardS->tradingReward(300,13962426665);
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
        $res = $memberS->memberLevel(18657968098);
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

    public function getDayNode()
    {
        $nodeS = new NodeService();
        $amount = $nodeS->getDayNodeCount(15857909002);

        return empty($amount) ? 0 : $amount;
    }

    public function createExcel(){
        vendor('PHPExcel.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        // import("Vendor.PHPExcel.PHPExcel.Writer.Excel5", '', '.php');
        // import("Vendor.PHPExcel.PHPExcel.IOFactory", '', '.php');
        // $objPHPExcel = new \PHPExcel();
        // 设置文档信息，这个文档信息windows系统可以右键文件属性查看
        $objPHPExcel->getProperties()->setCreator("gca")
            ->setLastModifiedBy("gca")
            ->setTitle("gca point")
            ->setSubject("gca point")
            ->setDescription("gca Capital flow");
        //根据excel坐标，添加数据
        $objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue('A1', '用户')
        ->setCellValue('B1', '总释放量')
        ->setCellValue('C1', '本次释放')
        ->setCellValue('D1', '老系统释放')
        ->setCellValue('E1', '新系统释放')
        ->setCellValue('F1', '新老释放');

        $nodeS = new \web\api\model\MemberNode;
        $nodeIncomeS = new \web\api\model\MemberNodeIncome;

        $map['type'] = ['in','2,3,4,5,6,7'];
        $allnode = $nodeS->field('id,type,user_id,sum(release_yet) as release_yet,sum(total_num) as total_num')->where($map)->group('user_id')->select();
        $id = [];
        foreach ($allnode as $key => $value) {
            $id[] = $value['user_id'];
        }
        $where['user_id'] = ['in',$id];
        $where['type'] = ['in','2,3,4,5,6,7'];
        $where['create_time'] = ['lt','2018-11-28'];
        $allrelease = $nodeIncomeS->where($where)->field('user_id,sum(amount) amount')->group('user_id')->select();
        foreach ($allnode as $k => $v) {
            $allnode[$k]['can_release'] = $v['total_num'];
            foreach ($allrelease as $key => $value) {
                if($v['user_id']==$value['user_id']){
                    $less = $v['total_num']-$value['amount'];
                    $allnode[$k]['can_release'] = $less;
                    $allnode[$k]['less'] = $value['amount'];
                }
            }
            $num = $k+2;
            $objPHPExcel->getActiveSheet()
                ->setCellValue('A'.$num,$v['user_id'])
                ->setCellValue('B'.$num,$v['total_num'])
                ->setCellValue('C'.$num,$allnode[$k]['can_release'])
                ->setCellValue('D'.$num,$v['release_yet'])
                ->setCellValue('E'.$num,$allnode[$k]['less']-$v['release_yet'])
                ->setCellValue('F'.$num,$allnode[$k]['less']);
                        
        }

        // 设置第一个sheet为工作的sheet
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $filename = date('Y-m-d').'.xlsx';
        // 保存Excel 2007格式文件，保存路径为当前路径，名字为export.xlsx
        ob_end_clean();     //清除缓冲区,避免乱码
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header('Content-Disposition:inline;filename="'.$filename.'"');
        header("Content-Transfer-Encoding: binary");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Pragma: no-cache");
        $objWriter->save('php://output');
    }

    public function createExcelSuper(){
        vendor('PHPExcel.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        // import("Vendor.PHPExcel.PHPExcel.Writer.Excel5", '', '.php');
        // import("Vendor.PHPExcel.PHPExcel.IOFactory", '', '.php');
        // $objPHPExcel = new \PHPExcel();
        // 设置文档信息，这个文档信息windows系统可以右键文件属性查看
        $objPHPExcel->getProperties()->setCreator("gca")
            ->setLastModifiedBy("gca")
            ->setTitle("gca point")
            ->setSubject("gca point")
            ->setDescription("gca Capital flow");
        //根据excel坐标，添加数据
        $objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue('A1', '用户')
        ->setCellValue('B1', '总释放量')
        ->setCellValue('C1', '本次释放')
        ->setCellValue('D1', '本次释放扣除30%')
        ->setCellValue('E1', '老系统释放')
        ->setCellValue('F1', '新系统释放')
        ->setCellValue('G1', '新老释放');

        $nodeS = new \web\api\model\MemberNode;
        $nodeIncomeS = new \web\api\model\MemberNodeIncome;

        $map['type'] = 8;
        $allnode = $nodeS->field('id,type,user_id,sum(release_yet) as release_yet,sum(total_num) as total_num')->where($map)->group('user_id')->select();
        $id = [];
        foreach ($allnode as $key => $value) {
            $id[] = $value['user_id'];
        }
        $where['user_id'] = ['in',$id];
        $where['type'] = 8;
        $where['create_time'] = ['lt','2018-11-28'];
        $allrelease = $nodeIncomeS->where($where)->field('user_id,sum(amount) amount')->group('user_id')->select();
        foreach ($allnode as $k => $v) {
            $allnode[$k]['can_release'] = $v['total_num'];
            foreach ($allrelease as $key => $value) {
                if($v['user_id']==$value['user_id']){
                    $less = $v['total_num']-$value['amount'];
                    $allnode[$k]['can_release'] = $less;
                    $allnode[$k]['less'] = $value['amount'];
                }
            }
            $num = $k+2;
            $objPHPExcel->getActiveSheet()
                ->setCellValue('A'.$num,$v['user_id'])
                ->setCellValue('B'.$num,$v['total_num'])
                ->setCellValue('C'.$num,$allnode[$k]['can_release'])
                ->setCellValue('D'.$num,bcmul($allnode[$k]['can_release'], 0.7,2))
                ->setCellValue('E'.$num,$v['release_yet'])
                ->setCellValue('F'.$num,$allnode[$k]['less']-$v['release_yet'])
                ->setCellValue('G'.$num,$allnode[$k]['less']);
                        
        }

        // 设置第一个sheet为工作的sheet
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $filename = date('Y-m-d').'.xlsx';
        // 保存Excel 2007格式文件，保存路径为当前路径，名字为export.xlsx
        ob_end_clean();     //清除缓冲区,避免乱码
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header('Content-Disposition:inline;filename="'.$filename.'"');
        header("Content-Transfer-Encoding: binary");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Pragma: no-cache");
        $objWriter->save('php://output');
    }

    public function balanceDiff()
    {
        set_time_limit(0);
        $balanceM = new \addons\member\model\Balance();

        $userM = new \addons\member\model\MemberAccountModel();
        $users = $userM->field('id')->select();
        for ($i = 0; $i <= 1000; $i++)
        {
            if(empty($users[$i]['id']))
            {
                echo 'ok';
                break;
            }

            $amount = $balanceM->where(['user_id' => $users[$i]['id'], 'type' => 1])->value('amount');
            $use_amount = bcmul($amount,0.7,4);
            $balanceM->save([
                'amount' => $use_amount,
            ],[
                'user_id' => $users[$i]['id'],
                'type' => 2,
                'update_time' => NOW_DATETIME,
            ]);

            echo $users[$i]['id'] . '/';
        }

    }

    public function balanceDiff2()
    {
        set_time_limit(0);
        $balanceM = new \addons\member\model\Balance();

        $userM = new \addons\member\model\MemberAccountModel();
        $users = $userM->field('id')->select();
        $page = Cache::get('page2');
        if(empty($page))
        {
            $page = 0;
        }

        echo $page . '&&&';
        for ($i = $page; $i <= ($page + 1000); $i++)
        {
            if(empty($users[$i]['id']))
            {
                echo 'ok';
                break;
            }

            $amount = $balanceM->where(['user_id' => $users[$i]['id'], 'type' => 1])->value('amount');
            $amount2 = $balanceM->where(['user_id' => $users[$i]['id'], 'type' => 2])->value('amount');

            $num = bcmul($amount,0.7,4) - $amount2;
            if(abs($num) > 10)
            {
                echo $users[$i]['id'] . '/' . $num . '***';
            }
        }

        $page += 1000;
        Cache::set('page2',$page);
    }
}











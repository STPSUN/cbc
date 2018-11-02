<?php

namespace addons\member\user\controller;

use PHPExcel;
use web\api\service\NodeService;

class Member extends \web\user\controller\AddonUserBase{
    
    public function index(){
//        $is_auth = $this->_get('is_auth');
//        if($is_auth == ''){
//            $is_auth = 2; //待认证
//        }
//        $this->assign('is_auth',$is_auth);
        return $this->fetch();
    }
    
    public function loadList(){
//        $is_auth = $this->_get('is_auth');
        $keyword = $this->_get('keyword');
        $filter = 'logic_delete=0';
        if ($keyword != null) {
            $filter .= ' and id like \'%' . $keyword . '%\' or phone like \'%' . $keyword . '%\'';
        }
        $m = new \addons\member\model\MemberAccountModel();
        $Balance = new \addons\member\model\Balance();
        $total = $m->getTotal($filter);
        $rows = $m->getList($this->getPageIndex(), $this->getPageSize(), $filter);
        foreach ($rows as $key => $value) {
            $info = $Balance->where(['user_id'=>$value['id']])->select();
            foreach ($info as $k => $v) {
                if($v['type']==1){
                    $rows[$key]['total_cbc'] = $v['amount']+0;
                }elseif($v['type']==2){
                    $rows[$key]['can_use'] = $v['amount']+0;
                }elseif($v['type']==3){
                    $rows[$key]['lock_cbc'] = $v['amount']+0;
                }elseif($v['type']==4){
                    $rows[$key]['code_cbc'] = $v['amount']+0;
                }elseif($v['type']==5){
                    $rows[$key]['release_cbc'] = $v['amount']+0;
                }
            }
        }
        return $this->toDataGrid($total, $rows);
    }

    /**
     * 增加挂卖权限
     */
    public function power(){
        $user_id = $this->_post('id');
        if(!$user_id) return $this->failData('错误的参数');
        $TransferM = new \addons\member\model\Transfer();
        $info = $TransferM->findData($user_id);
        if($info){
            $info['power'] = 1;
            $info['update_at'] = NOW_DATETIME;
        }else{
            $info = [
                'user_id'       => $user_id,
                'today_quota'   => 0,
                'quota'         => 0,
                'quota_at'      => NOW_DATETIME,
                'today_at'      => NOW_DATETIME,
                'power'         => 1,
                'update_at'     => NOW_DATETIME,
                'create_at'     => NOW_DATETIME,
            ];
        }
        // print_r($info);
        $res = $TransferM->save($info);
        if($res){
            return $this->successData('修改权限成功');
        }else{
            return $this->failData('修改权限失败');
        }
    }
    
    public function edit(){
        $m = new \addons\member\model\MemberAccountModel();
        if(IS_POST){
            $data = $_POST;
            $password = $this->_post("now_password");
            if(!empty($password)){
                if (!preg_match("/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,20}$/", $password)) {
                    return $this->failData('请输入5~20位字母数字密码');
                }
                $data['password'] = md5($password);
            }
            if($data['id']){
                $m->save($data);
                return $this->successData();
            }else{
                return $this->failData('用户id为空');
            }
        }else{
            
            $list = $m->field('id,username')->where('logic_delete=0')->order('id asc')->select();
            $this->assign('user_list', json_encode($list, 256));
            $this->assign('id', $this->_get('id'));
            $this->setLoadDataAction('loadData');
            $mealM = new \addons\baodan\model\MealConfig();
            $meals = $mealM->getDataList(-1,-1,'','','id asc');
            $this->assign('meals',$meals);
            return $this->fetch();
        }
    }
    
    public function loadData() {
        $id = $this->_get('id');
        $m = new \addons\member\model\MemberAccountModel();
        $data = $m->getDetail($id);
        $father = $m->getSingleField($data['pid'],'phone');
        $data['father'] = $father;
        return $data;
    }
    
    public function select_user(){
        return $this->fetch();
    }
    
    public function loadSelectUser(){
        $keyword = $this->_get('keyword');
        $filter = 'logic_delete=0';
        if ($keyword != null) {
            $filter .= ' and username like \'%' . $keyword . '%\'';
        }
        $m = new \addons\member\model\MemberAccountModel();
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }


    /**
     * 认证
     */
    public function auth(){
       if(IS_POST){
           $is_auth = $this->_post('is_auth');
           $user_id = $this->_post('id');
           if($is_auth && $user_id){
                $m = new \addons\member\model\MemberAccountModel();
                $data['id'] = $user_id;
                $data['is_auth'] = $is_auth;

                $m->startTrans();
                try
                {
                    $data['node_level'] = 1;
                    $data['user_level'] = 1;
                    $memberSer = new \web\api\service\MemberService();
                    $res = $memberSer->memberLevel($user_id);
                    if(!$res) $m->rollback();
                    $res = $m->save($data);
                    if(!$res) $m->rollback();
                    //赠送微信节点
                    $nodeS = new NodeService();
                    $nodeS->sendNode($user_id);

                    $m->commit();
                    return $this->successData();
                }catch (\Exception $e)
                {
                    $m->rollback();
                    return $this->failData('失败');
                }
           }else{
               return $this->failData('缺少参数');
           }
       }else{
           $this->assign('id',$this->_get('id'));
           $this->setLoadDataAction('loadCard');
           return $this->fetch();
       }
    }
    
    
    /**
     * 加载认证数据
     * @return type
     */
    public function loadCard(){
        $id = $this->_get('id');
        $m = new \addons\member\model\MemberAccountModel();
        $data = $m->getAuthData($id);
        return $data;
    }
    
    /**
     * 拨币
     * @return type
     */
    public function add_coin_stock(){
        if(IS_POST){
            $user_id = $this->_post('id');
            $amount = $this->_post('amount');
            $type = $this->_post('type');
            $remark = $this->_post('remark');
            $memberM = new \addons\member\model\MemberAccountModel();
            $m = new \addons\member\model\Balance();
            $m->startTrans();
            $balance = $m->getBalanceByType($user_id,$type);
            try{
                if(!empty($balance)){
                    $id = $balance['id'];
                    $before_amount = $balance['amount'];
                    $balance['type'] = $type;
                    $balance['amount'] = $before_amount + $amount;
                    $balance['update_time'] = NOW_DATETIME;
                    $m->save($balance);
                }else{
                    $before_amount = 0;
                    $balance['user_id'] = $user_id;
                    $balance['type'] = $type;
                    $balance['amount'] = $amount;
                    $balance['update_time'] = NOW_DATETIME;
                    $id = $m->add($balance);
                }
                if(!$id){                
                    $m->rollback();
                    return $this->failData('拨币失败');
                }
                $rm = new \addons\member\model\TradingRecord();
                $after_amount = $balance['amount'];
                $asset_type = $type;
                if($amount > 0){
                    $plus = 1;
                    $change_type = 1; //增加
                }else{
                    $plus = 0;
                    $change_type= 0;//减少
                    $amount = abs($amount);
                }
                $type = 13;//后台拨币
                $r_id = $rm->addRecord($user_id, $amount, $before_amount, $after_amount,$asset_type, $type, $change_type,0, $remark);
                if(!$r_id){
                    $m->rollback();
                    return $this->failData();
                }
                if($asset_type==1){
                    if(!$plus){
                        $amount = -$amount;
                    }
                    $balance = $m->getBalanceByType($user_id,2);
                    $amount2 = bcmul($amount, 0.7,2);
                    if(!empty($balance)){
                        $id = $balance['id'];
                        $before_amount = $balance['amount'];
                        $balance['type'] = 2;
                        $balance['amount'] = $before_amount + $amount2;
                        $balance['update_time'] = NOW_DATETIME;
                        $m->save($balance);
                    }else{
                        $before_amount = 0;
                        $balance['user_id'] = $user_id;
                        $balance['type'] = 2;
                        $balance['amount'] = $amount2;
                        $balance['update_time'] = NOW_DATETIME;
                        $id = $m->add($balance);
                    }
                    $rm = new \addons\member\model\TradingRecord();
                    $after_amount = $balance['amount'];
                    $asset_type = 2;
                    if($amount2 > 0){
                        $change_type = 1; //增加
                    }else{
                        $change_type= 0;//减少
                        $amount2 = abs($amount2);
                    }
                    $type = 13;//后台拨币
                    $r_id = $rm->addRecord($user_id, $amount2, $before_amount, $after_amount,$asset_type, $type, $change_type,0, $remark);
                    if(!$r_id){
                        $m->rollback();
                        return $this->failData();
                    }
                }
                $m->commit();
                return $this->successData();
            } catch (\Exception $ex) {
                $m->rollback();
                return $this->failData($ex->getMessage());
            }
            
        }else{
            $m = new \addons\config\model\BalanceConf();
            $list = $m->getDataList(-1,-1,'','id,name','id asc');
            $this->assign('types',$list);
            $this->assign('id',$this->_get('id'));
            return $this->fetch();
        }
    }
    
    public function view_childs(){
        $id = $this->_get('id');
        $m = new \addons\member\model\MemberAccountModel();
        $data = $m->getDetail($id,'id,aid,username,left_total,right_total');
        if($data){
            $data['children'] = $m->getChildsByAID($id);
        }
        $this->assign('data', json_encode($data, 256));
        return $this->fetch();
    }
    
    public function load_childs(){
        $aid = $this->_get('aid');
        $m = new \addons\member\model\MemberAccountModel();
        return $m->getChildsByAID($aid);
    }
    
    public function view_balance(){
        $id = $this->_get('id');
        $m = new \addons\member\model\Balance();
        $filter = 'user_id='.$id;
        $list = $m->getList(-1,-1,$filter);
//        dump($list);exit;
        $this->assign('data',$list);
        return $this->fetch();
    }
    
    
    
    public function change_frozen(){
        $id = $this->_post('id');
        $status = $this->_post('status');
        if($status != 0){
            $status = 1;
        }
        $m = new \addons\member\model\MemberAccountModel();
        try{
            $ret = $m->changeFrozenStatus($id, $status);
            if($ret > 0){
                return $this->successData();
            }else{
                $message = '操作失败';
                return $this->failData($message);
            }
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }
    
    /**
     * 逻辑删除
     * @return type
     */
    public function del(){
        $id = $this->_post('id');
        $m = new \addons\member\model\MemberAccountModel();
        try{
            $where1['aid'] = $id;
            $user = $m->where($where1)->find();
            if(!empty($user)){
                return $this->failData('存在下级节点用户:'.$user['username']);
            }
//            删除账号后退款到推荐人用户 ， 删除的账号下面如果有节点 就不能删除
            $m->startTrans();
            //删除资产
            $b = new \addons\member\model\Balance();
            $where['user_id'] = $id;
            $del = $b->where($where)->delete();
            //删除报单订单
            $o = new \addons\baodan\model\MealOrder();
            $o_del = $o->where($where)->delete();
            //删除结算记录
            $tbr = new \addons\member\model\TotalBonusRecord();
            $tbr->where($where)->delete();
            $br = new \addons\member\model\BonusRecord();
            $br->where($where)->delete();
            $ret = $m->deleteData($id);
            if($ret > 0){
                $m->commit();
                return $this->successData();
            }else{
                $m->rollback();
                $message = '删除失败';
                return $this->failData($message);
            }
        } catch (\Exception $ex) {
            $m->rollback();
            return $this->failData($ex->getMessage());
        }
    }
    
    /**
     * 导出数据
     */
    public function exportout(){
        $m = new \addons\member\model\MemberAccountModel();
        $all = $m->select();
        $Balance = new \addons\member\model\Balance();
        
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
        ->setCellValue('A1', '手机号')
        ->setCellValue('B1', '节点等级')
        ->setCellValue('C1', '会员等级')
        ->setCellValue('D1', '信用等级')
        ->setCellValue('E1', '真实姓名')
        ->setCellValue('F1', '认证状态')
        ->setCellValue('G1', '总额')
        ->setCellValue('H1', '可用余额')
        ->setCellValue('I1', '锁仓')
        ->setCellValue('J1', '激活码')
        ->setCellValue('K1', '今日释放')
        ->setCellValue('L1', '注册时间');

        foreach ($all as $key => $value) {
            $info = $Balance->where(['user_id'=>$value['id']])->select();
            $total_cbc = 0;
            $can_use = 0;
            $lock_cbc = 0;
            $code_cbc = 0;
            $release_cbc = 0;
            foreach ($info as $k => $v) {
                if($v['type']==1){
                    $total_cbc = $v['amount']+0;
                }elseif($v['type']==2){
                    $can_use = $v['amount']+0;
                }elseif($v['type']==3){
                    $lock_cbc = $v['amount']+0;
                }elseif($v['type']==4){
                    $code_cbc = $v['amount']+0;
                }elseif($v['type']==5){
                    $release_cbc = $v['amount']+0;
                }
            }
            //1 微型 | 2 小型（SS） | 3 小型（S） | 4 中小型 | 5 中大型 | 6 大型 | 7 超大型 | 8 超级',
            $node_level = '';
            if($value['node_level']==1){
                $node_level = '微型';
            }elseif($value['node_level']==2){
                $node_level = '小型（SS）';
            }elseif($value['node_level']==3){
                $node_level = '小型（S）';
            }elseif($value['node_level']==4){
                $node_level = '中小型';
            }elseif($value['node_level']==5){
                $node_level = '中大型';
            }elseif($value['node_level']==6){
                $node_level = '大型';
            }elseif($value['node_level']==7){
                $node_level = '超大型';
            }elseif($value['node_level']==8){
                $node_level = '超级';
            }
            //user_level 1 会员 | 2 盟友 | 3 盟主 | 4 酋长 | 5 联盟大使 | 6 联合创始人',
            $user_level = '';
            if($value['user_level']==1){
                $user_level = '会员';
            }elseif($value['user_level']==2){
                $user_level = '盟友';
            }elseif($value['user_level']==3){
                $user_level = '盟主';
            }elseif($value['user_level']==4){
                $user_level = '酋长';
            }elseif($value['user_level']==5){
                $user_level = '联盟大使';
            }elseif($value['user_level']==6){
                $user_level = '联合创始人';
            }
            //-1:不通过，0=未认证，1=已认证，2=待认证',
            $is_auth = '';
            if($value['is_auth']==-1){
                $is_auth = '不通过';
            }elseif($value['is_auth']==0){
                $is_auth = '未认证';
            }elseif($value['is_auth']==1){
                $is_auth = '已认证';
            }elseif($value['is_auth']==2){
                $is_auth = '待认证';
            }
            $num = $key+2;
            $objPHPExcel->getActiveSheet()
                ->setCellValue('A'.$num,$value['phone'])
                ->setCellValue('B'.$num,$node_level)
                ->setCellValue('C'.$num,$user_level)
                ->setCellValue('D'.$num,$value['credit_level'])
                ->setCellValue('E'.$num,$value['real_name'])
                ->setCellValue('F'.$num,$is_auth)
                ->setCellValue('G'.$num,$total_cbc)
                ->setCellValue('H'.$num,$can_use)
                ->setCellValue('I'.$num,$lock_cbc)
                ->setCellValue('J'.$num,$code_cbc)
                ->setCellValue('K'.$num,$release_cbc)
                ->setCellValue('L'.$num,$value['register_time']);
                
        }
        // foreach($all as $k=>$v){
        //     $num = $k+2;
        //     $objPHPExcel->getActiveSheet()
        //         ->setCellValue('A'.$num,$v['userid'])
        //         ->setCellValue('B'.$num,$v['username'])
        //         ->setCellValue('C'.$num,$v['mobile'])
        //         ->setCellValue('D'.$num,$v['cangku_num'])
        //         ->setCellValue('E'.$num,$v['fengmi_num']);
            
        // }
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

}



<?php

namespace addons\member\model;

/**
 * Description of TradingRecord
 *
 * @author zhuangminghan 
 */
class Transfer extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'member_transfer';
    }
    

    /**
     * 查找订单
     * @param id int 
     * @return array
     */
    public function findData($user_id){
        return $this->where(['user_id'=>$user_id])->find();
    }

    /**
     * 更新可用额度
     */
    public function updateQuota($user_id,$amount,$type=0){
        $info = $this->where(['user_id'=>$user_id])->find();
        if(!$info){
            $info = [
                'user_id'       => $user_id,
                'quota'         => 0,
                'quota_at'      => NOW_DATETIME,
                'today_quota'   => 0,
                'today_at'      => NOW_DATETIME,
                'power'         => 0,
                'create_at'     => NOW_DATETIME,
                'update_at'     => '0000-00-00 00:00:00',
                'can_sell'     => 1,
            ];
        }else{
            $info['quota_at'] = NOW_DATETIME;
            if(!$type){
                if(!$info['power']){
                    $info['quota'] = $info['quota']-$amount;
                } 
            }else{
                if(!$info['power']){
                    $info['quota'] = $info['quota']+$amount;
                }
            }
        }
        $res = $this->save($info);
        if(!$res){
            return false;
        }else{
            return $info;
        }
    }

    /**
     * 更新金额额度
     */
    public function updateTodayQuota($user_id,$amount,$type=0){
        $info = $this->where(['user_id'=>$user_id])->find();
        if(!$info){
            $info = [
                'user_id'       => $user_id,
                'quota'         => 0,
                'quota_at'      => NOW_DATETIME,
                'today_quota'   => 0,
                'today_at'      => NOW_DATETIME,
                'power'         => 0,
                'update_at'     => NOW_DATETIME,
                'create_at'     => NOW_DATETIME,
            ];
        }else{
            $info['today_at'] = NOW_DATETIME;
            $info['update_at'] = NOW_DATETIME;
            if($type){
                $info['today_quota'] = $info['today_quota']-$amount;
            }else{
                $info['today_quota'] = $info['today_quota']+$amount;
            }
        }
        $res = $this->save($info);
        if(!$res){
            return false;
        }else{
            return $info;
        }
    }

    /**
     * 获取订单列表
     */
    public function getOrderList($map,$page,$size,$order = 'id desc'){
        if(!isset($map['status']))  $map['status'] = 0;
        if(isset($map['type']))     $map['t.type'] = $map['type'];
        if(isset($map['user_id']))  $map['t.user_id'] = $map['user_id'];
        unset($map['type']);
        unset($map['user_id']);
        if(isset($map['p.type'])){
            $list =  $this->alias('t')->field('t.*,m.phone,m.credit_level user_level,m.username,m.head_img,m.is_auth')->where($map)
            ->join("member_account m",'m.id=t.user_id','LEFT')
            ->join("member_pay_config p",'p.user_id=t.user_id','LEFT')->order($order)
            ->limit($page,$size)->select();
        }else{
            $list =  $this->alias('t')->field('t.*,m.phone,m.credit_level user_level,m.username,m.head_img,m.is_auth')->where($map)
            ->join("member_account m",'m.id=t.user_id','LEFT')->order($order)
            ->limit($page,$size)->select();
        }
        foreach ($list as $key => $value) {
            $info = $this->table('tp_member_pay_config')->where(['user_id'=>$value['user_id']])->select();
            foreach ($info as $k => $v) {
                if($v['type']==1){
                    $list[$key]['wechat'] = $v['account'];
                }elseif($v['type']==2){
                    $list[$key]['alipay'] = $v['account'];
                }elseif($v['type']==3){
                    $list[$key]['bank'] = $v['account'];
                }
            }
        }
        return $list;
    }

    /*
    *   获取订单数量
    */
    public function getCount($map){
        $map['status'] = 0;
        return $this->where($map)->count();
    }


    public function getTrandTotal($filter = '') {
        $userM = new \addons\member\model\MemberAccountModel();
        $sql = '(select a.*,b.username susername,b.phone sphone,c.username busername,c.phone bphone from '.$this->getTableName().' a left join '.$userM->getTableName().' b on a.user_id=b.id left join '.$userM->getTableName().' c on a.to_user_id=c.id) y';
        $sql = 'select count(*) c from '.$sql;
        if($filter != ''){
            $sql .= ' where ' . $filter;
        }
        $result = $this->query($sql);
        if (count($result) > 0)
            return intval($result[0]['c']);
        else
            return 0;
    }

    /**
     * 获取订单列表数据
     */
    public function getList($filter, $pageIndex, $pageSize, $order='y.id desc'){
        $userM = new \addons\member\model\MemberAccountModel();
        $sql = 'select a.*,b.username susername,b.phone sphone,c.username busername,c.phone bphone from '.$this->getTableName().' a left join '.$userM->getTableName().' b on a.user_id=b.id left join '.$userM->getTableName().' c on a.to_user_id=c.id';
        if($filter != ''){
            $sql = 'select * from ('.$sql.') as y where '.$filter;
        }
        // echo $sql;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    
    }
}

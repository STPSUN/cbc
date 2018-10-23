<?php

namespace addons\member\model;

/**
 * Description of TradingRecord
 *
 * @author zhuangminghan 
 */
class Trading extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'trading';
    }
    

    /**
     * 查找订单
     * @param trad_id int 
     * @return array
     */
    public function findTrad($trad_id){
        return $this->where(['id'=>$trad_id])->find();
    }


    /**
     * 获取订单列表
     */
    public function getOrderList($map,$page,$size){
        if(!isset($map['status'])) $map['status'] = 0;
        if(isset($map['type'])) $map['t.type'] = $map['type'];
        unset($map['type']);
        $map['p1.type'] = 1;
        $map['p2.type'] = 2;
        $map['p2.type'] = 3;
        return $this->alias('t')->field('t.*,m.user_level,m.username,m.head_img,m.is_auth,p1.account wechat,p2.account alipay,p3.account bank')->where($map)
        ->join("member_account m",'m.id=t.user_id','LEFT')
        ->join("member_pay_config p1",'p1.user_id=t.user_id','LEFT')
        ->join("member_pay_config p2",'p2.user_id=t.user_id','LEFT')
        ->join("member_pay_config p3",'p3.user_id=t.user_id','LEFT')
        ->limit($page,$size)->select();
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

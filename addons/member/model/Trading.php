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
        return $this->where($map)->limit($page,$size)->select();
    }

    /*
    *   获取订单数量
    */
    public function getCount($map){
        $map['status'] = 0;
        return $this->where($map)->count();
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
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    
    }
}

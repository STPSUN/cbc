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
}

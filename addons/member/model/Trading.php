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
   
}

<?php

namespace addons\member\model;

/**
 * Description of 投诉
 *
 * @author zhuangminghan 
 */
class TradingComplaint extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'trading_complaint';
    }
    
    /**
     * 添加投诉
     */
    public function addComplaint($data){
        $data['type'] = 0;
        $data['create_at'] = NOW_DATETIME;
        $data['update_at'] = NOW_DATETIME;
        return $this->add($data);
    }



    /**
     * 
     */
    public function getTrandTotal($filter = '') {
        $userM = new \addons\member\model\MemberAccountModel();
        $Trading = new \addons\member\model\Trading();
        $sql = '(select a.*,b.username susername,b.phone sphone,c.username busername,c.phone bphone from '.$this->getTableName().' t left join '.$Trading->getTableName().' a on a.id=t.trad_id left join '.$userM->getTableName().' b on a.user_id=b.id left join '.$userM->getTableName().' c on a.to_user_id=c.id ) y';
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
        $Trading = new \addons\member\model\Trading();
        $sql = 'select t.*,b.username susername,b.phone sphone,c.username busername,c.phone bphone,a.order_id from '.$this->getTableName().' t left join '.$Trading->getTableName().' a on a.id=t.trad_id left join '.$userM->getTableName().' b on a.user_id=b.id left join '.$userM->getTableName().' c on a.to_user_id=c.id';
        if($filter != ''){
            $sql = 'select * from ('.$sql.') as y where '.$filter;
        }
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    
    }
}

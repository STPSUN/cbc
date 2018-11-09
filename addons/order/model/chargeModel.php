<?php

namespace addons\order\model;

/**
 * 
 *
 * @author shilinqing
 */
class chargeModel extends \web\common\model\BaseModel {

    protected function _initialize() {
        $this->tableName = 'shop_voucher';
    }
    public function getList($pageIndex = -1, $pageSize = -1, $filter = '', $order = 'id asc') {
        $m = new \addons\member\model\MemberAccountModel();
        $sql = 'select a.*,b.username from ' . $this->getTableName() . ' a,'.$m->getTableName().' b where a.user_id=b.id';
        if (!empty($filter))
            $sql .=  ' and '.$filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }

    public function getPicById($id){
        $where['id'] = $id;
        return $this->where($where)->field('pic')->find();
    }


}

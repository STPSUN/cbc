<?php

namespace addons\shop\model;

/**
 * 收货地址表
 */
class MemberAddress extends \web\common\model\BaseModel {

    protected function _initialize() {
        $this->tableName = 'member_address';
    }

    public function getList($pageIndex = -1, $pageSize = -1, $filter = '', $order = 'id asc') {
        $sql = 'select * from ' . $this->getTableName();
        if (!empty($filter))
            $sql .= ' where ' . $filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }

    /*
     * 获取地址详情
     */
    public function getAddressDetail($id, $userId) {
        $where['id'] = $id;
        $where['user_id'] = $userId;
        return $this->where($where)->find();
    }

}

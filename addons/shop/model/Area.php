<?php

namespace addons\shop\model;

class Area extends \web\common\model\BaseModel {

    protected function _initialize() {
        $this->tableName = 'area';
    }

    public function getList($pageIndex = -1, $pageSize = -1, $filter = '', $order = 'id asc') {
        $sql = 'select id,goods_name,img,shop_price,sales_sum from ' . $this->getTableName();
        if (!empty($filter))
            $sql .= ' where ' . $filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }

    //获取地区名称
    public function getAreaName($id) {
        return $this->where('id=' . $id)->value('name');
    }


}

<?php

namespace web\common\model\sys;

/**
 * 系统参数。
 */
class FinancialModel extends \web\common\model\Model {

    protected function _initialize() {
        $this->tableName = 'financial_limit';
    }

    /**
     * 获取利息类型
     */
    public function getFinancial($id){
        return $this->find($id);
    }

    /**
     * 获取数据详情。
     * @param type $id
     * @return type
     */
    public function getDetail($id) {
        return $this->where(['id'=>$id])->find();
    }


    /**
     * 获取用户理财记录
     */
    public function getDataList($pageIndex = -1, $pageSize = -1, $filter = '', $fileds = '', $order = 'id asc') {
        $sql = 'select * from ' .  $this->getTableName();
        if ($filter != '') {
            $sql = 'select * from ' .  $this->getTableName() . '  where ' . $filter;
        }
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }


    public function deleteData($id){
        return $this->where(['id'=>$id])->delete();
    }
}

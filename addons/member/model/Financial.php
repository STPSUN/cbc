<?php

namespace addons\member\model;

/**
 * 投资理财
 *
 * @author shilinqing
 */
class Financial extends \web\common\model\BaseModel {

    protected function _initialize() {
        $this->tableName = 'financial';
    }

    /**
     * 获取用户理财记录
     */
    public function getDataList($pageIndex = -1, $pageSize = -1, $filter = '', $fileds = '', $order = 'id asc') {
        $userM = new \addons\member\model\MemberAccountModel();
        if($fileds == ''){
            $sql = 'select * from ' . $this->getTableName();
        }else{
            $sql = 'select ' . $fileds . ' from ' . $this->getTableName();
        }
        $sql = 'select s.*,u.username from (' . $sql . ') as s left join ' . $userM->getTableName() . ' u on s.user_id=u.id ';
        if ($filter != '') {
            $sql = 'select * from (' . $sql . ') as y where ' . $filter;
        }
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }

}

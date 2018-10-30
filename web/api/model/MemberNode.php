<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/19
 * Time: 14:10
 */

namespace web\api\model;


class MemberNode extends \web\common\model\BaseModel
{
    protected function _initialize()
    {
        $this->tableName = 'member_node';
    }


    public function getList($pageIndex = -1, $pageSize = -1, $filter = '', $order = 'id asc') {
    	$member = new \addons\member\model\MemberAccountModel();
        $sql = 'select tab.*,c.phone as phone,d.phone g_phone from ' . $this->getTableName() . ' as tab left join ' . $member->getTableName() . ' c on tab.user_id=c.id left join ' . $member->getTableName() . ' d on tab.give_user_id=d.id ';
        if (!empty($filter))
            $sql = 'select * from (' . $sql . ') t where ' . $filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }
}
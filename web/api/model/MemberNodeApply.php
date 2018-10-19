<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/19
 * Time: 15:37
 */

namespace web\api\model;


class MemberNodeApply extends \web\common\model\BaseModel
{
    public function _initialize()
    {
        $this->tableName = 'member_node_apply';
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
        $sql = 'select s.*,u.username u from (' . $sql . ') as s left join ' . $userM->getTableName() . ' u on s.username=u.phone ';
        if ($filter != '') {
            $sql = 'select * from (' . $sql . ') as y where ' . $filter;
        }
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }

    // public function getDataList(){

    // }
}
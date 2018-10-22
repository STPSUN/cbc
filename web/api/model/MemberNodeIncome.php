<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/22
 * Time: 11:04
 */

namespace web\api\model;


class MemberNodeIncome extends \web\common\model\BaseModel
{
    public function _initialize()
    {
        $this->tableName = 'member_node_income';
    }

    /**
     * 获取列表数据
     * @param type $pageIndex 当前页
     * @param type $pageSize 每页数量
     * @param type $filter 过滤条件
     * @param type $fields 字段信息
     * @param type $order 排序
     * @return type
     */
    public function getDataList2($pageIndex = -1, $pageSize = -1, $filter = '', $fields = '', $order = 'id desc') {
        $sql = 'select ';
        if (!empty($fields))
            $sql .= $fields;
        else
            $sql .= '*';
        $sql .= ' from ' . $this->getTableName();
        if (!empty($filter))
            $sql .= ' where ' . $filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/25
 * Time: 11:25
 */

namespace web\api\model;


class Leave extends \web\common\model\BaseModel
{
    protected function _initialize()
    {
        $this->tableName = 'leave';
    }


    /**
     * 获取订单列表数据
     */
    public function getList($filter, $pageIndex, $pageSize, $order='y.id desc'){
        $userM = new \addons\member\model\MemberAccountModel();
        $sql = 'select t.*,b.phone from '.$this->getTableName().' t left join '.$userM->getTableName().' b on b.id=t.user_id';
        if($filter != ''){
            $sql = 'select * from ('.$sql.') as y where '.$filter;
        }
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    
    }
}
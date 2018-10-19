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
}
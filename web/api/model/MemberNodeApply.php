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
}
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

}
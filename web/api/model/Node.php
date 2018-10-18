<?php

/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/17
 * Time: 14:13
 */
namespace web\api\model;

class Node extends \web\common\model\BaseModel
{
    protected function _initialize()
    {
        $this->tableName = 'node';
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/11/3
 * Time: 10:54
 */

namespace web\api\model;


class AwardIssue extends \web\common\model\BaseModel
{
    public function _initialize()
    {
        $this->tableName = 'award_issue';
    }
}
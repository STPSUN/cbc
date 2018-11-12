<?php

namespace addons\member\model;

/**
 * 会员提币
 *
 * @author shilinqing
 */
class MemberExtract extends \web\common\model\BaseModel {

    protected function _initialize() {
        $this->tableName = 'member_exchange';
    }

    
}

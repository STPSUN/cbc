<?php

 namespace addons\member\model;

/**
 * Description of BonusRecord
 * 每日拨比记录
 * @author shilinqing
 */
class DailyTotalRecord extends \web\common\model\BaseModel {
    
    protected function _initialize() {
        $this->tableName = 'daily_total_record';
    }
    
    
    
}

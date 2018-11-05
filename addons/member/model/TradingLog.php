<?php

namespace addons\member\model;

/**
 * Description of TradingRecord
 *
 * @author zhuangminghan 
 */
class TradingLog extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'trading_log';
    }
    
}

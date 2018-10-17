<?php

namespace addons\shop\model;

/**
 * Description of Goods
 * 商城商品表
 * @author shilinqing
 */
class Goods extends \web\common\model\BaseModel {
    
    protected function _initialize(){
        $this->tableName = 'shop_goods';
    }
    
}

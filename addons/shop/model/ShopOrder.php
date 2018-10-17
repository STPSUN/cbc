<?php

namespace addons\shop\model;

/**
 * Description of Goods
 * 商城订单表
 * @author shilinqing
 */
class ShopOrder extends \web\common\model\BaseModel {
    
    protected function _initialize(){
        $this->tableName = 'shop_order';
    }
    
}

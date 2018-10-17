<?php

namespace addons\shop\model;

/**
 * Description of Goods
 * 商城商品属性表
 * @author shilinqing
 */
class GoodsProperty extends \web\common\model\BaseModel {
    
    protected function _initialize(){
        $this->tableName = 'shop_goods_property';
    }
    
}

<?php

namespace addons\shop\model;

/**
 * Description of Goods
 * 商城商品规格表
 * @author shilinqing
 */
class GoodsAttr extends \web\common\model\BaseModel {
    
    protected function _initialize(){
        $this->tableName = 'shop_goods_attr';
    }
    
}

<?php

namespace addons\shop\model;

/**
 * Description of Goods
 * 商城商品分类表
 * @author shilinqing
 */
class GoodsClass extends \web\common\model\BaseModel {

    protected function _initialize() {
        $this->tableName = 'shop_goods_class';
    }

    public function getList($level = 1) {
        $where = $this->getWhere(array('level' => $level));
        return $this->field('id,class_name')->where($where)->order('order_index asc,id asc')->select();
    }
    
    public function getClassGroup($level) {
        $where['level'] = $level;
        return $this->where($where)->field('id,pid,class_name')->order('order_index,id asc')->select();
        
    }
}

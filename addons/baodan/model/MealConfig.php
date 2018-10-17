<?php

namespace addons\baodan\model;

class MealConfig extends \web\common\model\BaseModel{
    
    protected function _initialize(){
        $this->tableName = 'baodan_meal_config';
    }
    
    public function getFieldByID($id,$field){
        $where['id'] = $id;
        return $this->where($where)->value($field);
    }
   
    
   
}

<?php

namespace addons\config\model;

class Quotation extends \web\common\model\BaseModel{
    
    protected function _initialize(){
        $this->tableName = 'sys_cbc_quotation';
    }
	
	public function getQuotationList($map,$field,$page,$size,$order = 'id desc'){
		return $this->field($field)->where($map)->limit($page,$size)->order($order)->select();
	}
}

<?php

namespace addons\config\model;

class Notice extends \web\common\model\BaseModel{
    
    protected function _initialize(){
        $this->tableName = 'sys_notice';
    }
	
	public function getNoticeList($map,$page,$size,$order = 'id desc'){
		return $this->where($map)->limit($page,$size)->order($order)->select();
	}
}

<?php

namespace web\api\controller;

use think\Lang;

class Crontab extends \web\common\controller\Controller {


    /**
     * 定时器访问
     * 超过30分钟未付款则取消订单
     */
    public function cancleOrder(){
        $tradingM = new \addons\member\model\Trading();
        $map['type'] = 1;
        $map['status'] = 0;
        $map['update_time'] = ['gt',date('Y-m-d H:i:s',(time()+30*60))];
        $list = $tradingM->where($map)->select();
        if(!$list) return $this->failJSON('暂无订单');
        foreach ($list as $key => $value) {
            $list[$key]['to_user_id'] = 0;
            $list[$key]['type'] = 0;
            $list[$key]['update_time']=NOW_DATETIME;
        }
        $res = $tradingM->save($list);
        if($res) $this->successJSON('update success');
        else $this->failJSON('update failed');
    }
    
   
}

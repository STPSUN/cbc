<?php
/**
 * Created by sublime.
 * User: zhuangminghan 
 * Date: 2018/10/22
 * 公告
 */

namespace web\api\controller;


class Notice extends ApiBase
{

    /**
     * 获取快讯
     */
    public function getMessage(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');
        $m = new \addons\config\model\Notice();
        $lang = $this->_post('lang');
        if($lang == 'en'){
            $map['lang'] = 1;
        }else{
            $map['lang'] = 0;
        }
        $map['type'] = 0;
        $page = $this->_post('page')?$this->_post('page'):0;
        $rows = $this->_post('rows')?$this->_post('rows'):15;
        $page = $page*$rows;
        $rows = $m->getNoticeList($map,$page,$rows, 'id desc');
        return $this->successJSON($rows);
    }

    /**
     * 获取公告
     */
    public function getNotice(){
        $user_id = $this->user_id;
        if($user_id <= 0) return $this->failData('请登录');
        $m = new \addons\config\model\Notice();
        $lang = $this->_post('lang');
        if($lang == 'en'){
            $map['lang'] = 1;
        }else{
            $map['lang'] = 0;
        }
        $map['type'] = 1;
        $page = $this->_post('page')?$this->_post('page'):0;
        $rows = $this->_post('rows')?$this->_post('rows'):15;
        $page = $page*$rows;
        $rows = $m->getNoticeList($map,$page,$rows, 'id desc');
        return $this->successJSON($rows);
    }


    /**
     * 获取行情
     */
    public function getQuotation(){
        $m = new \addons\config\model\Quotation();
        $list = $m->field('price_now,create_at')->order('create_at asc')->select();
        $now = $m->field('price_now,price_top,price_low,create_at')->order('id desc')->find();
        $month     = $m->where(['create_at'=>['between',[date('Y-m-d',strtotime('-1 month')),NOW_DATETIME]]])->order('id desc')->avg('price_now');
        $week      = $m->where(['create_at'=>['between',[date('Y-m-d',strtotime('-1 week')),NOW_DATETIME]]])->order('id desc')->avg('price_now');
        $yesterday = $m->where(['create_at'=>['between',[date('Y-m-d',strtotime('-1 days')),date('Y-m-d',strtotime('-1 days')).' 23:59:59']]])->order('id desc')->avg('price_now');
        $data['now'] = $now;
        $data['month'] = $month;
        $data['week'] = $week;
        $data['yesterday'] = $yesterday;
        $arr = [];
        foreach ($list as $key => $value) {
            $tmp = [];
            $tmp[] = (float)(strtotime($value['create_at']).'000');
            $tmp[] = (float)$value['price_now'];
            $arr[] = $tmp;
        }
        $data['list'] = $arr;
        $sysM = new \web\common\model\sys\SysParameterModel();
        $data['usdt'] = $sysM->getValByName('usdt_price');
        return $this->successJSON($data);
    }
}
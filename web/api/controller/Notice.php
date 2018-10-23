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
        $m = new \addons\config\model\Notice();
        $map['type'] = 0;
        $page = $this->_post('page')?$this->_post('page'):0;
        $rows = $this->_post('rows')?$this->_post('rows'):0;
        $page = $page*$rows;
        $rows = $m->getNoticeList($map,$page,$rows, 'id desc');
        return $this->successJSON($rows);
    }

    /**
     * 获取公告
     */
    public function getNotice(){
        $m = new \addons\config\model\Notice();
        $map['type'] = 1;
        $page = $this->_post('page')?$this->_post('page'):0;
        $rows = $this->_post('rows')?$this->_post('rows'):15;
        $page = $page*$rows;
        $rows = $m->getNoticeList($map,$page,$rows, 'id desc');
        return $this->successJSON($rows);
    }
}
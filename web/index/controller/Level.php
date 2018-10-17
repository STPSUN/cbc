<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace web\index\controller;

/**
 * Description of Level
 *
 * @author shilinqing
 */
class Level extends Base{
    //put your code here
    
    public function direct(){
        $m = new \addons\member\model\MemberAccountModel();
        $can_see_map = $m->where('id='.$this->user_id)->value('can_see_map');
        if($can_see_map == 0){
            return $this->fetch('404');
        }
        return $this->fetch();
        
    }

    public function getTree(){
        $m = new \addons\member\model\MemberAccountModel();
        return $m->getInviteTree($this->user_id);
    }
    
    public function view_childs(){
        $user_id = $this->_get('user_id');
        if(empty($user_id)){
            $user_id = $this->user_id;
        }
        $m = new \addons\member\model\MemberAccountModel();
        $can_see_map = $m->where('id='.$this->user_id)->value('can_see_map');
        if($can_see_map == 0){
            return $this->fetch('404');
        }
        $fields = 'id,aid,username,left_total,right_total';
        $data = $m->getDetail($user_id, $fields);
        if($data){
            $children = $m->getChildsByAID($user_id);
            if($children){
                foreach($children as $k => $_data){
                    $children[$k]['children'] = $m->getChildsByAID($_data['id']);
                }
            }else{
                $children = '';
            }
            
            $this->assign('children',$children);
        }
        $this->assign('parent', $data);
        return $this->fetch();
    }
    
    public function load_childs(){
        $aid = $this->_get('aid');
        $m = new \addons\member\model\MemberAccountModel();
        return $m->getChildsByAID($aid);
    }
    
    //邀请码
    public function invite(){
        $url = $this->getUserApiURL();
        $this->assign('user_url',$url);
        return $this->fetch();
    }
}

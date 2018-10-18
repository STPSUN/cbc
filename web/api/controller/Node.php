<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/17
 * Time: 13:49
 */

namespace web\api\controller;


use addons\member\model\MemberAccountModel;
use think\Request;
use think\Validate;

class Node extends ApiBase
{
    //购买节点
    public function buyNode()
    {
        $param = Request::instance()->post();

        $validate = new Validate([
            'node_num'   => 'require|integer',
            'num'       => 'require|integer',
            'give_username'  => 'integer',
            'node_id'   => 'require|integer',
        ]);

        $node_id = $param['node_id'];
        $node_num = $param['node_num'];
        $give_username = $param['give_username'];

        $nodeM = new \web\api\model\Node();
        $node = $nodeM->getDetail($node_id);
        if(empty($node))
            return $this->failJSON('该节点为空');

        $memberM= new MemberAccountModel();
        $give_user_id = $memberM->getUserByUsername($give_username);
        if(empty($give_user_id))
            return $this->failJSON("该赠送账号不存在");

        $data = array(
                'node_id'   => $node_id,
                'node_num'   => $node_num,
                'user_id'   => $this->user_id,
                'create_time'   => NOW_DATETIME,
            );

        if(empty($give_username))
        {
            $nodeM->save($data);
            return $this->successJSON();
        }else
        {


            $data['give_user_id'] = $give_username;
        }
    }
}
















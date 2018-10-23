<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/22
 * Time: 15:19
 */

namespace web\api\controller;

use addons\member\model\MemberAccountModel;

class Upload extends ApiBase
{
    public function uploadHead()
    {
        $base64 = $this->_post('image');
        if($base64){
            $savePath = 'user/';
            $ret = $this->base_img_upload($base64, $this->user_id, $savePath);
            if(!$ret['success']){
                return $this->failJSON($ret['message']);
            }

            $userM = new MemberAccountModel();
            $userM->save([
                'head_img'  => $ret['path'],
            ],[
                'id'    => $this->user_id,
            ]);
            return $this->successJSON();
        }
    }

    public function uploadImg()
    {
        $base64 = $this->_post('image');
        if($base64){
            $savePath = 'user/';
            $ret = $this->base_img_upload($base64, $this->user_id, $savePath);
            if(!$ret['success']){
                return $this->failJSON($ret['message']);
            }

            return $this->successJSON($ret['path']);
        }
    }
}
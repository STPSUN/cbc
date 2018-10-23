<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/22
 * Time: 15:19
 */

namespace web\api\controller;

class Upload extends ApiBase
{
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